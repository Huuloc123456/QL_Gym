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
            ->assertSee('data-carousel', false);

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
