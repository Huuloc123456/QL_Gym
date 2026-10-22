<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_a_customers_profile_and_history(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create();

        $this->actingAs($manager)
            ->get(route('gym.admin.customers.show', $customer->sql_customer_id))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee(route('gym.admin.index', 'customers'), false)
            ->assertSee('Khách hàng')
            ->assertSee('Lịch sử gói tập')
            ->assertSee('Đơn hàng và thanh toán')
            ->assertSee('Lịch sử check-in');
    }

    public function test_only_managers_can_open_customer_management_profiles(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('gym.admin.customers.show', $otherCustomer->sql_customer_id))
            ->assertForbidden();
    }

    public function test_customer_order_history_includes_legacy_package_orders(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create(['name' => 'Khách có đơn cũ']);
        $package = GymPackage::factory()->create(['name' => 'Gói dữ liệu cũ']);
        MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();

        $this->actingAs($manager)
            ->get(route('gym.admin.customers.show', $customer->sql_customer_id))
            ->assertOk()
            ->assertSee('Đơn gói #')
            ->assertSee('Gói dữ liệu cũ')
            ->assertViewHas('invoices', fn ($orders): bool => $orders->total() === 1);
    }
}
