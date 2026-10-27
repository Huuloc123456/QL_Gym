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
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->decimal('sql_membership_id', 18, 0)->nullable()->index();
        });

        Schema::table('trial_entitlements', function (Blueprint $table): void {
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trial_entitlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revoked_by');
        });

        Schema::table('check_ins', function (Blueprint $table): void {
            $table->dropIndex(['sql_membership_id']);
            $table->dropColumn('sql_membership_id');
        });
    }
};
