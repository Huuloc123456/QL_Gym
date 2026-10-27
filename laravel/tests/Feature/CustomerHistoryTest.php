<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerHistoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_can_filter_their_orders_and_reach_the_last_page(): void
    {
        $customer = User::factory()->create(['name' => 'My Orders']);
        $otherCustomer = User::factory()->create(['name' => 'Other Orders']);
        $package = GymPackage::factory()->create();

        foreach (range(1, 21) as $index) {
            MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
                'created_at' => Carbon::parse('2026-10-01 09:00:00')->addMinutes($index),
            ]);
        }
        $paidOrder = MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create();
        $otherOrder = MembershipOrder::factory()->paid()->for($otherCustomer)->for($package, 'gymPackage')->create();

        $this->actingAs($customer)
            ->get(route('my.orders.index', ['status' => 'pending', 'page' => 2]))
            ->assertOk()
            ->assertSee('status=pending', false)
            ->assertViewHas('orders', function ($orders): bool {
                $this->assertInstanceOf(LengthAwarePaginator::class, $orders);
                $this->assertSame(2, $orders->currentPage());
                $this->assertSame(21, $orders->total());
                $this->assertSame(1, $orders->count());

                return true;
            });

        $this->get(route('my.orders.index', ['status' => 'paid']))
            ->assertOk()
            ->assertSee('Gói hội viên')
            ->assertViewHas('orders', function ($orders) use ($paidOrder, $otherOrder): bool {
                $this->assertSame(1, $orders->total());
                $this->assertTrue($orders->contains('id', $paidOrder->id));
                $this->assertFalse($orders->contains('id', $otherOrder->id));

                return true;
            });
    }

    #[Test]
    public function customer_memberships_are_grouped_and_old_memberships_are_paginated(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'Asia/Ho_Chi_Minh'));
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();

        foreach (range(1, 21) as $index) {
            $this->createMembership($customer, $package, '2026-0'.max(1, min(9, 9 - (int) ceil($index / 3))).'-01', '2026-09-10');
        }
        $active = $this->createMembership($customer, $package, '2026-10-01', '2026-11-01');
        $upcoming = $this->createMembership($customer, $package, '2026-12-01', '2027-01-01');

        $this->actingAs($customer)
            ->get(route('my.memberships.index', ['history_page' => 2]))
            ->assertOk()
            ->assertSee('Đang hiệu lực')
            ->assertSee('Chờ hiệu lực')
            ->assertViewHas('activeMemberships', function ($memberships) use ($active): bool {
                $this->assertInstanceOf(LengthAwarePaginator::class, $memberships);
                $this->assertSame(1, $memberships->total());
                $this->assertSame($active->id, $memberships->first()->id);

                return true;
            })
            ->assertViewHas('upcomingMemberships', function ($memberships) use ($upcoming): bool {
                $this->assertInstanceOf(LengthAwarePaginator::class, $memberships);
                $this->assertSame(1, $memberships->total());
                $this->assertSame($upcoming->id, $memberships->first()->id);

                return true;
            })
            ->assertViewHas('expiredMemberships', function ($memberships): bool {
                $this->assertInstanceOf(LengthAwarePaginator::class, $memberships);
                $this->assertSame(21, $memberships->total());
                $this->assertSame(10, $memberships->count());
                $this->assertSame(2, $memberships->currentPage());

                return true;
            });
    }

    #[Test]
    public function expired_sql_membership_mirror_remains_in_customer_history(): void
    {
        $this->travelTo(Carbon::parse('2026-10-27 12:00:00', 'Asia/Ho_Chi_Minh'));
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $membership = $this->createMembership($customer, $package, '2026-08-01', '2026-09-01');
        $membership->forceFill(['sql_membership_id' => 9908])->saveQuietly();
        DB::table('DANGKYGOITAP')->insert([
            'MADKGT' => 9908,
            'MAKH' => $customer->sql_customer_id,
            'MAGOITAP' => DB::table('GOITAP')->value('MAGOITAP'),
            'NGAYDANGKY' => '2026-08-01 00:00:00',
            'NGAYBATDAU' => '2026-08-01 00:00:00',
            'NGAYKETTHUC' => '2026-08-31 23:59:59',
            'TRANGTHAI' => 'Hết hạn',
        ]);

        $this->actingAs($customer)
            ->get(route('my.memberships.index'))
            ->assertOk()
            ->assertViewHas('expiredMemberships', function ($memberships) use ($membership): bool {
                $this->assertTrue($memberships->getCollection()->contains('id', $membership->id));

                return true;
            });
    }

    #[Test]
    public function customer_cannot_view_another_customers_order(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $otherOrder = MembershipOrder::factory()->for($otherCustomer)->for($package, 'gymPackage')->create();

        $this->actingAs($customer)
            ->get(route('my.orders.show', $otherOrder))
            ->assertNotFound();
    }

    private function createMembership(User $customer, GymPackage $package, string $startsOn, string $expiresOn): Membership
    {
        $order = MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create();

        return Membership::factory()->for($customer)->for($package, 'gymPackage')->for($order, 'order')->create([
            'starts_on' => $startsOn,
            'expires_on' => $expiresOn,
        ]);
    }
}
