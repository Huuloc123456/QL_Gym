<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_online_payments', function (Blueprint $table): void {
            $table->string('review_resolution', 20)->nullable()->after('review_reason');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('review_resolution')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->string('resolution_reference', 100)->nullable()->after('reviewed_at');
            $table->text('resolution_note')->nullable()->after('resolution_reference');
        });
    }

    public function down(): void
    {
        Schema::table('gym_online_payments', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropColumn(['review_resolution', 'reviewed_by_user_id', 'reviewed_at', 'resolution_reference', 'resolution_note']);
        });
    }
};
