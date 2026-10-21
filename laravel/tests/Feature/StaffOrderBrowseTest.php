<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffOrderBrowseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function staff_can_switch_between_pending_history_and_all_orders_and_search(): void
    {
        $staff = User::factory()->employee()->create();
        $package = GymPackage::factory()->create();
        $pendingCustomer = User::factory()->create(['name' => 'Pending Customer']);
        $paidCustomer = User::factory()->create(['name' => 'History Customer']);
        $cancelledCustomer = User::factory()->create(['name' => 'Cancelled Customer']);
        $pending = MembershipOrder::factory()->for($pendingCustomer)->for($package, 'gymPackage')->create();
        $paid = MembershipOrder::factory()->for($paidCustomer)->for($package, 'gymPackage')->paid()->create();
        $cancelled = MembershipOrder::factory()->for($cancelledCustomer)->for($package, 'gymPackage')->create(['status' => 'cancelled']);

        $this->actingAs($staff)
            ->get(route('staff.orders.index'))
            ->assertOk()
            ->assertSee('Cần xử lý')
            ->assertSee('Đơn #'.$pending->id)
            ->assertDontSee('Đơn #'.$paid->id)
            ->assertDontSee('Đơn #'.$cancelled->id);

        $this->get(route('staff.orders.index', ['status' => 'history']))
            ->assertOk()
            ->assertSee('Đơn #'.$paid->id)
            ->assertSee('Đơn #'.$cancelled->id)
            ->assertDontSee('Đơn #'.$pending->id);

        $this->get(route('staff.orders.index', ['status' => 'all', 'q' => $paidCustomer->phone]))
            ->assertOk()
            ->assertSee('Đơn #'.$paid->id)
            ->assertDontSee('Đơn #'.$pending->id);

        $this->get(route('staff.orders.index', ['status' => 'all', 'q' => (string) $cancelled->id]))
            ->assertOk()
            ->assertSee('Đơn #'.$cancelled->id)
            ->assertDontSee('Đơn #'.$paid->id);
    }

    #[Test]
    public function order_filters_remain_when_staff_moves_to_the_next_page(): void
    {
        $staff = User::factory()->employee()->create();
        $package = GymPackage::factory()->create();
        $customer = User::factory()->create(['name' => 'Batch Customer']);

        foreach (range(1, 23) as $index) {
            MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
                'created_at' => Carbon::parse('2026-10-21 10:00:00')->addSeconds($index),
            ]);
        }

        $response = $this->actingAs($staff)
            ->get(route('staff.orders.index', ['status' => 'pending', 'q' => 'Batch Customer', 'page' => 2]));

        $response->assertOk()
            ->assertSee('status=pending', false)
            ->assertSee('q=Batch%20Customer', false)
            ->assertViewHas('orders', function ($orders): bool {
                $this->assertSame(2, $orders->currentPage());
                $this->assertSame(23, $orders->total());
                $this->assertSame(3, $orders->count());

                return true;
            });
    }

    #[Test]
    public function staff_can_filter_orders_by_creation_date(): void
    {
        $staff = User::factory()->employee()->create();
        $package = GymPackage::factory()->create();
        $customer = User::factory()->create();
        $inside = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'created_at' => Carbon::parse('2026-10-15 12:00:00'),
        ]);
        $outside = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'created_at' => Carbon::parse('2026-10-10 12:00:00'),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.orders.index', ['status' => 'all', 'from_date' => '2026-10-14', 'to_date' => '2026-10-16']))
            ->assertOk()
            ->assertSee('Đơn #'.$inside->id)
            ->assertDontSee('Đơn #'.$outside->id);
    }

    #[Test]
    public function staff_can_reach_an_order_older_than_one_hundred_results(): void
    {
        $staff = User::factory()->employee()->create();
        $package = GymPackage::factory()->create();
        $customer = User::factory()->create(['name' => 'Long History Customer']);
        $oldest = null;

        foreach (range(1, 101) as $index) {
            $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
                'created_at' => Carbon::parse('2026-10-01 00:00:00')->addMinutes($index),
            ]);
            $oldest ??= $order;
        }

        $this->actingAs($staff)
            ->get(route('staff.orders.index', ['status' => 'pending', 'q' => 'Long History Customer', 'page' => 6]))
            ->assertOk()
            ->assertSee('Đơn #'.$oldest->id)
            ->assertViewHas('orders', function ($orders): bool {
                $this->assertSame(6, $orders->currentPage());
                $this->assertSame(101, $orders->total());
                $this->assertSame(1, $orders->count());

                return true;
            });
    }
}
