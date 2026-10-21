<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_fulfillments', function (Blueprint $table): void {
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable();
        });
        Schema::table('gym_settings', function (Blueprint $table): void {
            $table->unsignedInteger('payment_hours')->default(24);
            $table->unsignedInteger('reminder_days')->default(3);
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->boolean('email_enabled')->default(false);
            $table->dateTime('last_processed_at')->nullable();
        });
        DB::table('gym_settings')->insertOrIgnore([
            'id' => 1,
            'gym_name' => 'The Gym',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::create('gym_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 190)->unique();
            $table->string('title');
            $table->text('body');
            $table->string('path');
            $table->dateTime('read_at')->nullable();
            $table->string('email_state', 20)->default('disabled')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('email_sent_at')->nullable();
            $table->string('last_error')->nullable();
            $table->dateTime('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_messages');
        Schema::table('gym_settings', function (Blueprint $table): void {
            $table->dropColumn(['payment_hours', 'reminder_days', 'shipping_fee', 'email_enabled', 'last_processed_at']);
        });
        Schema::table('gym_fulfillments', function (Blueprint $table): void {
            $table->dropColumn(['expires_at', 'paid_at']);
        });
    }
};
