<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_manage_trial_requests')->default(false);
            $table->boolean('can_manage_orders')->default(false);
            $table->boolean('can_manage_check_ins')->default(false);
        });

        DB::table('users')->where('role', 'employee')->update([
            'can_manage_trial_requests' => true,
            'can_manage_orders' => true,
            'can_manage_check_ins' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'can_manage_trial_requests',
                'can_manage_orders',
                'can_manage_check_ins',
            ]);
        });
    }
};
