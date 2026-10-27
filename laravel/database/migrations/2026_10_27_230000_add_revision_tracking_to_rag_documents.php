<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rag_documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('revision')->default(1);
            $table->text('sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rag_documents', function (Blueprint $table): void {
            $table->dropColumn(['revision', 'sync_error']);
        });
    }
};
