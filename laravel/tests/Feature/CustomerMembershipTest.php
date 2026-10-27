<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_the_legacy_gym_membership_design(): void
    {
        GymPackage::factory()->create([
            'name' => 'Gói trải nghiệm test',
            'description' => 'Tập luyện không giới hạn',
        ]);

        $response = $this->get('/');

        $response->assertSee('Hội viên The Gym')
            ->assertSee('Tập luyện không giới hạn')
            ->assertSee('Gói tập');
    }

    public function test_customer_creates_a_pending_order_at_the_current_package_price(): void
    {
        $startDate = now(config('app.timezone'))->toDateString();
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create([
            'price' => 399000,
            'duration_months' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'package', 'gym_package_id' => $package->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'bank_transfer', 'start_date' => $startDate, 'amount' => 1])->assertSessionHasNoErrors();
        $invoice = DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->orderByDesc('MAHD')->first();
        $this->assertSame('Chưa thanh toán', $invoice->TRANGTHAI);
        $line = DB::table('gym_invoice_lines')->where('invoice_id', $invoice->MAHD)->where('kind', 'package')->first();
        $this->assertSame(399000, (int) $line->unit_price);
        $this->assertSame(1, json_decode($line->details, true)['months']);
        $this->assertSame($startDate, json_decode($line->details, true)['start_date']);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_customer_cannot_order_a_period_that_overlaps_a_paid_membership(): void
    {
        $membershipStart = now(config('app.timezone'))->startOfDay()->addMonthsNoOverflow(2);
        $membershipEnd = $membershipStart->copy()->addMonthNoOverflow();
        $attemptedStart = $membershipStart->copy()->subDays(15);
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create(['price' => 399000, 'duration_months' => 1]);
        $paidOrder = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 399000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        Membership::factory()->for($customer)->for($package, 'gymPackage')->for($paidOrder, 'order')->create([
            'starts_on' => $membershipStart->toDateString(),
            'expires_on' => $membershipEnd->toDateString(),
        ]);

        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'package', 'gym_package_id' => $package->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $response = $this->from(route('gym.cart'))->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash', 'start_date' => $attemptedStart->toDateString()]);

        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('membership_orders', 1);
    }

    public function test_guest_trial_request_requires_login_without_creating_an_account(): void
    {
        $response = $this->post(route('trials.store'), [
            'name' => 'Nguyen Van An',
            'phone' => '0901234567',
            'email' => 'an@example.test',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('trial_requests', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
