<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_forgot_password_form(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Khôi phục mật khẩu')
            ->assertSee('name="email"', false);
    }

    public function test_reset_link_request_sends_email_without_disclosing_unknown_addresses(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['email' => 'member@example.test']);
        $message = 'Nếu email tồn tại trong hệ thống, hướng dẫn khôi phục đã được gửi.';

        $this->post(route('password.email'), ['email' => $customer->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success', $message);

        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success', $message);

        $resetUrl = null;
        Notification::assertSentTo($customer, ResetPassword::class, function (ResetPassword $notification) use ($customer, &$resetUrl): bool {
            $resetUrl = $notification->toMail($customer)->actionUrl;

            return true;
        });
        Notification::assertCount(1);
        $this->assertSame(route('password.reset', [
            'token' => Notification::sent($customer, ResetPassword::class)->first()->token,
            'email' => $customer->email,
        ]), $resetUrl);
    }

    public function test_valid_reset_token_changes_the_password_and_can_only_be_used_once(): void
    {
        $customer = User::factory()->create(['password' => 'OldPassword123!']);
        $token = Password::createToken($customer);
        $credentials = [
            'email' => $customer->email,
            'token' => $token,
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ];

        $this->get(route('password.reset', ['token' => $token, 'email' => $customer->email]))
            ->assertOk()
            ->assertSee('Đặt mật khẩu mới');

        $this->from(route('password.reset', ['token' => $token, 'email' => $customer->email]))
            ->post(route('password.update'), [
                'email' => $customer->email,
                'token' => $token,
                'password' => 'NewPassword456!',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrors('password');

        $this->post(route('password.update'), $credentials)
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $customer->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $customer->password));
        $this->assertFalse(Hash::check('OldPassword123!', $customer->password));
        $this->assertGuest();

        $this->from(route('password.reset', ['token' => $token, 'email' => $customer->email]))
            ->post(route('password.update'), $credentials)
            ->assertSessionHasErrors('email');
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $customer = User::factory()->create();
        $token = Password::createToken($customer);
        $this->travel(61)->minutes();

        $this->from(route('password.reset', ['token' => $token, 'email' => $customer->email]))
            ->post(route('password.update'), [
                'email' => $customer->email,
                'token' => $token,
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('NewPassword456!', $customer->fresh()->password));
    }
}
