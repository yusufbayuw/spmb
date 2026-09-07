<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Filament\Admin\Resources\DocumentResource\Pages\ListDocuments;
use App\Filament\Applicant\Pages\DocumentsUpload;
use App\Jobs\SendAnnouncementPublishedMail;
use App\Models\Document;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\RegistrationWorkflowService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SpmbTrialIssuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_registration_shows_reason_to_its_parent_and_hides_upload_actions(): void
    {
        [$registration, $staff, $parent] = $this->fixture();
        $registration->changeLifecycle('cancelled', $staff, 'Data ganda <script>alert(1)</script>');

        $this->actingAs($parent)->get('/pendaftar/status/'.$registration->uuid)
            ->assertOk()
            ->assertSeeText('Pendaftaran Dibatalkan')
            ->assertSee('Data ganda <script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSeeText('Lengkapi Dokumen');

        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('pendaftar');
        $this->actingAs($other)->get('/pendaftar/status/'.$registration->uuid)->assertNotFound();
    }

    public function test_draft_announcement_renders_and_staff_can_publish_it_to_complete_parent_journey(): void
    {
        Queue::fake();
        [$registration, $staff, $parent] = $this->fixture(stage: 'selection');
        app(RegistrationWorkflowService::class)->decide($registration, $staff, 'accepted');
        $announcement = $registration->announcement()->firstOrFail();

        $this->actingAs($parent)->get('/pendaftar/status/'.$registration->uuid)
            ->assertSeeText('Menunggu pengumuman dipublikasikan')->assertDontSeeText('DITERIMA');
        $this->actingAs($staff)->get('/admin/announcements')->assertOk()->assertSeeText('Belum Dipublikasikan');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListAnnouncements::class)
            ->callTableAction('publish', $announcement)
            ->assertHasNoTableActionErrors();

        $this->assertSame('completed', $registration->fresh()->current_stage);
        $this->assertNull($registration->admissionOffer()->first());
        $this->assertNotNull($announcement->fresh()->published_at);
        $this->get('/admin/announcements')->assertOk()->assertSeeText('Sudah Dipublikasikan');
        $this->actingAs($parent)->get('/pendaftar/status/'.$registration->uuid)->assertSeeText('DITERIMA');
        Queue::assertPushed(SendAnnouncementPublishedMail::class);
    }

    #[TestWith(['SD', true])]
    #[TestWith(['SMP', false])]
    #[TestWith(['SMA', false])]
    public function test_only_sd_can_finish_document_verification_without_report_card(string $code, bool $expected): void
    {
        [$registration, $staff] = $this->fixture($code);
        foreach (['family_card', 'birth_certificate', 'photo'] as $type) {
            $this->document($registration, $type, true);
        }

        $this->assertSame($expected, app(RegistrationWorkflowService::class)->refreshDocumentStage($registration));
        $this->assertSame($expected ? 'selection' : 'document_verification', $registration->fresh()->current_stage);
    }

    public function test_sd_still_requires_all_other_documents_to_be_verified(): void
    {
        [$registration] = $this->fixture();
        $this->document($registration, 'family_card', true);
        $this->document($registration, 'photo', false);

        $this->assertFalse(app(RegistrationWorkflowService::class)->refreshDocumentStage($registration));
        $this->assertNull($registration->fresh()->documents_verified_at);
        $this->assertDatabaseMissing('selections', ['registration_id' => $registration->id]);
    }

    public function test_sd_parent_sees_no_report_card_requirement_and_can_resume_already_verified_documents(): void
    {
        [$registration, , $parent] = $this->fixture();
        foreach (['family_card', 'birth_certificate', 'photo'] as $type) {
            $this->document($registration, $type, true);
        }
        $this->actingAs($parent)->get('/pendaftar/status/'.$registration->uuid)->assertDontSeeText('Rapor / Dokumen Akademik');
        $this->get('/pendaftar/dokumen/'.$registration->uuid)->assertOk()->assertDontSeeText('Rapor');
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])->call('submit')->assertHasNoFormErrors();

        $this->assertSame('selection', $registration->fresh()->current_stage);
    }

    public function test_staff_can_reject_unverified_document_with_reason_visible_to_parent(): void
    {
        [$registration, $staff, $parent] = $this->fixture(stage: 'document_verification');
        $document = $this->document($registration, 'photo');
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListDocuments::class)
            ->callTableAction('reject', $document, ['rejection_reason' => 'Foto buram, unggah foto yang jelas.'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'is_verified' => false, 'rejection_reason' => 'Foto buram, unggah foto yang jelas.']);
        $this->assertSame('documents', $registration->fresh()->current_stage);
        $this->assertStringContainsString('Foto buram', json_encode($parent->notifications()->where('data->spmb_event', 'documents.verification_reopened')->firstOrFail()->data));
        $this->actingAs($parent)->get('/pendaftar/dokumen/'.$registration->uuid)->assertSeeText('Ditolak')->assertSeeText('Foto buram, unggah foto yang jelas.');
        $this->get('/pendaftar/status/'.$registration->uuid)->assertSeeText('Foto buram, unggah foto yang jelas.');
    }

    public function test_rejection_requires_reason_and_does_not_change_document_on_failure(): void
    {
        [$registration, $staff] = $this->fixture();
        $document = $this->document($registration, 'photo');
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListDocuments::class)->callTableAction('reject', $document, ['rejection_reason' => ''])
            ->assertHasTableActionErrors(['rejection_reason' => 'required']);
        $this->assertNull($document->fresh()->rejection_reason);
    }

    public function test_staff_cannot_reject_documents_of_another_unit_or_after_selection(): void
    {
        [$registration, $staff] = $this->fixture(stage: 'selection');
        $document = $this->document($registration, 'photo', true);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListDocuments::class)->assertTableActionHidden('reject', $document);
        $staff->update(['unit_id' => Unit::create(['name' => 'Unit lain', 'code' => 'OTHER', 'is_active' => true])->id]);
        Livewire::test(ListDocuments::class)->assertCanNotSeeTableRecords([$document]);
        $this->assertTrue($document->fresh()->is_verified);
    }

    public function test_reupload_clears_rejection_but_requires_new_verification(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');
        Storage::fake('public');
        config(['spmb.uploads.require_malware_scan' => false]);
        [$registration, $staff, $parent] = $this->fixture();
        $document = $this->document($registration, 'photo');
        app(RegistrationWorkflowService::class)->rejectDocument($document, $staff, 'Foto buram');
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->fillForm(['photo' => UploadedFile::fake()->image('new-photo.png')])
            ->call('submit')->assertHasNoFormErrors();
        $this->assertNull($document->fresh()->rejection_reason);
        $this->assertFalse($document->fresh()->is_verified);
        $this->assertNull($document->fresh()->verified_by);
        $this->assertNotNull($document->fresh()->security_scanned_at);
    }

    public function test_verifying_last_sd_document_advances_and_clears_previous_rejection(): void
    {
        [$registration, $staff] = $this->fixture();
        $this->document($registration, 'family_card', true);
        $this->document($registration, 'birth_certificate', true);
        $document = $this->document($registration, 'photo');
        $document->update(['rejection_reason' => 'Periksa foto']);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListDocuments::class)->callTableAction('verify', $document)->assertHasNoTableActionErrors();

        $this->assertTrue($document->fresh()->is_verified);
        $this->assertNull($document->fresh()->rejection_reason);
        $this->assertSame('selection', $registration->fresh()->current_stage);
    }

    public function test_rejected_required_document_cannot_be_resubmitted_without_replacement(): void
    {
        [$registration, $staff, $parent] = $this->fixture();
        $this->document($registration, 'family_card', true);
        $this->document($registration, 'birth_certificate', true);
        $document = $this->document($registration, 'photo', true);
        app(RegistrationWorkflowService::class)->rejectDocument($document, $staff, 'Ganti foto');
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])->call('submit')->assertHasNoFormErrors();

        $this->assertFalse($document->fresh()->is_verified);
        $this->assertSame('Ganti foto', $document->fresh()->rejection_reason);
        $this->assertSame('documents', $registration->fresh()->current_stage);
        $this->assertNull($registration->fresh()->documents_completed_at);
    }

    #[TestWith(['verify', 'selection'])]
    #[TestWith(['reject', 'selection'])]
    #[TestWith(['verify', 'completed'])]
    #[TestWith(['reject', 'completed'])]
    public function test_optional_documents_can_be_reviewed_without_changing_later_stages(string $action, string $stage): void
    {
        $this->freezeTime();
        [$registration, $staff, $parent] = $this->fixture(stage: $stage);
        $registration->update(['documents_completed_at' => now(), 'documents_verified_at' => now()]);
        $document = $this->document($registration, 'supporting_document');
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListDocuments::class)
            ->assertTableActionVisible('verify', $document)
            ->assertTableActionVisible('reject', $document)
            ->callTableAction($action, $document, $action === 'reject' ? ['rejection_reason' => 'Dokumen tidak terbaca.'] : [])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'is_verified' => $action === 'verify',
            'verified_by' => $staff->id,
            'rejection_reason' => $action === 'reject' ? 'Dokumen tidak terbaca.' : null,
        ]);
        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'current_stage' => $stage,
            'documents_completed_at' => now()->toDateTimeString(),
            'documents_verified_at' => now()->toDateTimeString(),
        ]);
        $event = $action === 'verify' ? 'document.verified' : 'document.optional_rejected';
        $this->assertSame(1, $parent->notifications()->where('data->spmb_event', $event)->count());
    }

    public function test_optional_document_actions_remain_available_after_last_required_document_is_verified(): void
    {
        [$registration, $staff] = $this->fixture();
        $this->document($registration, 'family_card', true);
        $this->document($registration, 'birth_certificate', true);
        $photo = $this->document($registration, 'photo');
        $optional = $this->document($registration, 'supporting_document');
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListDocuments::class)
            ->callTableAction('verify', $photo)
            ->assertHasNoTableActionErrors()
            ->assertTableActionVisible('verify', $optional)
            ->assertTableActionVisible('reject', $optional);

        $this->assertSame('selection', $registration->fresh()->current_stage);
    }

    public function test_optional_document_review_stays_blocked_for_inactive_registrations_and_other_units(): void
    {
        [$registration, $staff] = $this->fixture(stage: 'selection');
        $registration->update(['lifecycle_status' => 'cancelled']);
        $document = $this->document($registration, 'supporting_document');
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListDocuments::class)
            ->assertTableActionHidden('verify', $document)
            ->assertTableActionHidden('reject', $document);

        $staff->update(['unit_id' => Unit::create(['name' => 'Unit lain', 'code' => 'OTHER', 'is_active' => true])->id]);
        Livewire::test(ListDocuments::class)->assertCanNotSeeTableRecords([$document]);
        $this->assertFalse($document->fresh()->is_verified);
        $this->assertNull($document->fresh()->rejection_reason);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_late_document_review_uses_the_configured_requirement_instead_of_document_type(bool $required): void
    {
        [$registration, $staff] = $this->fixture(stage: 'selection');
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'] = [[
            'key' => 'custom_attachment', 'label' => 'Lampiran Tambahan', 'active' => true,
            'required' => $required, 'max_files' => 1, 'formats' => ['png'],
        ]];
        $configuration = $service->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);
        $document = $this->document($registration, 'supporting_document');
        $document->update(['requirement_key' => 'custom_attachment']);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(ListDocuments::class);

        if ($required) {
            $page->assertTableActionHidden('verify', $document)->assertTableActionHidden('reject', $document);
        } else {
            $page->assertTableActionVisible('verify', $document)->assertTableActionVisible('reject', $document);
        }
    }

    private function document(Registration $registration, string $type, bool $verified = false): Document
    {
        return Document::create([
            'registration_id' => $registration->id,
            'type' => $type,
            'file_path' => 'documents/'.$registration->id.'/'.$type.'.png',
            'original_name' => $type.'.png',
            'file_type' => 'png',
            'file_size' => 100,
            'sha256' => hash('sha256', $type),
            'security_scanned_at' => now(),
            'is_verified' => $verified,
        ]);
    }

    private function fixture(string $code = 'SD', string $stage = 'documents'): array
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Sekolah '.$code, 'code' => $code, 'institution_type' => 'school', 'is_active' => true]);
        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('pendaftar');
        $staff = User::factory()->create(['role' => 'tu', 'is_active' => true, 'unit_id' => $unit->id]);
        $staff->assignRole('tu');
        $opening = RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open']);
        $registration = Registration::create([
            'user_id' => $parent->id, 'unit_id' => $unit->id, 'registration_opening_id' => $opening->id,
            'registrant_type' => 'parent', 'nik' => '3273010101010001', 'full_name' => 'Peserta Uji',
            'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung',
            'status' => 'submitted', 'current_stage' => $stage,
        ]);

        return [$registration, $staff, $parent];
    }
}
