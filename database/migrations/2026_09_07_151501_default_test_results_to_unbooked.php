<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_test_results', function (Blueprint $table): void {
            $table->string('status', 20)->default('unbooked')->change();
        });
    }

    public function down(): void
    {
        Schema::table('admission_test_results', function (Blueprint $table): void {
            $table->string('status', 20)->default('scheduled')->change();
        });
    }
};
