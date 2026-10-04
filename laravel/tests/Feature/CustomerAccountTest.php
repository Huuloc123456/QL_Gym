<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_logs_customer_in_without_email_verification(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Nguyen Van An',
            'phone' => '0901234567',
            'email' => 'an@example.test',
            'username' => 'nguyenvanan',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertRedirect(route('my.memberships.index'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'nguyenvanan',
            'phone' => '0901234567',
            'email_verified_at' => null,
            'role' => 'customer',
        ]);
    }

    public function test_customer_login_uses_username_and_password(): void
    {
        $customer = User::factory()->create([
            'name' => 'Nguyen Van An',
            'username' => 'gymmember',
            'password' => 'StrongPass123!',
            'role' => 'customer',
        ]);

        $response = $this->post(route('login.store'), [
            'username' => 'gymmember',
            'password' => 'StrongPass123!',
        ]);

        $response->assertRedirect(route('my.memberships.index'));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_manager_login_redirects_to_manager_dashboard_even_if_member_url_was_intended(): void
    {
        $manager = User::factory()->create([
            'username' => 'quanly',
            'password' => 'GymDemo@2026',
            'role' => 'manager',
        ]);

        $this->get(route('my.memberships.index'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'username' => 'quanly',
            'password' => 'GymDemo@2026',
        ])->assertRedirect(route('manager.dashboard'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_employee_login_redirects_to_staff_orders(): void
    {
        User::factory()->create([
            'username' => 'nhanvien',
            'password' => 'GymDemo@2026',
            'role' => 'employee',
        ]);

        $this->post(route('login.store'), [
            'username' => 'nhanvien',
            'password' => 'GymDemo@2026',
        ])->assertRedirect(route('staff.orders.index'));
    }

    public function test_repeated_invalid_login_attempts_are_throttled(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'username' => 'unknown-member',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $this->post(route('login.store'), [
            'username' => 'unknown-member',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_customer_can_update_profile_fields_but_not_login_or_email(): void
    {
        $customer = User::factory()->create([
            'name' => 'Nguyen Van An',
            'username' => 'gymmember',
            'email' => 'an@example.test',
            'phone' => '0901234567',
            'role' => 'customer',
        ]);

        $this->actingAs($customer)->put(route('my.profile.update'), [
            'name' => 'Nguyen An Updated',
            'phone' => '0907654321',
            'birth_date' => '2001-04-05',
            'gender' => 'male',
            'username' => 'someone-else',
            'email' => 'other@example.test',
        ])->assertSessionHasErrors(['username', 'email']);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Nguyen Van An',
            'phone' => '0901234567',
            'username' => 'gymmember',
            'email' => 'an@example.test',
        ]);
    }

    public function test_customer_can_update_allowed_profile_fields(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->put(route('my.profile.update'), [
            'name' => 'Nguyen An Updated',
            'phone' => '0907654321',
            'birth_date' => '2001-04-05',
            'gender' => 'male',
        ])->assertRedirect(route('my.profile.show'));

        $customer->refresh();
        $this->assertSame('Nguyen An Updated', $customer->name);
        $this->assertSame('0907654321', $customer->phone);
        $this->assertSame('2001-04-05', $customer->birth_date->toDateString());
        $this->assertSame('male', $customer->gender);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create();
        $otherOrder = MembershipOrder::factory()->for($otherCustomer)->for($package, 'gymPackage')->create();

        $this->actingAs($owner)->get(route('my.orders.show', $otherOrder))->assertNotFound();
    }

    public function test_staff_pages_require_an_employee_or_manager_role(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('staff.orders.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('manager.dashboard'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_account_pages(): void
    {
        $this->get(route('my.memberships.index'))->assertRedirect(route('login'));
        $this->get(route('staff.checkins.index'))->assertRedirect(route('login'));
    }
}
