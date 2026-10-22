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
        Schema::create('rag_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('document_key', 180)->unique();
            $table->string('title', 180);
            $table->longText('content');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_documents');
    }
};
