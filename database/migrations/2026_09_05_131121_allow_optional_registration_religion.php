<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', fn (Blueprint $table) => $table->string('religion', 50)->nullable()->default('Islam')->change());
        Schema::table('parent_infos', function (Blueprint $table): void {
            $table->string('father_name', 150)->nullable()->change();
            $table->string('mother_name', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('registrations')->whereNull('religion')->exists() || DB::table('parent_infos')->whereNull('father_name')->orWhereNull('mother_name')->exists()) {
            throw new RuntimeException('Rollback membutuhkan pengisian data opsional terlebih dahulu; nilai tidak akan dibuat otomatis.');
        }
        Schema::table('registrations', fn (Blueprint $table) => $table->string('religion', 50)->nullable(false)->default('Islam')->change());
        Schema::table('parent_infos', function (Blueprint $table): void {
            $table->string('father_name', 150)->nullable(false)->change();
            $table->string('mother_name', 150)->nullable(false)->change();
        });
    }
};
