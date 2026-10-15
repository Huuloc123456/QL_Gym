<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\TrialRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_change_permissions_for_an_employee(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($manager)->get(route('manager.employees.permissions.edit', $employee))
            ->assertOk()
            ->assertSee('Tiếp nhận tập thử')
            ->assertSee('Đơn hàng và thanh toán')
            ->assertSee('Check-in');

        $this->put(route('manager.employees.permissions.update', $employee), [
            'can_manage_trial_requests' => '0',
            'can_manage_orders' => '1',
            'can_manage_check_ins' => '0',
            'role' => 'manager',
        ])->assertRedirect(route('manager.employees.index'));

        $employee->refresh();
        $this->assertSame('employee', $employee->role);
        $this->assertFalse($employee->can_manage_trial_requests);
        $this->assertTrue($employee->can_manage_orders);
        $this->assertFalse($employee->can_manage_check_ins);
    }

    public function test_only_a_manager_can_change_permissions_for_employee_accounts(): void
    {
        $employee = User::factory()->employee()->create();
        $customer = User::factory()->create(['role' => 'customer']);
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $customer] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get(route('manager.employees.permissions.edit', $employee))
                ->assertForbidden();
            $this->put(route('manager.employees.permissions.update', $employee), [])->assertForbidden();
        }

        $this->actingAs($manager)
            ->get(route('manager.employees.permissions.edit', $customer))
            ->assertForbidden();
        $this->put(route('manager.employees.permissions.update', $manager), [])->assertForbidden();
    }

    public function test_disabled_employee_permissions_block_every_staff_module_route(): void
    {
        $employee = User::factory()->employee()->create([
            'can_manage_trial_requests' => false,
            'can_manage_orders' => false,
            'can_manage_check_ins' => false,
        ]);
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();
        $membership = Membership::factory()->for($customer)->for($package, 'gymPackage')->create();
        $visit = CheckIn::factory()->for($customer)->for($membership)->create();
        $trialRequest = TrialRequest::factory()->create();

        $this->actingAs($employee)->get(route('staff.trial-requests.index'))->assertForbidden();
        $this->post(route('staff.trial-requests.contact', $trialRequest))->assertForbidden();
        $this->get(route('staff.orders.index'))->assertForbidden();
        $this->post(route('staff.orders.payments.store', $order), [])->assertForbidden();
        $this->post(route('staff.orders.confirm', $order), [])->assertForbidden();
        $this->post(route('staff.orders.cancel', $order))->assertForbidden();
        $this->get(route('staff.checkins.index'))->assertForbidden();
        $this->post(route('staff.checkins.store'), [])->assertForbidden();
        $this->post(route('staff.checkins.checkout', $visit))->assertForbidden();
        $this->post(route('staff.checkins.manual-close', $visit), [])->assertForbidden();
    }

    public function test_employee_can_access_only_the_modules_that_were_enabled(): void
    {
        $employee = User::factory()->employee()->create([
            'can_manage_trial_requests' => false,
            'can_manage_orders' => true,
            'can_manage_check_ins' => false,
        ]);

        $this->actingAs($employee)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee(route('staff.orders.index'), false)
            ->assertDontSee(route('staff.trial-requests.index'), false)
            ->assertDontSee(route('staff.checkins.index'), false);

        $this->get(route('staff.orders.index'))->assertOk();
        $this->get(route('staff.trial-requests.index'))->assertForbidden();
        $this->get(route('staff.checkins.index'))->assertForbidden();
    }

    public function test_permission_update_requires_all_three_boolean_choices(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($manager)->put(route('manager.employees.permissions.update', $employee), [
            'can_manage_orders' => 'not-a-boolean',
        ])->assertSessionHasErrors([
            'can_manage_trial_requests',
            'can_manage_orders',
            'can_manage_check_ins',
        ]);

        $this->assertTrue($employee->fresh()->can_manage_trial_requests);
        $this->assertTrue($employee->fresh()->can_manage_orders);
        $this->assertTrue($employee->fresh()->can_manage_check_ins);
    }

    public function test_manager_keeps_staff_access_regardless_of_employee_permission_flags(): void
    {
        $manager = User::factory()->manager()->create([
            'can_manage_trial_requests' => false,
            'can_manage_orders' => false,
            'can_manage_check_ins' => false,
        ]);

        $this->actingAs($manager)->get(route('staff.trial-requests.index'))->assertOk();
        $this->get(route('staff.orders.index'))->assertOk();
        $this->get(route('staff.checkins.index'))->assertOk();
    }

    public function test_employee_without_permissions_lands_on_a_helpful_staff_page(): void
    {
        $employee = User::factory()->employee()->create([
            'can_manage_trial_requests' => false,
            'can_manage_orders' => false,
            'can_manage_check_ins' => false,
        ]);

        $this->actingAs($employee)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Chưa được cấp quyền nghiệp vụ')
            ->assertDontSee(route('staff.trial-requests.index'), false)
            ->assertDontSee(route('staff.orders.index'), false)
            ->assertDontSee(route('staff.checkins.index'), false);
    }

    public function test_new_employee_starts_without_permissions_and_is_sent_to_permission_setup(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager)->post(route('manager.employees.store'), [
            'name' => 'Nhân viên mới',
            'username' => 'staff_without_access',
            'email' => 'staff-without-access@example.test',
            'phone' => '0901234555',
            'password' => 'GymPassword123!',
            'password_confirmation' => 'GymPassword123!',
        ]);

        $employee = User::query()->where('username', 'staff_without_access')->firstOrFail();
        $response->assertRedirect(route('manager.employees.permissions.edit', $employee));
        $this->assertFalse($employee->can_manage_trial_requests);
        $this->assertFalse($employee->can_manage_orders);
        $this->assertFalse($employee->can_manage_check_ins);
    }
}
