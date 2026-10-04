<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create([
            'price' => 399000,
            'duration_months' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer)->post(route('orders.store'), [
            'gym_package_id' => $package->id,
            'start_date' => '2026-10-10',
            'payment_method' => 'bank_transfer',
            'amount' => 1,
        ]);

        $response->assertRedirect(route('my.orders.index'));
        $this->assertDatabaseHas('membership_orders', [
            'user_id' => $customer->id,
            'gym_package_id' => $package->id,
            'amount' => 399000,
            'status' => 'pending',
        ]);
        $this->assertSame('2026-10-10', MembershipOrder::query()->firstOrFail()->start_date->toDateString());
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_customer_cannot_order_a_period_that_overlaps_a_paid_membership(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create(['price' => 399000, 'duration_months' => 1]);
        $paidOrder = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 399000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        Membership::factory()->for($customer)->for($package, 'gymPackage')->for($paidOrder, 'order')->create([
            'starts_on' => '2026-12-01',
            'expires_on' => '2027-01-01',
        ]);

        $response = $this->actingAs($customer)->from(route('packages.index'))->post(route('orders.store'), [
            'gym_package_id' => $package->id,
            'start_date' => '2026-11-15',
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('membership_orders', 1);
    }

    public function test_public_trial_request_does_not_create_an_account(): void
    {
        $response = $this->post(route('trials.store'), [
            'name' => 'Nguyen Van An',
            'phone' => '0901234567',
            'email' => 'an@example.test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('trial_requests', [
            'name' => 'Nguyen Van An',
            'phone' => '0901234567',
            'email' => 'an@example.test',
        ]);
        $this->assertDatabaseCount('users', 0);
    }
}
