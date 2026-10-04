<?php

namespace Database\Seeders;

use App\Models\GymPackage;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        GymPackage::query()->firstOrCreate(
            ['name' => 'Gói 1 tháng'],
            [
                'description' => 'Tập luyện không giới hạn trong 1 tháng tại The Gym.',
                'price' => 399000,
                'duration_months' => 1,
                'is_active' => true,
            ],
        );

        if (config('app.env') === 'local') {
            User::query()->firstOrCreate(
                ['username' => 'quanly'],
                [
                    'name' => 'Quản lý The Gym',
                    'email' => 'quanly@thegym.test',
                    'phone' => '0900000001',
                    'role' => 'manager',
                    'password' => 'GymDemo@2026',
                ],
            );

            User::query()->firstOrCreate(
                ['username' => 'nhanvien'],
                [
                    'name' => 'Nhân viên The Gym',
                    'email' => 'nhanvien@thegym.test',
                    'phone' => '0900000002',
                    'role' => 'employee',
                    'password' => 'GymDemo@2026',
                ],
            );
        }

        GymPackage::query()->firstOrCreate(
            ['name' => 'Gói 12 tháng'],
            [
                'description' => 'Tập luyện không giới hạn trong 12 tháng tại The Gym.',
                'price' => 10000000,
                'duration_months' => 12,
                'is_active' => true,
            ],
        );
    }
}
