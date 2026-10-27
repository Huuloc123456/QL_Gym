<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerEmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_lock_and_unlock_an_employee_account_with_audit_details(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($manager)->patch(route('manager.employees.toggle-active', $employee))
            ->assertRedirect(route('manager.employees.index'));

        $employee->refresh();
        $this->assertFalse($employee->is_active);
        $this->assertNotNull($employee->locked_at);
        $this->assertSame($manager->id, $employee->locked_by);

        $this->patch(route('manager.employees.toggle-active', $employee))
            ->assertRedirect(route('manager.employees.index'));

        $employee->refresh();
        $this->assertTrue($employee->is_active);
        $this->assertNull($employee->locked_at);
        $this->assertNull($employee->locked_by);
    }

    public function test_locked_employee_cannot_log_in_or_continue_an_existing_session(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create([
            'username' => 'locked-staff',
            'password' => 'StrongPass123!',
        ]);

        $this->actingAs($manager)->patch(route('manager.employees.toggle-active', $employee));
        auth()->logout();

        $this->post(route('login.store'), [
            'username' => 'locked-staff',
            'password' => 'StrongPass123!',
        ])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->actingAs($employee->refresh())->get(route('home'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_manager_cannot_lock_another_role_or_their_own_account(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $customer = User::factory()->create();

        $this->actingAs($manager)->patch(route('manager.employees.toggle-active', $manager))->assertForbidden();
        $this->patch(route('manager.employees.toggle-active', $customer))->assertForbidden();

        $this->assertTrue($manager->fresh()->is_active);
        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_authenticated_user_can_change_password_after_confirming_current_password(): void
    {
        $customer = User::factory()->create(['password' => 'CurrentPass123!']);
        $oldRememberToken = $customer->remember_token;
        DB::table('sessions')->insert(['id' => 'other-customer-session', 'user_id' => $customer->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($customer)->put(route('account.password.update'), [
            'current_password' => 'CurrentPass123!',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ])->assertRedirect(route('account.password.edit'));

        $this->assertTrue(Hash::check('NewSecurePass123!', $customer->fresh()->password));
        $this->assertFalse(Hash::check('CurrentPass123!', $customer->fresh()->password));
        $this->assertNotSame($oldRememberToken, $customer->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-customer-session']);
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $customer = User::factory()->create(['password' => 'CurrentPass123!']);

        $this->actingAs($customer)->from(route('account.password.edit'))
            ->put(route('account.password.update'), [
                'current_password' => 'WrongPass123!',
                'password' => 'NewSecurePass123!',
                'password_confirmation' => 'NewSecurePass123!',
            ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPass123!', $customer->fresh()->password));
    }
}
