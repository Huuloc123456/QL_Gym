<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffCheckInBrowseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function staff_can_search_visits_older_than_the_latest_fifty(): void
    {
        $staff = User::factory()->employee()->create();
        [$member, $membership] = $this->memberWithMembership('Old Member');

        foreach (range(1, 55) as $index) {
            CheckIn::factory()->for($member)->for($membership)->create([
                'checked_in_at' => Carbon::parse('2026-08-01 08:00:00')->addDays($index),
                'checked_out_at' => Carbon::parse('2026-08-01 09:00:00')->addDays($index),
            ]);
        }

        $this->actingAs($staff)
            ->get(route('staff.checkins.index', ['status' => 'history', 'q' => 'Old Member']))
            ->assertOk()
            ->assertViewHas('visits', function ($visits): bool {
                $this->assertInstanceOf(LengthAwarePaginator::class, $visits);
                $this->assertSame(55, $visits->total());
                $this->assertSame(20, $visits->count());

                return true;
            });
    }

    #[Test]
    public function staff_can_separate_open_visits_and_paginate_filtered_history(): void
    {
        $staff = User::factory()->employee()->create();
        [$member, $membership] = $this->memberWithMembership('Search Member');

        foreach (range(1, 22) as $index) {
            CheckIn::factory()->for($member)->for($membership)->create([
                'checked_in_at' => Carbon::parse('2026-10-15 08:00:00')->addMinutes($index),
                'checked_out_at' => Carbon::parse('2026-10-15 09:00:00')->addMinutes($index),
            ]);
        }

        CheckIn::factory()->for($member)->for($membership)->create([
            'checked_in_at' => Carbon::parse('2026-09-15 08:00:00'),
            'checked_out_at' => Carbon::parse('2026-09-15 09:00:00'),
        ]);
        $openVisit = CheckIn::factory()->for($member)->for($membership)->create([
            'checked_in_at' => Carbon::parse('2026-10-15 10:00:00'),
            'checked_out_at' => null,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.checkins.index'))
            ->assertOk()
            ->assertSee('Đang tập')
            ->assertSee('Ghi giờ ra')
            ->assertViewHas('visits', function ($visits) use ($openVisit): bool {
                $this->assertInstanceOf(LengthAwarePaginator::class, $visits);
                $this->assertSame(1, $visits->total());
                $this->assertSame($openVisit->id, $visits->first()->id);

                return true;
            });

        $this->get(route('staff.checkins.index', [
            'status' => 'history',
            'q' => 'Search Member',
            'from_date' => '2026-10-15',
            'to_date' => '2026-10-15',
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee('status=history', false)
            ->assertSee('q=Search%20Member', false)
            ->assertViewHas('visits', function ($visits): bool {
                $this->assertInstanceOf(LengthAwarePaginator::class, $visits);
                $this->assertSame(2, $visits->currentPage());
                $this->assertSame(22, $visits->total());
                $this->assertSame(2, $visits->count());

                return true;
            });
    }

    /** @return array{User, Membership} */
    private function memberWithMembership(string $name): array
    {
        $customer = User::factory()->create(['name' => $name]);
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create();
        $membership = Membership::factory()->for($customer)->for($package, 'gymPackage')->for($order, 'order')->create();

        return [$customer, $membership];
    }
}
