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
        Schema::create('gym_refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 20);
            $table->decimal('target_id', 18, 0);
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('reference', 100);
            $table->text('reason');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->uuid('token')->unique();
            $table->timestamp('refunded_at')->index();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->unique(['source_type', 'source_id', 'reference']);
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_refunds');
    }
};
