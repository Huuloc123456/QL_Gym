<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class LegacyQlGymSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! Schema::hasTable('GOITAP') || ! Schema::hasTable('KHACHHANG') || ! Schema::hasTable('NHANVIEN')) {
            $this->command?->warn('Không tìm thấy bảng gốc của ql_gym; bỏ qua.');

            return;
        }
        Artisan::call('gym:link-sql');
    }
}
