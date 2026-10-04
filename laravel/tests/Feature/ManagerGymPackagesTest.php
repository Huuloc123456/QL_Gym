<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ManagerGymPackagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_edit_and_deactivate_packages_without_removing_order_history(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create(['name' => 'Gói cũ', 'is_active' => true]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();

        $this->actingAs($manager)->get(route('manager.packages.index'))
            ->assertOk()
            ->assertSee('Gói cũ')
            ->assertSee('Quản lý gói tập');

        $this->post(route('manager.packages.store'), [
            'name' => 'Gói sức bền',
            'description' => 'Tập luyện trong ba tháng.',
            'price' => 899000,
            'duration_months' => 3,
        ])->assertRedirect(route('manager.packages.index'));

        $createdPackage = GymPackage::query()->where('name', 'Gói sức bền')->firstOrFail();
        $this->assertTrue($createdPackage->is_active);

        $this->put(route('manager.packages.update', $createdPackage), [
            'name' => 'Gói sức bền nâng cấp',
            'description' => 'Thời hạn mới.',
            'price' => 999000,
            'duration_months' => 6,
        ])->assertRedirect(route('manager.packages.index'));

        $this->assertDatabaseHas('gym_packages', [
            'id' => $createdPackage->id,
            'name' => 'Gói sức bền nâng cấp',
            'price' => 999000,
            'duration_months' => 6,
        ]);

        $this->patch(route('manager.packages.toggle-active', $package))
            ->assertRedirect(route('manager.packages.index'));

        $this->assertDatabaseHas('gym_packages', ['id' => $package->id, 'is_active' => false]);
        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'gym_package_id' => $package->id]);
        $this->get(route('packages.index'))->assertDontSee('Gói cũ');
        $this->assertFalse(GymPackage::query()->findOrFail($package->id)->is_active);

        $this->actingAs($customer)->post(route('orders.store'), [
            'gym_package_id' => $package->id,
            'start_date' => now(config('app.timezone'))->toDateString(),
            'payment_method' => 'cash',
        ])->assertNotFound();

        $this->assertDatabaseCount('membership_orders', 1);
    }

    public function test_only_managers_can_manage_packages(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $employee = User::factory()->employee()->create();
        $package = GymPackage::factory()->create();

        foreach ([$customer, $employee] as $nonManager) {
            $this->actingAs($nonManager)->get(route('manager.packages.index'))->assertForbidden();
            $this->actingAs($nonManager)->patch(route('manager.packages.toggle-active', $package))->assertForbidden();
        }

        $this->post(route('logout'));
        $this->get(route('manager.packages.index'))->assertRedirect(route('login'));
        $this->assertTrue(Gate::forUser(User::factory()->manager()->create())->allows('viewAny', GymPackage::class));
        $this->assertFalse(Gate::forUser($employee)->allows('viewAny', GymPackage::class));
    }

    public function test_package_form_rejects_invalid_price_and_duration(): void
    {
        $manager = User::factory()->manager()->create();
        $existingPackage = GymPackage::factory()->create(['name' => 'Gói đang hoạt động']);

        $this->actingAs($manager)->get(route('manager.packages.create'))
            ->assertOk()
            ->assertSee('Tạo gói tập mới');

        $this->get(route('manager.packages.edit', $existingPackage))
            ->assertOk()
            ->assertSee('Sửa thông tin gói');

        $this->actingAs($manager)->from(route('manager.packages.create'))
            ->post(route('manager.packages.store'), [
                'name' => 'Gói sai',
                'description' => 'Nội dung',
                'price' => -1,
                'duration_months' => 0,
            ])
            ->assertSessionHasErrors(['price', 'duration_months']);

        $this->assertDatabaseMissing('gym_packages', ['name' => 'Gói sai']);
    }
}
