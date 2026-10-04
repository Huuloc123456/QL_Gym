<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_a_gym_package_detail(): void
    {
        $package = GymPackage::factory()->create(['name' => 'Gói Sức Bền']);

        $this->get(route('packages.index'))->assertOk();
        $this->get(route('packages.show', $package))
            ->assertOk()
            ->assertSee('Gói Sức Bền')
            ->assertSee('Ngày tập cuối dự kiến');
    }

    public function test_application_shell_has_skip_navigation_and_a_mobile_menu_control(): void
    {
        $this->get(route('home'))
            ->assertSee('href="#main"', false)
            ->assertSee('data-menu-toggle', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('<main id="main"', false);
    }

    public function test_customer_can_open_their_attendance_history(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create(['name' => 'Gói Của Tôi']);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->paid()->create();
        $membership = Membership::factory()->for($customer)->for($package, 'gymPackage')->for($order, 'order')->create();
        $visit = CheckIn::factory()->for($customer)->for($membership)->create();
        $otherPackage = GymPackage::factory()->create(['name' => 'Gói Riêng']);
        $otherOrder = MembershipOrder::factory()->for($otherCustomer)->for($otherPackage, 'gymPackage')->paid()->create();
        $otherMembership = Membership::factory()->for($otherCustomer)->for($otherPackage, 'gymPackage')->for($otherOrder, 'order')->create();
        CheckIn::factory()->for($otherCustomer)->for($otherMembership)->create();

        $this->actingAs($customer)->get(route('my.attendance.index'))
            ->assertOk()
            ->assertSee('Gói Của Tôi')
            ->assertDontSee('Gói Riêng');
    }

    public function test_customer_membership_order_and_profile_pages_render(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $staff = User::factory()->employee()->create();
        $package = GymPackage::factory()->create();
        $paidOrder = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->paid()->create();
        Membership::factory()->for($customer)->for($package, 'gymPackage')->for($paidOrder, 'order')->create();
        $pendingOrder = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();
        OrderPayment::factory()->for($pendingOrder, 'order')->for($staff, 'receiver')->create(['amount' => 50000]);

        $this->actingAs($customer)->get(route('my.memberships.index'))->assertOk();
        $this->get(route('my.orders.index'))->assertOk();
        $this->get(route('my.orders.show', $pendingOrder))->assertOk();
        $this->get(route('my.profile.show'))->assertOk();
    }

    public function test_manager_dashboard_shows_paid_revenue_and_packages(): void
    {
        $manager = User::factory()->manager()->create();
        $package = GymPackage::factory()->create(['name' => 'Gói Sức Bền']);
        $customer = User::factory()->create(['role' => 'customer']);
        $paidOrder = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->paid()->create([
            'amount' => 750000,
        ]);
        MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create(['amount' => 200000]);
        OrderPayment::factory()->for($paidOrder, 'order')->for($manager, 'receiver')->create(['amount' => 750000]);

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('750.000 VNĐ')
            ->assertSee('Gói Sức Bền');
    }

    public function test_guest_login_form_remembers_the_selected_package(): void
    {
        $package = GymPackage::factory()->create();

        $this->get(route('login', ['package_id' => $package->id]))
            ->assertOk()
            ->assertSee('name="package_id"', false)
            ->assertSee('value="'.$package->id.'"', false);
    }

    public function test_login_returns_a_member_to_the_package_they_selected(): void
    {
        $package = GymPackage::factory()->create();
        $customer = User::factory()->create([
            'username' => 'returnmember',
            'password' => 'StrongPass123!',
            'role' => 'customer',
        ]);

        $this->post(route('login.store'), [
            'username' => $customer->username,
            'password' => 'StrongPass123!',
            'package_id' => $package->id,
        ])->assertRedirect(route('packages.show', $package));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_customer_can_request_cancellation_without_hiding_received_payments(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();
        $staff = User::factory()->employee()->create();
        OrderPayment::factory()->for($order, 'order')->for($staff, 'receiver')->create(['amount' => 50000]);

        $this->actingAs($customer)->post(route('my.orders.cancel-request', $order))
            ->assertRedirect(route('my.orders.index'));

        $this->assertDatabaseHas('membership_orders', [
            'id' => $order->id,
            'status' => MembershipOrder::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('order_payments', ['membership_order_id' => $order->id, 'amount' => 50000]);
        $this->assertNotNull($order->fresh()->cancellation_requested_at);
    }

    public function test_staff_order_and_check_in_screens_render(): void
    {
        $staff = User::factory()->employee()->create();

        $this->actingAs($staff)->get(route('staff.orders.index'))->assertOk();
        $this->get(route('staff.checkins.index'))->assertOk();
    }

    public function test_local_seed_provisions_manager_and_employee_accounts(): void
    {
        config(['app.env' => 'local']);
        $this->seed();

        $this->assertDatabaseHas('users', ['username' => 'quanly', 'role' => 'manager']);
        $this->assertDatabaseHas('users', ['username' => 'nhanvien', 'role' => 'employee']);
    }
}
