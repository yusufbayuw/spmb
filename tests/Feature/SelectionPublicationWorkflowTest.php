<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\AnnouncementResource;
use App\Filament\Admin\Resources\SelectionResource;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\RegistrationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SelectionPublicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_selection_is_not_official_until_result_is_published(): void
    {
        Queue::fake();

        [$registration, $staff] = $this->registrationAtSelectionStage();
        $workflow = app(RegistrationWorkflowService::class);

        $selection = $workflow->decide(
            $registration,
            $staff,
            'accepted',
            92,
            'Memenuhi seluruh kriteria penerimaan.',
        );

        $registration->refresh();
        $announcement = $registration->announcement()->firstOrFail();

        $this->assertSame('accepted', $selection->decision);
        $this->assertSame('announcement', $registration->current_stage);
        $this->assertSame('submitted', $registration->status);
        $this->assertNull($registration->accepted_at);
        $this->assertSame('draft', $announcement->status);
        $this->assertNull($announcement->published_at);

        $published = $workflow->publish(
            $registration,
            $staff,
            'Pengumuman Hasil SPMB',
            'Selamat, calon siswa dinyatakan diterima.',
        );

        $registration->refresh();

        $this->assertSame('completed', $registration->current_stage);
        $this->assertSame('accepted', $registration->status);
        $this->assertNotNull($registration->accepted_at);
        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->published_at);
    }

    public function test_selection_and_announcement_cannot_be_created_manually_from_resources(): void
    {
        $this->assertFalse(SelectionResource::canCreate());
        $this->assertFalse(AnnouncementResource::canCreate());
    }

    private function registrationAtSelectionStage(): array
    {
        $unit = Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'is_active' => true,
        ]);

        $applicant = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
            'email' => 'applicant-selection@example.test',
        ]);

        $staff = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'unit_id' => $unit->id,
        ]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => '3273010101010099',
            'full_name' => 'Calon Siswa Seleksi',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'selection',
            'data_validation_status' => 'valid',
        ]);

        return [$registration, $staff];
    }
}
