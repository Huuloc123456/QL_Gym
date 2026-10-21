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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('locked_at')->nullable()->after('is_active');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['locked_by', 'locked_at', 'is_active']);
        });
    }
};
