<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalChromeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_keeps_the_marketing_carousel_but_management_pages_do_not(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('family=Be+Vietnam+Pro', false)
            ->assertSee('as="font"', false)
            ->assertDontSee('data-carousel-pause', false)
            ->assertSee('data-carousel-go', false)
            ->assertDontSee('data-carousel-prev', false)
            ->assertDontSee('data-carousel-next', false);

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('manager.packages.index'))
            ->assertOk()
            ->assertDontSee('data-carousel', false);
    }

    public function test_current_management_destination_is_identified_in_navigation(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('manager.packages.index'))
            ->assertOk()
            ->assertSee(route('manager.packages.index'), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('>Quản lý gói tập</a>', false);
    }

    public function test_customers_can_reach_the_cart_from_the_main_navigation(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('gym.cart').'"', false)
            ->assertSee('>Giỏ hàng</a>', false);
    }

    public function test_all_roles_use_a_consistent_account_menu_with_password_and_logout_actions(): void
    {
        $users = [
            User::factory()->create(),
            User::factory()->employee()->create(),
            User::factory()->manager()->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->get(route('home'))
                ->assertOk()
                ->assertSee('data-account-menu', false)
                ->assertSee('data-account-trigger', false)
                ->assertSee('Đổi mật khẩu')
                ->assertSee('Đăng xuất');
        }
    }

    public function test_registration_validation_is_attached_to_the_invalid_field(): void
    {
        $this->from(route('register'))->followingRedirects()->post(route('register.store'), [])
            ->assertOk()
            ->assertSee('id="name"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('href="#name"', false)
            ->assertSee('aria-describedby="name-error"', false)
            ->assertSee('id="name-error"', false);
    }
}
