<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $employee = DB::table('NHANVIEN')->where('MANV', 20251219)->where('TENDANGNHAP', 'admin')->where('MACHUCVU', 3)->first();
        if ($employee) {
            DB::table('users')->where('sql_employee_id', $employee->MANV)->where('username', 'admin')->where('role', 'employee')->update(['role' => 'manager']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Role synchronization must not revoke an administrator's access on rollback.
    }
};
