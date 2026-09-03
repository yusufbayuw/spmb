<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->string('institution_type', 30)
                ->default('school')
                ->after('code')
                ->index();
        });

        Schema::create('study_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('degree_level', 20);
            $table->string('faculty', 150)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['unit_id', 'code'], 'study_programs_unit_code_unique');
            $table->index(['unit_id', 'is_active', 'sort_order'], 'study_programs_unit_active_sort');
        });

        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->dropUnique('registration_openings_unique_period');
            $table->foreignId('study_program_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('study_programs')
                ->restrictOnDelete();

            $table->unique(
                ['unit_id', 'study_program_id', 'academic_year', 'wave', 'pathway'],
                'registration_openings_unique_offering'
            );
            $table->index(['study_program_id', 'status'], 'registration_openings_program_status');
        });
    }

    public function down(): void
    {
        Schema::table('registration_openings', function (Blueprint $table): void {
            $table->dropUnique('registration_openings_unique_offering');
            $table->dropIndex('registration_openings_program_status');
            $table->dropConstrainedForeignId('study_program_id');
            $table->unique(
                ['unit_id', 'academic_year', 'wave', 'pathway'],
                'registration_openings_unique_period'
            );
        });

        Schema::dropIfExists('study_programs');

        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('institution_type');
        });
    }
};
