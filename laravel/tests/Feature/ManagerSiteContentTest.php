<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\GymSetting;
use App\Models\HeroBanner;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagerSiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_change_public_gym_information_and_faqs(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->put(route('manager.settings.update'), [
            'gym_name' => 'The Gym Riverside',
            'phone' => '0901234567',
            'email' => 'hello@example.test',
            'address' => '12 Riverside Road',
            'opening_hours' => '06:00–22:00',
            'introduction' => 'Tập luyện phù hợp với mục tiêu của bạn.',
            'bank_name' => 'ACB',
            'bank_code' => 'acb',
            'bank_account' => '123456789',
            'bank_account_name' => 'THE GYM',
            'faq_items' => [
                ['question' => 'Gói tập tính hạn thế nào?', 'answer' => 'Theo tháng lịch kể từ ngày bắt đầu.'],
            ],
        ])->assertRedirect(route('manager.settings.edit'));

        $this->assertDatabaseHas('gym_settings', ['id' => 1, 'bank_code' => 'acb']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('The Gym Riverside')
            ->assertSee('Tập luyện phù hợp với mục tiêu của bạn.')
            ->assertSee('Gói tập tính hạn thế nào?')
            ->assertSee('Theo tháng lịch kể từ ngày bắt đầu.');
    }

    public function test_pending_bank_transfer_order_shows_only_configured_payment_details(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'payment_method' => 'bank_transfer',
        ]);
        GymSetting::current()->update([
            'bank_name' => 'ACB',
            'bank_code' => 'acb',
            'bank_account' => '123456789',
            'bank_account_name' => 'THE GYM',
        ]);
        $this->assertSame('bank_transfer', $order->fresh()->payment_method);
        $this->assertSame('123456789', GymSetting::query()->findOrFail(1)->bank_account);

        $this->actingAs($customer)->get(route('my.orders.show', $order))
            ->assertOk()
            ->assertSee('ACB')
            ->assertSee('123456789')
            ->assertSee('THE GYM')
            ->assertSee('GYM-'.$order->id)
            ->assertSee('img.vietqr.io/image/acb-123456789-compact2.png', false);
    }

    public function test_manager_can_upload_and_publish_a_hero_banner(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post(route('manager.banners.store'), [
            'title' => 'Bắt đầu hành trình mới',
            'alt_text' => 'Hội viên tập cùng huấn luyện viên',
            'image' => UploadedFile::fake()->image('hero.jpg', 1200, 600),
        ])->assertRedirect(route('manager.banners.index'));

        $banner = HeroBanner::query()->firstOrFail();
        $this->assertDatabaseHas('hero_banners', [
            'id' => $banner->id,
            'title' => 'Bắt đầu hành trình mới',
            'is_active' => true,
        ]);
        Storage::disk('public')->assertExists($banner->image_path);
        $this->get(route('home'))->assertOk()->assertSee($banner->image_path);
    }

    public function test_banner_upload_rejects_svg_and_non_managers_cannot_edit_site_content(): void
    {
        $employee = User::factory()->employee()->create(['is_active' => true]);

        $this->actingAs($employee)->get(route('manager.settings.edit'))->assertForbidden();
        $this->put(route('manager.settings.update'), ['gym_name' => 'Forged'])->assertForbidden();
        $this->post(route('manager.banners.store'), [
            'title' => 'Unsafe',
            'alt_text' => 'Unsafe',
            'image' => UploadedFile::fake()->create('banner.svg', 10, 'image/svg+xml'),
        ])->assertForbidden();

        $this->assertDatabaseMissing('gym_settings', ['gym_name' => 'Forged']);
        $this->assertDatabaseCount('hero_banners', 0);
    }
}
