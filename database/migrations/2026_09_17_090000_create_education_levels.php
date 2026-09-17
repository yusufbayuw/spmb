<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('category', 30)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['category', 'is_active', 'sort_order'], 'education_levels_category_active_sort');
        });

        $now = now();
        DB::table('education_levels')->insert([
            ['code' => 'DC', 'name' => 'Daycare', 'category' => 'early_childhood', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'KB', 'name' => 'Kelompok Bermain', 'category' => 'early_childhood', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TK', 'name' => 'Taman Kanak-Kanak', 'category' => 'early_childhood', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SD', 'name' => 'Sekolah Dasar', 'category' => 'school', 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SMP', 'name' => 'Sekolah Menengah Pertama', 'category' => 'school', 'sort_order' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SMA', 'name' => 'Sekolah Menengah Atas', 'category' => 'school', 'sort_order' => 60, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'D3', 'name' => 'Diploma III', 'category' => 'higher_education', 'sort_order' => 70, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'D4', 'name' => 'Sarjana Terapan', 'category' => 'higher_education', 'sort_order' => 80, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'S1', 'name' => 'Sarjana', 'category' => 'higher_education', 'sort_order' => 90, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'S2', 'name' => 'Magister', 'category' => 'higher_education', 'sort_order' => 100, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'S3', 'name' => 'Doktor', 'category' => 'higher_education', 'sort_order' => 110, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('units', function (Blueprint $table): void {
            $table->foreignId('education_level_id')
                ->nullable()
                ->constrained('education_levels')
                ->restrictOnDelete();
        });

        Schema::table('study_programs', function (Blueprint $table): void {
            $table->foreignId('education_level_id')
                ->nullable()
                ->constrained('education_levels')
                ->restrictOnDelete();
        });

        $levelIds = DB::table('education_levels')->pluck('id', 'code');

        foreach (['DC', 'KB', 'TK', 'SD', 'SMP', 'SMA'] as $code) {
            if (isset($levelIds[$code])) {
                DB::table('units')
                    ->where('code', $code)
                    ->where('institution_type', '!=', 'university')
                    ->update(['education_level_id' => $levelIds[$code]]);
            }
        }

        foreach (['D3', 'D4', 'S1', 'S2', 'S3'] as $code) {
            if (isset($levelIds[$code])) {
                DB::table('study_programs')
                    ->where('degree_level', $code)
                    ->update(['education_level_id' => $levelIds[$code]]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('study_programs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('education_level_id');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('education_level_id');
        });

        Schema::dropIfExists('education_levels');
    }
};
