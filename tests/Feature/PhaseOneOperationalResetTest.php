<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\ApplicantFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseOneOperationalResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_missing_allowlist_never_delete_operational_data(): void
    {
        [$unit, $opening, $applicant, $registration] = $this->fixture();

        $this->artisan('spmb:reset-operational')->assertExitCode(0);
        $this->artisan('spmb:reset-operational', [
            '--execute' => true,
            '--target' => 'wrong-fingerprint',
            '--backup-confirmed' => true,
        ])->assertExitCode(1);

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('users', ['id' => $applicant->id]);
        $this->assertDatabaseHas('registration_openings', ['id' => $opening->id]);
    }

    public function test_allowlisted_reset_keeps_configuration_and_deletes_applicant_files(): void
    {
        Storage::fake('local');
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake(ApplicantFileStorage::LEGACY_PUBLIC_DISK);

        [$unit, $opening, $applicant, $registration] = $this->fixture();
        $unrelatedUser = User::factory()->create(['role' => 'user']);
        $path = 'documents/'.$registration->id.'/file.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, '%PDF-1.4 example');
        $abandoned = 'pre-registration/'.$applicant->id.'/unfinished.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($abandoned, '%PDF-1.4 example');
        $unrelated = 'documents/99999/untouched.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($unrelated, '%PDF-1.4 example');

        Document::create([
            'registration_id' => $registration->id,
            'type' => 'report_card',
            'file_path' => $path,
            'original_name' => 'file.pdf',
            'file_type' => 'pdf',
            'file_size' => 16,
        ]);

        $connection = DB::connection();
        $target = hash('sha256', implode('|', [
            $connection->getDriverName(),
            (string) $connection->getConfig('host'),
            (string) $connection->getConfig('port'),
            (string) $connection->getDatabaseName(),
        ]));
        config()->set('spmb.reset.allowed_target', $target);

        $this->artisan('spmb:reset-operational', [
            '--execute' => true,
            '--target' => $target,
            '--backup-confirmed' => true,
        ])
            ->expectsConfirmation('Have the database and private-file backups been restored and verified?', 'yes')
            ->expectsQuestion('Type RESET-OPERATIONAL to continue', 'RESET-OPERATIONAL')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('users', ['id' => $applicant->id]);
        $this->assertDatabaseHas('users', ['id' => $unrelatedUser->id]);
        $this->assertDatabaseHas('registration_openings', ['id' => $opening->id]);
        $this->assertDatabaseHas('units', ['id' => $unit->id]);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertMissing($path);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertMissing($abandoned);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertExists($unrelated);
    }

    public function test_assigned_va_blocks_reset_and_keeps_all_data(): void
    {
        [$unit, $opening, $applicant, $registration] = $this->fixture();

        VirtualAccount::create([
            'unit_id' => $unit->id,
            'bank' => 'TEST',
            'va_number' => 'TEST-ASSIGNED-001',
            'status' => 'assigned',
            'registration_id' => $registration->id,
        ]);

        $this->artisan('spmb:reset-operational')->assertExitCode(1);
        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('registration_openings', ['id' => $opening->id]);
        $this->assertDatabaseHas('virtual_accounts', ['registration_id' => $registration->id]);
    }

    public function test_production_configuration_blocks_reset_even_in_testing_runtime(): void
    {
        [$unit, $opening, $applicant, $registration] = $this->fixture();
        config()->set('app.env', 'production');

        $this->artisan('spmb:reset-operational')->assertExitCode(1);

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('registration_openings', ['id' => $opening->id]);
    }

    private function fixture(): array
    {
        $unit = Unit::create(['name' => 'Development Unit', 'code' => 'DEV-UNIT', 'is_active' => true]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Pertama',
            'registration_fee' => 0,
            'status' => 'open',
        ]);
        $applicant = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'nik' => '0000000000000001',
            'full_name' => 'Test Applicant',
            'gender' => 'L',
            'birth_place' => 'Test City',
            'birth_date' => '2019-01-01',
            'home_address' => 'Test Street',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
        ]);

        return [$unit, $opening, $applicant, $registration];
    }
}
