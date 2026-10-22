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
        Schema::create('gym_online_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('target_type', 24);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('txn_ref', 64)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('provider_transaction_no', 100)->nullable()->unique();
            $table->string('bank_code', 30)->nullable();
            $table->string('response_code', 10)->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_online_payments');
    }
};
