<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payment_keeps_order_pending_and_does_not_create_membership(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $order = $this->pendingOrder();

        $response = $this->actingAs($staff)->post(route('staff.orders.payments.store', $order), [
            'amount' => 100000,
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertRedirect(route('staff.orders.index'));
        $this->assertDatabaseHas('order_payments', [
            'membership_order_id' => $order->id,
            'amount' => 100000,
            'received_by' => $staff->id,
        ]);
        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_confirmation_requires_full_received_amount(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $order = $this->pendingOrder();
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 100000]);

        $response = $this->actingAs($staff)->from(route('staff.orders.index'))
            ->post(route('staff.orders.confirm', $order), ['start_date' => '2026-10-10']);

        $response->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_full_confirmation_creates_one_membership_and_is_idempotent(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $order = $this->pendingOrder();
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 399000]);

        $this->actingAs($staff)->post(route('staff.orders.confirm', $order), [
            'start_date' => '2026-10-10',
        ])->assertRedirect(route('staff.orders.index'));

        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'paid']);
        $membership = Membership::query()->where('membership_order_id', $order->id)->firstOrFail();
        $this->assertSame('2026-10-10', $membership->starts_on->toDateString());
        $this->assertSame('2026-11-10', $membership->expires_on->toDateString());

        $this->actingAs($staff)->post(route('staff.orders.confirm', $order), [
            'start_date' => '2026-10-10',
        ])->assertRedirect(route('staff.orders.index'));

        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('order_payments', 1);
    }

    public function test_one_month_membership_starting_on_january_31_expires_on_february_28(): void
    {
        $this->travelTo('2026-01-31 09:00:00');
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create(['price' => 399000, 'duration_months' => 1]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 399000,
            'start_date' => '2026-01-31',
            'status' => 'pending',
        ]);
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 399000]);

        $this->actingAs($staff)->post(route('staff.orders.confirm', $order), [
            'start_date' => '2026-01-31',
        ])->assertRedirect(route('staff.orders.index'));

        $membership = Membership::query()->where('membership_order_id', $order->id)->firstOrFail();
        $this->assertSame('2026-01-31', $membership->starts_on->toDateString());
        $this->assertSame('2026-02-28', $membership->expires_on->toDateString());
    }

    public function test_staff_must_replace_a_past_or_overlapping_start_date_before_confirmation(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create(['price' => 399000, 'duration_months' => 1]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 399000,
            'start_date' => '2026-10-10',
            'status' => 'pending',
        ]);
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 399000]);

        $response = $this->actingAs($staff)->from(route('staff.orders.index'))
            ->post(route('staff.orders.confirm', $order), ['start_date' => '2026-10-10']);

        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('memberships', 0);

        $this->post(route('staff.orders.confirm', $order), ['start_date' => '2026-10-12'])
            ->assertRedirect(route('staff.orders.index'));

        $membership = Membership::query()->where('membership_order_id', $order->id)->firstOrFail();
        $this->assertSame('2026-10-12', $membership->starts_on->toDateString());
        $this->assertSame('2026-11-12', $membership->expires_on->toDateString());
    }

    public function test_customer_cannot_record_or_confirm_payment(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->pendingOrder();

        $this->actingAs($customer)->post(route('staff.orders.payments.store', $order), [
            'amount' => 399000,
            'payment_method' => 'cash',
        ])->assertForbidden();

        $this->post(route('staff.orders.confirm', $order), ['start_date' => '2026-10-10'])
            ->assertForbidden();

        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_staff_cannot_cancel_an_order_after_receiving_any_money(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $order = $this->pendingOrder();
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 100000]);

        $this->actingAs($staff)->from(route('staff.orders.index'))
            ->post(route('staff.orders.cancel', $order))
            ->assertSessionHasErrors('order');

        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'pending']);
    }

    private function pendingOrder(): MembershipOrder
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create(['price' => 399000, 'duration_months' => 1]);

        return MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 399000,
            'start_date' => '2026-10-10',
            'status' => 'pending',
        ]);
    }
}
