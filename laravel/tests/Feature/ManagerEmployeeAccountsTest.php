<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerEmployeeAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_an_employee_account_without_assigning_another_role(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create(['name' => 'Khách không được hiện']);
        $employee = User::factory()->employee()->create(['name' => 'Nhân viên hiện có']);

        $this->actingAs($manager)->get(route('manager.employees.index'))
            ->assertOk()
            ->assertSee('Nhân viên hiện có')
            ->assertDontSee('Khách không được hiện');

        $this->get(route('manager.employees.create'))
            ->assertOk()
            ->assertSee('Tạo tài khoản nhân viên')
            ->assertDontSee('name="role"', false);

        $this->post(route('manager.employees.store'), [
            'name' => 'Nhân viên mới',
            'username' => 'nhanvienmoi',
            'email' => 'newemployee@example.test',
            'phone' => '0901234000',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => 'manager',
        ])->assertRedirect(route('manager.employees.index'));

        $createdEmployee = User::query()->where('username', 'nhanvienmoi')->firstOrFail();

        $this->assertSame('employee', $createdEmployee->role);
        $this->assertTrue(Hash::check('StrongPass123!', $createdEmployee->password));
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'role' => 'customer']);
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'role' => 'employee']);
    }

    public function test_manager_can_search_employee_accounts_and_keep_the_filter_when_paginating(): void
    {
        $manager = User::factory()->manager()->create();

        foreach (range(0, 11) as $number) {
            User::factory()->employee()->create(['name' => sprintf('QA Employee %02d', $number)]);
        }

        $this->actingAs($manager)->get(route('manager.employees.index', ['q' => 'QA Employee']))
            ->assertOk()
            ->assertSee('QA Employee 00')
            ->assertSee('QA Employee 09')
            ->assertDontSee('QA Employee 10')
            ->assertSee('q=QA')
            ->assertSee('page=2');

        $this->get(route('manager.employees.index', ['q' => 'QA Employee', 'page' => 2]))
            ->assertOk()
            ->assertSee('QA Employee 10')
            ->assertSee('QA Employee 11')
            ->assertDontSee('QA Employee 00');
    }

    public function test_manager_can_edit_an_employee_and_leave_the_password_unchanged(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'name' => 'Tên cũ',
            'password' => 'OldPass123!',
        ]);

        $this->actingAs($manager)->get(route('manager.employees.edit', $employee))
            ->assertOk()
            ->assertSee('Sửa tài khoản nhân viên');

        $this->put(route('manager.employees.update', $employee), [
            'name' => 'Tên mới',
            'username' => $employee->username,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'password' => '',
            'password_confirmation' => '',
            'role' => 'manager',
        ])->assertRedirect(route('manager.employees.index'));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Tên mới',
            'role' => 'employee',
        ]);
        $this->assertTrue(Hash::check('OldPass123!', $employee->fresh()->password));

        $this->put(route('manager.employees.update', $employee), [
            'name' => 'Tên mới',
            'username' => $employee->username,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertRedirect(route('manager.employees.index'));

        $this->assertTrue(Hash::check('NewPass123!', $employee->fresh()->password));
    }

    public function test_employee_account_forms_validate_unique_identity_and_password_confirmation(): void
    {
        $manager = User::factory()->manager()->create();
        $existingEmployee = User::factory()->employee()->create();

        $this->actingAs($manager)->from(route('manager.employees.create'))
            ->post(route('manager.employees.store'), [
                'name' => 'Trùng tài khoản',
                'username' => $existingEmployee->username,
                'email' => $existingEmployee->email,
                'phone' => $existingEmployee->phone,
                'password' => 'StrongPass123!',
                'password_confirmation' => 'WrongPass123!',
            ])
            ->assertSessionHasErrors(['username', 'email', 'phone', 'password']);

        $this->assertDatabaseMissing('users', ['name' => 'Trùng tài khoản']);
    }

    public function test_only_managers_can_manage_employee_accounts_and_cannot_edit_other_roles(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->get(route('manager.employees.index'))->assertRedirect(route('login'));

        foreach ([$employee, $customer] as $nonManager) {
            $this->actingAs($nonManager)->get(route('manager.employees.index'))->assertForbidden();
            $this->post(route('manager.employees.store'), [])->assertForbidden();
        }

        $this->actingAs($manager)->get(route('manager.employees.edit', $customer))->assertForbidden();
        $this->assertTrue(Gate::forUser($manager)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($manager)->allows('update', $employee));
        $this->assertFalse(Gate::forUser($employee)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $customer));
    }
}
