<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use App\Repositories\GymPackageRepository;
use App\Repositories\MembershipOrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_repository_returns_only_active_packages_in_duration_order(): void
    {
        $annual = GymPackage::factory()->create(['duration_months' => 12, 'is_active' => true]);
        $inactive = GymPackage::factory()->create(['duration_months' => 1, 'is_active' => false]);
        $monthly = GymPackage::factory()->create(['duration_months' => 1, 'is_active' => true]);

        $packages = app(GymPackageRepository::class)->activePackages();

        $this->assertSame([$monthly->id, $annual->id], $packages->modelKeys());
    }

    public function test_customer_order_repository_never_returns_another_customers_orders(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $package = GymPackage::factory()->create();
        $ownOrder = MembershipOrder::factory()->for($owner)->for($package, 'gymPackage')->create();
        MembershipOrder::factory()->for($otherCustomer)->for($package, 'gymPackage')->create();

        $orders = app(MembershipOrderRepository::class)->forCustomer($owner)->get();

        $this->assertSame([$ownOrder->id], $orders->modelKeys());
    }
}
