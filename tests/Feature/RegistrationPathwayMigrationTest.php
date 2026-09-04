<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationPathwayMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_opening_pathway_is_backfilled_to_existing_registrations(): void
    {
        $migration = require database_path('migrations/2026_09_04_012845_create_registration_pathways_table.php');
        $migration->down();

        $unit = Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $openingId = DB::table('registration_openings')->insertGetId([
            'unit_id' => $unit->id,
            'study_program_id' => null,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'pathway' => 'Prestasi',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('registration_openings')->insert([
            'unit_id' => $unit->id,
            'study_program_id' => null,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'pathway' => 'Reguler',
            'status' => 'closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $registrationId = DB::table('registrations')->insertGetId([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $openingId,
            'nik' => '3273010101010077',
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $pathwayId = DB::table('registration_pathways')
            ->where('unit_id', $unit->id)
            ->where('name', 'Prestasi')
            ->value('id');

        $this->assertNotNull($pathwayId);
        $this->assertSame(
            $pathwayId,
            DB::table('registrations')->where('id', $registrationId)->value('registration_pathway_id'),
        );
        $this->assertTrue(Schema::hasColumn('registration_openings', 'pathway'));
        $this->assertSame('Prestasi', DB::table('registration_openings')->where('id', $openingId)->value('pathway'));
        $this->assertSame(2, DB::table('registration_openings')->count());
        $this->assertSame(2, DB::table('registration_pathways')->where('unit_id', $unit->id)->count());
    }
}
