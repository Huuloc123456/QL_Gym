<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\User;
use Database\Seeders\LegacyQlGymSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Dùng DatabaseMigrations vì tạo bảng trên MySQL sẽ tự commit transaction của RefreshDatabase.
 */
class LegacyQlGymSeederTest extends TestCase
{
    use DatabaseMigrations;

    private const LEGACY_TABLES = ['NHANVIEN', 'CHUCVU', 'KHACHHANG', 'GOITAP'];

    public function test_legacy_packages_customers_and_employees_are_imported_once(): void
    {
        $this->createLegacyTables();

        DB::table('GOITAP')->insert([
            ['MAGOITAP' => 1, 'TENGOI' => '1 tháng', 'THOIHAN' => 1, 'GIA' => 399000, 'MOTA' => 'Gói cơ bản'],
            ['MAGOITAP' => 2, 'TENGOI' => '3 tháng', 'THOIHAN' => 3, 'GIA' => 1499000, 'MOTA' => null],
        ]);
        DB::table('KHACHHANG')->insert([
            ['MAKH' => 161, 'TENKH' => 'Nguyễn Văn A', 'TENDANGNHAP' => 'user161', 'MATKHAU' => 'pass', 'SDT' => '0900000161', 'EMAIL' => null, 'GIOITINH' => 'Nam', 'NGAYSINH' => null],
            ['MAKH' => 162, 'TENKH' => 'Trần Thị B', 'TENDANGNHAP' => 'user162', 'MATKHAU' => 'pass', 'SDT' => '0900000162', 'EMAIL' => 'b@example.com', 'GIOITINH' => 'Nữ', 'NGAYSINH' => '2001-05-02 00:00:00'],
        ]);
        DB::table('CHUCVU')->insert([
            ['MACHUCVU' => 2, 'TENCHUCVU' => 'PT'],
            ['MACHUCVU' => 3, 'TENCHUCVU' => 'Quản lý'],
        ]);
        DB::table('NHANVIEN')->insert([
            ['MANV' => 24, 'MACHUCVU' => 2, 'TENNV' => 'PT Nguyễn Văn X', 'TENDANGNHAP' => 'pt24', 'MATKHAU' => 'pass', 'SDT' => '0999999024', 'GIOITINH' => 'Nam', 'NGAYSINH' => null],
            ['MANV' => 30, 'MACHUCVU' => 3, 'TENNV' => 'Quản lý cũ', 'TENDANGNHAP' => 'ql30', 'MATKHAU' => '123', 'SDT' => null, 'GIOITINH' => null, 'NGAYSINH' => null],
        ]);

        $this->seed(LegacyQlGymSeeder::class);
        $this->seed(LegacyQlGymSeeder::class);

        $this->assertSame(2, GymPackage::query()->count());
        $this->assertDatabaseHas('gym_packages', ['name' => 'Gói 3 tháng', 'price' => 1499000, 'duration_months' => 3, 'is_active' => true]);

        $customer = User::query()->where('username', 'user161')->firstOrFail();
        $this->assertSame(161, $customer->id);
        $this->assertSame('customer', $customer->role);
        $this->assertSame('male', $customer->gender);
        $this->assertSame('user161@thegym.test', $customer->email);
        $this->assertTrue(Hash::check('pass', $customer->password));
        $this->assertDatabaseHas('users', ['username' => 'user162', 'email' => 'b@example.com', 'gender' => 'female']);

        $this->assertDatabaseHas('users', ['username' => 'pt24', 'role' => 'employee']);
        $this->assertDatabaseHas('users', ['username' => 'ql30', 'role' => 'manager', 'phone' => 'NV30']);
        $this->assertSame(4, User::query()->count());
    }

    public function test_seeder_skips_when_legacy_tables_are_missing(): void
    {
        $this->seed(LegacyQlGymSeeder::class);

        $this->assertSame(0, GymPackage::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    private function createLegacyTables(): void
    {
        $this->beforeApplicationDestroyed(function (): void {
            foreach (self::LEGACY_TABLES as $table) {
                Schema::dropIfExists($table);
            }
        });

        Schema::create('GOITAP', function (Blueprint $table): void {
            $table->integer('MAGOITAP')->primary();
            $table->string('TENGOI');
            $table->integer('THOIHAN');
            $table->integer('GIA');
            $table->text('MOTA')->nullable();
        });
        Schema::create('KHACHHANG', function (Blueprint $table): void {
            $table->integer('MAKH')->primary();
            $table->string('TENKH');
            $table->string('TENDANGNHAP');
            $table->string('MATKHAU');
            $table->string('SDT')->nullable();
            $table->string('EMAIL')->nullable();
            $table->string('GIOITINH')->nullable();
            $table->dateTime('NGAYSINH')->nullable();
        });
        Schema::create('CHUCVU', function (Blueprint $table): void {
            $table->integer('MACHUCVU')->primary();
            $table->string('TENCHUCVU');
        });
        Schema::create('NHANVIEN', function (Blueprint $table): void {
            $table->integer('MANV')->primary();
            $table->integer('MACHUCVU')->nullable();
            $table->string('TENNV');
            $table->string('TENDANGNHAP');
            $table->string('MATKHAU');
            $table->string('SDT')->nullable();
            $table->string('GIOITINH')->nullable();
            $table->dateTime('NGAYSINH')->nullable();
        });
    }
}
