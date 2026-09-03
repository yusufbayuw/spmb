<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('units', 'institution_type')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->string('institution_type', 30)
                    ->default('school')
                    ->after('code')
                    ->index();
            });
        }

        if (! Schema::hasTable('study_programs')) {
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
        }

        if ($this->hasNamedIndex('registration_openings', 'registration_openings_unique_period')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->dropUnique('registration_openings_unique_period');
            });
        }

        if (! Schema::hasColumn('registration_openings', 'study_program_id')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->foreignId('study_program_id')
                    ->nullable()
                    ->after('unit_id')
                    ->constrained('study_programs')
                    ->restrictOnDelete();
            });
        }

        if (! $this->hasNamedIndex('registration_openings', 'registration_openings_unique_offering')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->unique(
                    ['unit_id', 'study_program_id', 'academic_year', 'wave', 'pathway'],
                    'registration_openings_unique_offering'
                );
            });
        }

        if (! $this->hasNamedIndex('registration_openings', 'registration_openings_program_status')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->index(['study_program_id', 'status'], 'registration_openings_program_status');
            });
        }

        // MySQL may use the old composite unique index as the supporting index
        // for admission_tests.unit_id's foreign key. Add a temporary standalone
        // index first so the unique index can be dropped safely.
        if (
            $this->hasNamedIndex('admission_tests', 'admission_tests_unit_id_code_unique')
            && ! $this->hasNamedIndex('admission_tests', 'admission_tests_unit_fk_guard')
        ) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->index('unit_id', 'admission_tests_unit_fk_guard');
            });
        }

        if ($this->hasNamedIndex('admission_tests', 'admission_tests_unit_id_code_unique')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->dropUnique('admission_tests_unit_id_code_unique');
            });
        }

        if (! Schema::hasColumn('admission_tests', 'study_program_id')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->foreignId('study_program_id')
                    ->nullable()
                    ->after('unit_id')
                    ->constrained('study_programs')
                    ->restrictOnDelete();
            });
        }

        if (! $this->hasNamedIndex('admission_tests', 'admission_tests_unit_program_code_unique')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->unique(
                    ['unit_id', 'study_program_id', 'code'],
                    'admission_tests_unit_program_code_unique'
                );
            });
        }

        if (! $this->hasNamedIndex('admission_tests', 'admission_tests_program_active')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->index(['study_program_id', 'is_active'], 'admission_tests_program_active');
            });
        }

        if ($this->hasNamedIndex('admission_tests', 'admission_tests_unit_fk_guard')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->dropIndex('admission_tests_unit_fk_guard');
            });
        }
    }

    public function down(): void
    {
        if (
            $this->hasNamedIndex('admission_tests', 'admission_tests_unit_program_code_unique')
            && ! $this->hasNamedIndex('admission_tests', 'admission_tests_unit_fk_guard')
        ) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->index('unit_id', 'admission_tests_unit_fk_guard');
            });
        }

        if ($this->hasNamedIndex('admission_tests', 'admission_tests_unit_program_code_unique')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->dropUnique('admission_tests_unit_program_code_unique');
            });
        }

        if ($this->hasNamedIndex('admission_tests', 'admission_tests_program_active')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->dropIndex('admission_tests_program_active');
            });
        }

        if (Schema::hasColumn('admission_tests', 'study_program_id')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('study_program_id');
            });
        }

        if (! $this->hasNamedIndex('admission_tests', 'admission_tests_unit_id_code_unique')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->unique(['unit_id', 'code']);
            });
        }

        if ($this->hasNamedIndex('admission_tests', 'admission_tests_unit_fk_guard')) {
            Schema::table('admission_tests', function (Blueprint $table): void {
                $table->dropIndex('admission_tests_unit_fk_guard');
            });
        }

        if ($this->hasNamedIndex('registration_openings', 'registration_openings_unique_offering')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->dropUnique('registration_openings_unique_offering');
            });
        }

        if ($this->hasNamedIndex('registration_openings', 'registration_openings_program_status')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->dropIndex('registration_openings_program_status');
            });
        }

        if (Schema::hasColumn('registration_openings', 'study_program_id')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('study_program_id');
            });
        }

        if (! $this->hasNamedIndex('registration_openings', 'registration_openings_unique_period')) {
            Schema::table('registration_openings', function (Blueprint $table): void {
                $table->unique(
                    ['unit_id', 'academic_year', 'wave', 'pathway'],
                    'registration_openings_unique_period'
                );
            });
        }

        Schema::dropIfExists('study_programs');

        if (Schema::hasColumn('units', 'institution_type')) {
            Schema::table('units', function (Blueprint $table): void {
                $table->dropColumn('institution_type');
            });
        }
    }

    private function hasNamedIndex(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
