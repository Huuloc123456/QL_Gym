<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('membership_orders', function (Blueprint $table): void {
            $table->timestamp('cancellation_requested_at')->nullable()->after('confirmed_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_orders', function (Blueprint $table): void {
            $table->dropColumn('cancellation_requested_at');
        });
    }
};
