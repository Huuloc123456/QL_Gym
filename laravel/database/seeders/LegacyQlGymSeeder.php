<?php

namespace Database\Seeders;

use App\Models\GymPackage;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chép dữ liệu từ các bảng cũ của ql_gym (GOITAP, KHACHHANG, NHANVIEN)
 * sang bảng Laravel. Chạy lại nhiều lần không tạo bản ghi trùng.
 */
class LegacyQlGymSeeder extends Seeder
{
    use WithoutModelEvents;

    private const MANAGER_POSITION = 'Quản lý';

    private const PLACEHOLDER_EMAIL_DOMAIN = 'thegym.test';

    public function run(): void
    {
        if (! Schema::hasTable('GOITAP') || ! Schema::hasTable('KHACHHANG') || ! Schema::hasTable('NHANVIEN')) {
            $this->command?->warn('Không tìm thấy bảng cũ của ql_gym; bỏ qua.');

            return;
        }

        DB::transaction(function (): void {
            $this->importPackages();
            $this->importCustomers();
            $this->importEmployees();
        });
    }

    private function importPackages(): void
    {
        DB::table('GOITAP')->orderBy('MAGOITAP')->get()->each(function (object $legacyPackage): void {
            GymPackage::query()->updateOrCreate(
                ['name' => 'Gói '.$legacyPackage->TENGOI],
                [
                    'description' => $legacyPackage->MOTA,
                    'price' => (int) $legacyPackage->GIA,
                    'duration_months' => (int) $legacyPackage->THOIHAN,
                    'is_active' => true,
                ],
            );
        });
    }

    private function importCustomers(): void
    {
        DB::table('KHACHHANG')->orderBy('MAKH')->get()->each(function (object $legacyCustomer): void {
            $customer = User::query()->firstOrNew(['username' => $legacyCustomer->TENDANGNHAP]);

            if (! $customer->exists) {
                // Giữ MAKH làm mã khách để nhân viên tra cứu check-in như hệ thống cũ.
                $customer->id = (int) $legacyCustomer->MAKH;
                $customer->password = $legacyCustomer->MATKHAU;
            }

            $customer->fill([
                'name' => $legacyCustomer->TENKH,
                'email' => $legacyCustomer->EMAIL ?: $this->placeholderEmail($legacyCustomer->TENDANGNHAP),
                'phone' => $legacyCustomer->SDT ?: 'KH'.(int) $legacyCustomer->MAKH,
                'birth_date' => $legacyCustomer->NGAYSINH,
                'gender' => $this->gender($legacyCustomer->GIOITINH),
                'role' => 'customer',
            ])->save();
        });
    }

    private function importEmployees(): void
    {
        DB::table('NHANVIEN')
            ->leftJoin('CHUCVU', 'CHUCVU.MACHUCVU', '=', 'NHANVIEN.MACHUCVU')
            ->orderBy('NHANVIEN.MANV')
            ->select('NHANVIEN.*', 'CHUCVU.TENCHUCVU')
            ->get()
            ->each(function (object $legacyEmployee): void {
                $employee = User::query()->firstOrNew(['username' => $legacyEmployee->TENDANGNHAP]);

                if (! $employee->exists) {
                    $employee->password = $legacyEmployee->MATKHAU;
                }

                $employee->fill([
                    'name' => $legacyEmployee->TENNV,
                    'email' => $this->placeholderEmail($legacyEmployee->TENDANGNHAP),
                    'phone' => $legacyEmployee->SDT ?: 'NV'.(int) $legacyEmployee->MANV,
                    'birth_date' => $legacyEmployee->NGAYSINH,
                    'gender' => $this->gender($legacyEmployee->GIOITINH),
                    'role' => $legacyEmployee->TENCHUCVU === self::MANAGER_POSITION ? 'manager' : 'employee',
                ])->save();
            });
    }

    private function placeholderEmail(string $username): string
    {
        return $username.'@'.self::PLACEHOLDER_EMAIL_DOMAIN;
    }

    private function gender(?string $legacyGender): ?string
    {
        return match ($legacyGender) {
            'Nam' => 'male',
            'Nữ' => 'female',
            default => null,
        };
    }
}
