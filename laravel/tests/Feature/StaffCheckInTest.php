<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use App\Services\MembershipPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_check_in_a_customer_without_a_paid_membership(): void
    {
        $this->travelTo('2026-10-02 09:00:00');
        $staff = User::factory()->employee()->create();
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($staff)->from(route('staff.checkins.index'))
            ->post(route('staff.checkins.store'), ['member' => $customer->phone]);

        $response->assertSessionHasErrors('member');
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_staff_cannot_check_in_before_the_membership_starts(): void
    {
        $this->travelTo('2026-10-02 09:00:00');
        $staff = User::factory()->employee()->create();
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
        $staff = User::factory()->employee()->create();
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
        $staff = User::factory()->employee()->create();
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
        $staff = User::factory()->employee()->create();
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
        $staff = User::factory()->employee()->create();
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
        $staff = User::factory()->employee()->create();
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

    public function test_an_old_open_visit_remains_actionable_while_closed_history_is_paginated(): void
    {
        $this->travelTo('2026-10-21 12:00:00');
        $staff = User::factory()->employee()->create();
        $customer = User::factory()->create();
        $membership = $this->membershipFor($customer, '2026-10-01', '2026-11-01');
        $oldVisit = CheckIn::factory()->for($customer)->for($membership)->create([
            'checked_in_at' => '2026-10-01 08:00:00',
        ]);
        CheckIn::factory()->count(51)->for($customer)->for($membership)->create([
            'checked_in_at' => '2026-10-20 08:00:00', 'checked_out_at' => '2026-10-20 09:00:00',
        ]);

        $this->actingAs($staff)->get(route('staff.checkins.index', ['status' => 'history']))
            ->assertSee(route('staff.checkins.checkout', $oldVisit), false)
            ->assertSee(route('staff.checkins.manual-close', $oldVisit), false)
            ->assertViewHas('openVisits', fn ($visits): bool => $visits->count() === 1 && $visits->first()->is($oldVisit))
            ->assertViewHas('visits', fn ($visits): bool => $visits->total() === 51 && $visits->count() === 20);
        $this->get(route('staff.checkins.index', ['status' => 'history', 'page' => 2]))
            ->assertSee(route('staff.checkins.checkout', $oldVisit), false)
            ->assertViewHas('visits', fn ($visits): bool => $visits->count() === 20);
        $this->post(route('staff.checkins.manual-close', $oldVisit), [
            'reason' => 'Đã đối chiếu lượt quên ghi giờ ra.',
        ])->assertRedirect(route('staff.checkins.index'));

        $this->assertDatabaseHas('check_ins', [
            'id' => $oldVisit->id, 'checked_out_at' => '2026-10-21 12:00:00',
            'closed_by_user_id' => $staff->id, 'manual_close_reason' => 'Đã đối chiếu lượt quên ghi giờ ra.',
        ]);
    }

    public function test_month_end_member_can_train_on_february_28_but_not_march_1(): void
    {
        $this->travelTo('2026-02-28 23:59:00');
        $staff = User::factory()->employee()->create();
        $customer = User::factory()->create();
        $expiry = app(MembershipPeriod::class)->expiresOn('2026-01-31', 1);
        $this->membershipFor($customer, '2026-01-31', $expiry->toDateString());

        $this->actingAs($staff)->post(route('staff.checkins.store'), ['member' => $customer->phone])
            ->assertRedirect(route('staff.checkins.index'));
        $visit = CheckIn::query()->where('user_id', $customer->id)->firstOrFail();
        $this->post(route('staff.checkins.checkout', $visit))->assertRedirect(route('staff.checkins.index'));
        $this->travelTo('2026-03-01 00:00:00');
        $this->post(route('staff.checkins.store'), ['member' => $customer->phone])
            ->assertSessionHasErrors('member');

        $this->assertDatabaseCount('check_ins', 1);
        $this->assertDatabaseHas('check_ins', ['id' => $visit->id, 'checked_out_at' => '2026-02-28 23:59:00']);
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
