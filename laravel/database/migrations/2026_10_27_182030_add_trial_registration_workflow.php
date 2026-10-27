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
        Schema::table('trial_requests', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('desired_date')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->index(['user_id', 'status']);
        });
        Schema::create('trial_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trial_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('verified_phone', 20)->unique();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
        });
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->foreignId('trial_entitlement_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('gym_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('trial_days')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trial_entitlement_id');
        });
        Schema::table('gym_settings', function (Blueprint $table): void {
            $table->dropColumn('trial_days');
        });
        Schema::dropIfExists('trial_entitlements');
        Schema::table('trial_requests', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['desired_date', 'decision_reason', 'decided_at']);
        });
    }
};
