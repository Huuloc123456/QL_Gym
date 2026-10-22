<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_login_page_does_not_offer_optional_email_otp(): void
    {
        config(['mail.default' => 'smtp']);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Xác minh thêm bằng mã email (khách hàng)');
    }
}
