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
        Schema::create('gym_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('gym_name', 120)->default('The Gym');
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('opening_hours', 255)->nullable();
            $table->text('introduction')->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 80)->nullable();
            $table->string('bank_account_name', 160)->nullable();
            $table->json('faq_items')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_settings');
    }
};
