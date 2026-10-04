<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_check_in_a_customer_without_a_paid_membership(): void
    {
        $this->travelTo('2026-10-02 09:00:00');
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($staff)->from(route('staff.checkins.index'))
            ->post(route('staff.checkins.store'), ['member' => $customer->phone]);

        $response->assertSessionHasErrors('member');
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_staff_cannot_check_in_before_the_membership_starts(): void
    {
        $this->travelTo('2026-10-02 09:00:00');
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);
        $membership = $this->membershipFor($customer, '2026-10-10', '2026-11-10');

        $response = $this->actingAs($staff)->from(route('staff.checkins.index'))
            ->post(route('staff.checkins.store'), ['member' => $customer->phone]);

        $response->assertSessionHasErrors('member');
        $this->assertDatabaseCount('check_ins', 0);
        $this->assertModelExists($membership);
    }

    public function test_staff_cannot_check_in_after_the_membership_expires(): void
    {
        $this->travelTo('2026-11-01 00:00:00');
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);
        $this->membershipFor($customer, '2026-10-01', '2026-11-01');

        $this->actingAs($staff)->from(route('staff.checkins.index'))
            ->post(route('staff.checkins.store'), ['member' => $customer->phone])
            ->assertSessionHasErrors('member');

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_active_member_gets_one_open_check_in_and_cannot_enter_twice(): void
    {
        $this->travelTo('2026-10-02 09:00:00');
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);
        $membership = $this->membershipFor($customer, '2026-10-01', '2026-11-01');

        $this->actingAs($staff)->post(route('staff.checkins.store'), ['member' => $customer->phone])
            ->assertRedirect(route('staff.checkins.index'));

        $this->assertDatabaseHas('check_ins', [
            'user_id' => $customer->id,
            'membership_id' => $membership->id,
            'checked_out_at' => null,
        ]);

        $this->post(route('staff.checkins.store'), ['member' => $customer->phone])
            ->assertSessionHasErrors('member');

        $this->assertDatabaseCount('check_ins', 1);
    }

    public function test_staff_can_check_out_and_customer_cannot_change_attendance(): void
    {
        $this->travelTo('2026-10-02 09:00:00');
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);
        $membership = $this->membershipFor($customer, '2026-10-01', '2026-11-01');
        $visit = CheckIn::factory()->for($customer)->for($membership)->create([
            'checked_in_at' => '2026-10-02 08:00:00',
        ]);

        $this->actingAs($customer)->post(route('staff.checkins.checkout', $visit))->assertForbidden();
        $this->actingAs($staff)->post(route('staff.checkins.checkout', $visit))
            ->assertRedirect(route('staff.checkins.index'));

        $this->assertDatabaseHas('check_ins', [
            'id' => $visit->id,
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_manual_close_of_a_forgotten_visit_requires_a_reason(): void
    {
        $this->travelTo('2026-10-02 12:00:00');
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);
        $membership = $this->membershipFor($customer, '2026-10-01', '2026-11-01');
        $visit = CheckIn::factory()->for($customer)->for($membership)->create([
            'checked_in_at' => '2026-10-02 08:00:00',
        ]);

        $this->actingAs($staff)->from(route('staff.checkins.index'))
            ->post(route('staff.checkins.manual-close', $visit))
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('check_ins', ['id' => $visit->id, 'checked_out_at' => null]);

        $this->post(route('staff.checkins.manual-close', $visit), ['reason' => 'Khách quên xác nhận ra quầy'])
            ->assertRedirect(route('staff.checkins.index'));

        $this->assertDatabaseHas('check_ins', [
            'id' => $visit->id,
            'manual_close_reason' => 'Khách quên xác nhận ra quầy',
            'closed_by_user_id' => $staff->id,
            'checked_out_at' => '2026-10-02 12:00:00',
        ]);
    }

    public function test_staff_cannot_close_an_already_closed_visit_again(): void
    {
        $this->travelTo('2026-10-02 12:00:00');
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = User::factory()->create(['role' => 'customer']);
        $membership = $this->membershipFor($customer, '2026-10-01', '2026-11-01');
        $visit = CheckIn::factory()->for($customer)->for($membership)->create([
            'checked_in_at' => '2026-10-02 08:00:00',
            'checked_out_at' => '2026-10-02 09:00:00',
        ]);

        $this->actingAs($staff)->from(route('staff.checkins.index'))
            ->post(route('staff.checkins.checkout', $visit))
            ->assertForbidden();

        $this->assertDatabaseHas('check_ins', [
            'id' => $visit->id,
            'checked_out_at' => '2026-10-02 09:00:00',
        ]);
    }

    private function membershipFor(User $customer, string $startsOn, string $expiresOn): Membership
    {
        $package = GymPackage::factory()->create(['duration_months' => 1]);
        $paidOrder = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 399000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return Membership::factory()->for($customer)->for($package, 'gymPackage')->for($paidOrder, 'order')->create([
            'starts_on' => $startsOn,
            'expires_on' => $expiresOn,
        ]);
    }
}
