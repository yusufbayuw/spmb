<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\DocumentResource;
use App\Filament\Admin\Resources\DocumentResource\Pages\ListDocuments;
use App\Models\Document;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\DocumentUploadOverview;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentUploadOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_widget_counts_distinct_applicants_and_uses_their_own_configuration(): void
    {
        [$unit, $staff] = $this->staffFixture();
        $otherUnit = Unit::create(['name' => 'Unit Lain', 'code' => 'DOC-OTHER', 'institution_type' => 'school', 'is_active' => true]);

        $first = $this->registration($unit, 1, 'Anak Pertama', ['family_card', 'certificates', 'photo']);
        $second = $this->registration($unit, 2, 'Anak Kedua', ['family_card', 'photo']);
        $foreign = $this->registration($otherUnit, 3, 'Anak Unit Lain', ['family_card']);

        $this->document($first, 'family_card', 'Kartu Keluarga.jpg');
        $this->document($first, 'certificates', 'Sertifikat A.jpg');
        $this->document($first, 'certificates', 'Sertifikat B.jpg', rejected: true);
        $this->document($first, 'photo', 'Foto Lama.jpg', superseded: true);
        $this->document($second, 'photo', 'Foto.jpg');
        $this->document($foreign, 'family_card', 'Rahasia.jpg');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $overview = app(DocumentUploadOverview::class);
        $this->assertSame(2, $overview->count());
        $this->assertSame(
            ['uploaded' => 2, 'total' => 3, 'rejected' => 1, 'pending' => 2, 'verified' => 0],
            $overview->summary($first->load('documents', 'configuration')),
        );

        $page = $overview->applicants();
        $this->assertSame(2, $page->total());
        $this->assertSame(1, $overview->applicants('Anak Pertama')->total());
        $this->assertSame(0, $overview->applicants('Unit Lain')->total());
    }

    public function test_document_tabs_and_name_search_are_scoped_to_document_records(): void
    {
        [$unit, $staff] = $this->staffFixture();
        $first = $this->registration($unit, 11, 'Nama Anak Unik', ['family_card', 'photo']);
        $second = $this->registration($unit, 12, 'Peserta Lain', ['family_card']);
        $verified = $this->document($first, 'family_card', 'nama-berkas.pdf', verified: true);
        $rejected = $this->document($first, 'photo', 'foto.jpg', rejected: true);
        $pending = $this->document($second, 'family_card', 'dokumen.pdf');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(ListDocuments::class)->assertSuccessful();
        $tabs = $page->instance()->getTabs();
        $this->assertSame(3, $tabs['all']->getBadge());
        $this->assertSame(1, $tabs['verified']->getBadge());
        $this->assertSame(1, $tabs['rejected']->getBadge());
        $this->assertSame(1, $tabs['pending']->getBadge());

        $page->set('activeTab', 'rejected')
            ->assertCanSeeTableRecords([$rejected])
            ->assertCanNotSeeTableRecords([$verified, $pending]);

        $page->set('activeTab', 'verified')
            ->assertCanSeeTableRecords([$verified])
            ->assertCanNotSeeTableRecords([$rejected, $pending]);

        $page->set('activeTab', 'all')
            ->searchTable('Nama Anak Unik')
            ->assertCanSeeTableRecords([$verified, $rejected])
            ->assertCanNotSeeTableRecords([$pending]);

        // File names are no longer searchable: only the registration identity.
        $page->searchTable('dokumen.pdf')
            ->assertCanNotSeeTableRecords([$verified, $rejected, $pending]);
    }

    public function test_focus_link_cannot_bypass_tu_unit_scope(): void
    {
        [$unit, $staff] = $this->staffFixture();
        $otherUnit = Unit::create(['name' => 'Unit Tetangga', 'code' => 'DOC-NEIGH', 'institution_type' => 'school', 'is_active' => true]);
        $ownRegistration = $this->registration($unit, 20, 'Anak Saya', ['family_card']);
        $foreignRegistration = $this->registration($otherUnit, 21, 'Anak Lain', ['family_card']);
        $ownDocument = $this->document($ownRegistration, 'family_card', 'own.pdf');
        $foreignDocument = $this->document($foreignRegistration, 'family_card', 'foreign.pdf');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListDocuments::class, ['registrationId' => $ownRegistration->id])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$ownDocument])
            ->assertCanNotSeeTableRecords([$foreignDocument]);

        Livewire::test(ListDocuments::class, ['registrationId' => $foreignRegistration->id])
            ->assertSuccessful()
            ->assertCanNotSeeTableRecords([$foreignDocument, $ownDocument]);

        $this->assertSame(1, app(DocumentUploadOverview::class)->count());
    }

    private function staffFixture(): array
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Unit Sendiri', 'code' => 'DOC-OWN', 'institution_type' => 'school', 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'tu', 'unit_id' => $unit->id, 'is_active' => true, 'email_verified_at' => now(),
        ]);
        $staff->assignRole('tu');

        return [$unit, $staff];
    }

    private function registration(Unit $unit, int $index, string $name, array $keys): Registration
    {
        $opening = RegistrationOpening::firstOrCreate(
            ['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1'],
            ['status' => 'open', 'registration_fee' => 0],
        );
        $configuration = UnitConfiguration::create([
            'unit_id' => $unit->id,
            'version' => $index,
            'status' => 'published',
            'documents_enabled' => true,
            'fields' => [],
            'test_definitions' => [],
            'document_requirements' => array_map(
                fn (string $key): array => [
                    'key' => $key, 'label' => $key, 'active' => true,
                    'required' => true, 'max_files' => 2, 'formats' => ['pdf', 'jpg'],
                ],
                $keys,
            ),
        ]);

        return Registration::create([
            'user_id' => User::factory()->create(['name' => 'Nama Akun Orang Tua '.$index, 'is_active' => true])->id,
            'unit_id' => $unit->id,
            'unit_configuration_id' => $configuration->id,
            'registration_opening_id' => $opening->id,
            'registration_number' => 'DOC-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'nik' => '327301010101'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'gender' => 'L', 'birth_place' => 'Bandung',
            'birth_date' => '2012-01-01', 'home_address' => 'Alamat Test',
            'status' => 'submitted', 'current_stage' => 'document_verification',
            'lifecycle_status' => 'active',
        ]);
    }

    private function document(
        Registration $registration,
        string $key,
        string $filename,
        bool $verified = false,
        bool $rejected = false,
        bool $superseded = false,
    ): Document {
        return Document::create([
            'registration_id' => $registration->id,
            'requirement_key' => $key,
            'type' => in_array($key, ['family_card', 'photo'], true) ? $key : 'supporting_document',
            'attachment_index' => $filename === 'Sertifikat B.jpg' ? 1 : 0,
            'file_path' => 'documents/'.$registration->id.'/'.$filename,
            'original_name' => $filename,
            'file_type' => pathinfo($filename, PATHINFO_EXTENSION),
            'file_size' => 1000,
            'is_verified' => $verified,
            'rejection_reason' => $rejected ? 'Perbaiki berkas' : null,
            'superseded_at' => $superseded ? now() : null,
        ]);
    }
}
