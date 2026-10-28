<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StaffMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payment_keeps_order_pending_and_does_not_create_membership(): void
    {
        $staff = User::factory()->employee()->create();
        $order = $this->pendingOrder();

        $response = $this->actingAs($staff)->post(route('staff.orders.payments.store', $order), [
            'amount' => 100000,
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertRedirect(route('staff.orders.show', $order));
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
        $startDate = now(config('app.timezone'))->toDateString();
        $staff = User::factory()->employee()->create();
        $order = $this->pendingOrder();
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 100000]);

        $response = $this->actingAs($staff)->from(route('staff.orders.index'))
            ->post(route('staff.orders.confirm', $order), ['start_date' => $startDate]);

        $response->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_full_confirmation_creates_one_membership_and_is_idempotent(): void
    {
        $this->travelTo('2026-10-21 09:00:00');
        $startDate = now(config('app.timezone'))->startOfDay();
        $staff = User::factory()->employee()->create();
        $order = $this->pendingOrder();
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 399000]);

        $this->actingAs($staff)->post(route('staff.orders.confirm', $order), [
            'start_date' => $startDate->toDateString(),
        ])->assertRedirect(route('staff.orders.show', $order));

        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'paid']);
        $membership = Membership::query()->where('membership_order_id', $order->id)->firstOrFail();
        $this->assertSame($startDate->toDateString(), $membership->starts_on->toDateString());
        $this->assertSame('2026-11-21', $membership->expires_on->toDateString());

        $this->actingAs($staff)->post(route('staff.orders.confirm', $order), [
            'start_date' => $startDate->toDateString(),
        ])->assertRedirect(route('staff.orders.show', $order));

        $this->assertDatabaseCount('memberships', 1);
        $this->assertDatabaseCount('order_payments', 1);
    }

    public function test_one_month_membership_starting_on_january_31_expires_on_march_1(): void
    {
        $this->travelTo('2026-01-31 09:00:00');
        $staff = User::factory()->employee()->create();
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
        ])->assertRedirect(route('staff.orders.show', $order));

        $membership = Membership::query()->where('membership_order_id', $order->id)->firstOrFail();
        $this->assertSame('2026-01-31', $membership->starts_on->toDateString());
        $this->assertSame('2026-03-01', $membership->expires_on->toDateString());
    }

    public function test_confirmation_preserves_the_ordered_duration_and_price_after_package_changes(): void
    {
        $this->travelTo('2026-10-21 09:00:00');
        $customer = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $staff = User::factory()->employee()->create();
        $package = GymPackage::factory()->create(['price' => 1200000, 'duration_months' => 12]);

        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 1200000, 'duration_months' => 12, 'start_date' => '2026-10-21', 'payment_method' => 'cash',
        ]);

        $this->actingAs($manager)->put(route('manager.packages.update', $package), [
            'name' => $package->name,
            'price' => 100000,
            'duration_months' => 1,
        ])->assertRedirect(route('manager.packages.index'));
        $this->actingAs($staff)->post(route('staff.orders.payments.store', $order), [
            'amount' => 1200000,
            'payment_method' => 'cash',
        ])->assertRedirect(route('staff.orders.show', $order));
        $this->post(route('staff.orders.confirm', $order), [
            'start_date' => '2026-10-21',
            'duration_months' => 1,
        ])->assertRedirect(route('staff.orders.show', $order));

        $this->assertDatabaseHas('membership_orders', [
            'id' => $order->id, 'amount' => 1200000, 'duration_months' => 12, 'status' => 'paid',
        ]);
        $this->assertDatabaseHas('memberships', [
            'membership_order_id' => $order->id, 'starts_on' => '2026-10-21', 'expires_on' => '2027-10-21',
        ]);
        $this->assertDatabaseCount('memberships', 1);
        $this->actingAs($customer)->followingRedirects()->get(route('my.orders.show', $order))
            ->assertSee('Thời hạn đã đặt: 12 tháng');
    }

    public function test_legacy_order_requires_verified_duration_before_confirmation(): void
    {
        $this->travelTo('2026-10-21 09:00:00');
        $staff = User::factory()->employee()->create();
        $order = $this->pendingOrder();
        $order->update(['duration_months' => null]);
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 399000]);

        $this->actingAs($staff)->get(route('staff.orders.index'))
            ->assertSee('Thời hạn đã thỏa thuận (tháng)')
            ->assertSee('name="duration_months"', false);
        $this->from(route('staff.orders.index'))->post(route('staff.orders.confirm', $order), [
            'start_date' => '2026-10-21',
        ])->assertSessionHasErrors([
            'duration_months' => 'Hãy xác minh và nhập thời hạn đã thỏa thuận của đơn cũ (1–65535 tháng).',
        ]);

        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'pending', 'duration_months' => null]);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('order_payments', 1);
    }

    public function test_staff_can_confirm_the_verified_duration_of_a_legacy_order(): void
    {
        $this->travelTo('2026-10-21 09:00:00');
        $staff = User::factory()->employee()->create();
        $order = $this->pendingOrder();
        $order->update(['duration_months' => null]);
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 399000]);

        $this->actingAs($staff)->post(route('staff.orders.confirm', $order), [
            'start_date' => '2026-10-21',
            'duration_months' => 12,
        ])->assertRedirect(route('staff.orders.show', $order));
        $this->post(route('staff.orders.confirm', $order), [
            'start_date' => '2026-10-21', 'duration_months' => 1,
        ])->assertRedirect(route('staff.orders.show', $order));

        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'duration_months' => 12, 'status' => 'paid']);
        $this->assertDatabaseHas('memberships', [
            'membership_order_id' => $order->id, 'expires_on' => '2027-10-21',
        ]);
        $this->assertDatabaseCount('memberships', 1);
    }

    #[TestWith([0])]
    #[TestWith([65536])]
    public function test_legacy_order_rejects_invalid_duration(int $months): void
    {
        $this->travelTo('2026-10-21 09:00:00');
        $staff = User::factory()->employee()->create();
        $order = $this->pendingOrder();
        $order->update(['duration_months' => null]);
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 399000]);

        $this->actingAs($staff)->post(route('staff.orders.confirm', $order), [
            'start_date' => '2026-10-21', 'duration_months' => $months,
        ])->assertSessionHasErrors('duration_months');

        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'pending', 'duration_months' => null]);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_staff_must_replace_a_past_or_overlapping_start_date_before_confirmation(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
        $staff = User::factory()->employee()->create();
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
            ->assertRedirect(route('staff.orders.show', $order));

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

        $this->post(route('staff.orders.confirm', $order), ['start_date' => now(config('app.timezone'))->toDateString()])
            ->assertForbidden();

        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_staff_cannot_cancel_an_order_after_receiving_any_money(): void
    {
        $staff = User::factory()->employee()->create();
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
            'start_date' => now(config('app.timezone'))->toDateString(),
            'status' => 'pending',
        ]);
    }
}
