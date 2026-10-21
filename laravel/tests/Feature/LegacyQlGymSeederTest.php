<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\User;
use Database\Seeders\LegacyQlGymSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyQlGymSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_packages_customers_and_employees_are_imported_once(): void
    {
        DB::table('NHANVIEN')->insert(['MANV' => 30, 'MACHUCVU' => 3, 'TENNV' => 'Quản lý cũ', 'TENDANGNHAP' => 'ql30', 'MATKHAU' => Hash::make('123'), 'SDT' => null]);
        $this->seed(LegacyQlGymSeeder::class);
        $packages = GymPackage::query()->count();
        $accounts = User::query()->count();

        $this->seed(LegacyQlGymSeeder::class);

        $this->assertSame($packages, GymPackage::query()->count());
        $this->assertSame($accounts, User::query()->count());
        $this->assertDatabaseHas('gym_packages', ['sql_package_id' => 2, 'price' => 1499000, 'duration_months' => 3]);
        $customer = User::query()->where('username', 'user161')->firstOrFail();
        $this->assertSame(161, $customer->sql_customer_id);
        $this->assertSame('customer', $customer->role);
        $this->assertSame('male', $customer->gender);
        $this->assertNull($customer->email);
        $this->assertTrue(Hash::check('pass', $customer->password));
        $this->assertDatabaseHas('users', ['username' => 'pt24', 'role' => 'employee', 'sql_employee_id' => 24]);
        $this->assertDatabaseHas('users', ['username' => 'ql30', 'role' => 'manager', 'phone' => null]);
    }

    public function test_seeder_skips_when_legacy_tables_are_missing(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('GOITAP')->andReturn(false);

        $this->seed(LegacyQlGymSeeder::class);

        $this->assertSame(0, GymPackage::query()->count());
        $this->assertSame(0, User::query()->count());
    }
}
