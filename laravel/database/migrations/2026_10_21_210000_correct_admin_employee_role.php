<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('NHANVIEN')
            ->where('MANV', 20251219)
            ->where('TENNV', 'Admin')
            ->where('TENDANGNHAP', 'admin')
            ->where('MACHUCVU', 2)
            ->update(['MACHUCVU' => 3]);
    }

    public function down(): void
    {
        DB::table('NHANVIEN')
            ->where('MANV', 20251219)
            ->where('TENNV', 'Admin')
            ->where('TENDANGNHAP', 'admin')
            ->where('MACHUCVU', 3)
            ->update(['MACHUCVU' => 2]);
    }
};
