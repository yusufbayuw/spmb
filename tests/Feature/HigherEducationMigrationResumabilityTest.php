<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HigherEducationMigrationResumabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_higher_education_migration_can_be_invoked_again_safely(): void
    {
        $migration = require database_path('migrations/2026_09_03_020000_add_higher_education_support.php');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('units', 'institution_type'));
        $this->assertTrue(Schema::hasTable('study_programs'));
        $this->assertTrue(Schema::hasColumn('registration_openings', 'study_program_id'));
        $this->assertTrue(Schema::hasColumn('admission_tests', 'study_program_id'));

        $indexes = collect(Schema::getIndexes('admission_tests'))->pluck('name');

        $this->assertTrue($indexes->contains('admission_tests_unit_program_code_unique'));
        $this->assertTrue($indexes->contains('admission_tests_program_active'));
        $this->assertFalse($indexes->contains('admission_tests_unit_fk_guard'));
    }
}
