<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_infos', function (Blueprint $table): void {
            $table->string('father_income', 100)->nullable()->change();
            $table->string('mother_income', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('parent_infos', function (Blueprint $table): void {
            $table->decimal('father_income', 15, 2)->nullable()->change();
            $table->decimal('mother_income', 15, 2)->nullable()->change();
        });
    }
};
