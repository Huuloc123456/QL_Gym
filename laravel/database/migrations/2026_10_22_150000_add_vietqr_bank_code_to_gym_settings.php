<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_settings', function (Blueprint $table): void {
            $table->string('bank_code', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gym_settings', function (Blueprint $table): void {
            $table->dropColumn('bank_code');
        });
    }
};
