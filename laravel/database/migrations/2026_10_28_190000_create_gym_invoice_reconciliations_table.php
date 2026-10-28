<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_invoice_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->decimal('invoice_id', 18, 0)->unique();
            $table->string('category', 40);
            $table->string('status', 20)->default('blocked')->index();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->text('evidence');
            $table->text('note');
            $table->foreignId('reviewed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_invoice_reconciliations');
    }
};
