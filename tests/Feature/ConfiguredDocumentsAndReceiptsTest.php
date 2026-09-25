<?php

namespace Tests\Feature;

use App\Filament\Applicant\Pages\DocumentsUpload;
use App\Filament\Applicant\Pages\RegistrationStatus;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\SpmbDatabaseNotification;
use App\Services\ApplicantUploadSecurity;
use App\Services\IdempotentDatabaseChannel;
use App\Services\ReceiptService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguredDocumentsAndReceiptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejected_file_shows_its_reason_and_rolls_back_all_document_uploads(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');
        config(['spmb.uploads.clamav_binary' => 'not-installed-spmb-test-clamscan', 'spmb.uploads.require_malware_scan' => false]);
        [$registration, $parent] = $this->fixture();
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $png = UploadedFile::fake()->image('photo.png')->getContent();

        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->fillForm([
                'family_card' => [UploadedFile::fake()->image('kk.jpg')],
                'photo' => [UploadedFile::fake()->createWithContent('photo.jpeg', $png)],
            ])
            ->call('submit')
            ->assertHasFormErrors(['photo'])
            ->assertSee('Ekstensi dan isi file tidak konsisten.')
            ->assertNotified(Notification::make()
                ->title('Dokumen belum berhasil disimpan')
                ->body('Ekstensi dan isi file tidak konsisten.')
                ->danger()
                ->persistent())
            ->assertNotNotified('Dokumen berhasil disimpan')
            ->assertNoRedirect();

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame('documents', $registration->fresh()->current_stage);
        Storage::disk('applicant-private')->assertDirectoryEmpty('documents/'.$registration->id);
    }

    public function test_form_validation_failure_shows_a_save_failure_notification(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');
        [$registration, $parent] = $this->fixture();
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->fillForm(['photo' => [UploadedFile::fake()->image('photo.jpg')->size(5121)]])
            ->call('submit')
            ->assertHasFormErrors(['photo'])
            ->assertNotified('Dokumen belum berhasil disimpan')
            ->assertNoRedirect();

        $this->assertDatabaseCount('documents', 0);
        Storage::disk('applicant-private')->assertDirectoryEmpty('/');
    }

    public function test_pdf_documents_and_jpeg_photo_save_together(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        Storage::fake('applicant-private');
        config(['spmb.uploads.clamav_binary' => 'not-installed-spmb-test-clamscan', 'spmb.uploads.require_malware_scan' => false]);
        [$registration, $parent] = $this->fixture();
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";

        // SQLite normally counts matched rows; emulate MySQL's zero changed rows for this no-op.
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TEMP TRIGGER ignore_unchanged_document_stage
                BEFORE UPDATE ON registrations
                WHEN OLD.current_stage = 'document_verification'
                    AND NEW.current_stage = OLD.current_stage
                    AND NEW.documents_completed_at IS OLD.documents_completed_at
                    AND NEW.documents_verified_at IS OLD.documents_verified_at
                    AND NEW.updated_at IS OLD.updated_at
                BEGIN
                    SELECT RAISE(IGNORE);
                END
                SQL);
        }

        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->fillForm([
                'family_card' => [UploadedFile::fake()->createWithContent('dokumen-pendukung.pdf', $pdf)],
                'birth_certificate' => [UploadedFile::fake()->createWithContent('akta (2).pdf', $pdf)],
                'photo' => [UploadedFile::fake()->image('Logo_YTB_SQUARE.jpg.jpeg')],
                'supporting_document' => [UploadedFile::fake()->createWithContent('dokumen-pendukung.pdf', $pdf)],
            ])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertNotified('Dokumen berhasil disimpan')
            ->assertRedirect(RegistrationStatus::getUrl(['registration' => $registration->uuid]));

        $this->assertSame('document_verification', $registration->fresh()->current_stage);
        $this->assertDatabaseCount('documents', 4);
        foreach ($registration->documents as $document) {
            Storage::disk('applicant-private')->assertExists($document->file_path);
        }
    }

    public function test_single_required_private_uploads_advance_to_document_verification(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');

        [$registration, $parent, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'] = [
            ['key' => 'report_card', 'label' => 'Rapor', 'active' => true, 'required' => true, 'max_files' => 1, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => null],
            ['key' => 'family_card', 'label' => 'Kartu Keluarga', 'active' => true, 'required' => true, 'max_files' => 1, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => null],
            ['key' => 'birth_certificate', 'label' => 'Akta Kelahiran', 'active' => true, 'required' => true, 'max_files' => 1, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => null],
            ['key' => 'photo', 'label' => 'Pas Foto', 'active' => true, 'required' => true, 'max_files' => 1, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => null],
        ];
        $configuration = $service->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->fillForm([
                'report_card' => [UploadedFile::fake()->image('rapor.jpg', 800, 600)],
                'family_card' => [UploadedFile::fake()->image('kk.jpg', 800, 600)],
                'birth_certificate' => [UploadedFile::fake()->image('akta.jpg', 800, 600)],
                'photo' => [UploadedFile::fake()->image('foto.jpg', 400, 600)],
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $registration->refresh();

        $this->assertSame('document_verification', $registration->current_stage);
        $this->assertNotNull($registration->documents_completed_at);
        $this->assertSame(4, $registration->documents()->count());
        $this->assertTrue($registration->documentsComplete());
        $this->assertFalse($registration->documentsComplete(true));

        foreach ($registration->documents as $document) {
            Storage::disk('applicant-private')->assertExists($document->file_path);
            $this->assertGreaterThan(548, (int) Storage::disk('applicant-private')->size($document->file_path));
        }
    }

    public function test_single_slot_fields_and_optional_template_document_save_together(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');

        [$registration, $parent, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);

        $templatePath = 'templates/'.$registration->unit_id.'/supporting.pdf';
        Storage::disk('applicant-private')->put($templatePath, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'] = [
            ['key' => 'family_card', 'label' => 'Kartu Keluarga', 'active' => true, 'required' => true, 'max_files' => 1, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => null],
            ['key' => 'birth_certificate', 'label' => 'Akta Kelahiran', 'active' => true, 'required' => true, 'max_files' => 1, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => null],
            ['key' => 'photo', 'label' => 'Pas Foto', 'active' => true, 'required' => true, 'max_files' => 1, 'formats' => ['png'], 'instructions' => '', 'template_path' => null],
            ['key' => 'supporting_document', 'label' => 'Dokumen Pendukung', 'active' => true, 'required' => false, 'max_files' => 1, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => $templatePath],
        ];

        $configuration = $service->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->fillForm([
                'family_card' => [UploadedFile::fake()->image('kk.jpg', 800, 600)],
                'birth_certificate' => [UploadedFile::fake()->image('akta.jpg', 800, 600)],
                'photo' => [UploadedFile::fake()->image('foto.png', 400, 600)],
                'supporting_document' => [UploadedFile::fake()->image('pendukung.jpg', 800, 600)],
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $registration->refresh();

        $this->assertSame('document_verification', $registration->current_stage);
        $this->assertSame(4, $registration->documents()->count());
        $this->assertTrue($registration->documentsComplete());
    }

    public function test_verified_single_document_upload_is_locked_for_applicant(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');

        [$registration, $parent, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'] = [
            ['key' => 'report_card', 'label' => 'Rapor', 'active' => true, 'required' => true, 'max_files' => 1, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => null],
        ];
        $configuration = $service->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);

        $path = 'documents/'.$registration->id.'/verified.jpg';
        Storage::disk('applicant-private')->put($path, UploadedFile::fake()->image('verified.jpg')->getContent());

        Document::create([
            'registration_id' => $registration->id,
            'requirement_key' => 'report_card',
            'attachment_index' => 0,
            'type' => 'report_card',
            'file_path' => $path,
            'original_name' => 'verified.jpg',
            'file_type' => 'jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => Storage::disk('applicant-private')->size($path),
            'sha256' => hash('sha256', Storage::disk('applicant-private')->get($path)),
            'security_scanned_at' => now(),
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $staff->id,
        ]);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->assertFormFieldIsDisabled('report_card');
    }

    public function test_verified_attachment_is_preserved_when_other_attachment_is_replaced(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');

        [$registration, $parent, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'] = [
            ['key' => 'certificates', 'label' => 'Sertifikat', 'active' => true, 'required' => true, 'max_files' => 2, 'formats' => ['jpg'], 'instructions' => '', 'template_path' => null],
        ];
        $configuration = $service->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);

        $verifiedPath = 'documents/'.$registration->id.'/verified.jpg';
        $replaceablePath = 'documents/'.$registration->id.'/rejected.jpg';
        Storage::disk('applicant-private')->put($verifiedPath, UploadedFile::fake()->image('verified.jpg')->getContent());
        Storage::disk('applicant-private')->put($replaceablePath, UploadedFile::fake()->image('rejected.jpg')->getContent());

        $verified = Document::create([
            'registration_id' => $registration->id,
            'requirement_key' => 'certificates',
            'attachment_index' => 0,
            'type' => 'supporting_document',
            'file_path' => $verifiedPath,
            'original_name' => 'verified.jpg',
            'file_type' => 'jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => Storage::disk('applicant-private')->size($verifiedPath),
            'sha256' => hash('sha256', Storage::disk('applicant-private')->get($verifiedPath)),
            'security_scanned_at' => now(),
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $staff->id,
        ]);

        $replaceable = Document::create([
            'registration_id' => $registration->id,
            'requirement_key' => 'certificates',
            'attachment_index' => 1,
            'type' => 'supporting_document',
            'file_path' => $replaceablePath,
            'original_name' => 'rejected.jpg',
            'file_type' => 'jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => Storage::disk('applicant-private')->size($replaceablePath),
            'sha256' => hash('sha256', Storage::disk('applicant-private')->get($replaceablePath)),
            'security_scanned_at' => now(),
            'is_verified' => false,
            'rejection_reason' => 'Perlu diganti',
        ]);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->assertFormFieldIsEnabled('certificates')
            ->fillForm(['certificates' => [UploadedFile::fake()->image('replacement.jpg')]])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertTrue($verified->fresh()->is_verified);
        $this->assertSame($verifiedPath, $verified->fresh()->file_path);
        $this->assertNull($verified->fresh()->superseded_at);

        $replaceable->refresh();
        $this->assertFalse($replaceable->is_verified);
        $this->assertNull($replaceable->rejection_reason);
        $this->assertSame(1, (int) $replaceable->attachment_index);
        $this->assertNotSame($replaceablePath, $replaceable->file_path);
    }

    public function test_custom_requirement_accepts_multiple_files_and_requires_each_verification(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');
        [$registration, $parent, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'] = [['key' => 'certificates', 'label' => 'Sertifikat Lomba', 'active' => true, 'required' => true, 'max_files' => 2, 'formats' => ['png']]];
        $configuration = $service->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])->fillForm(['certificates' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')]])->call('submit')->assertHasNoFormErrors();
        $this->assertSame(2, $registration->documents()->where('requirement_key', 'certificates')->count());
        $registration->documents()->first()->update(['is_verified' => true]);
        $this->assertFalse($registration->documentsComplete(true));
        $registration->documents()->update(['is_verified' => true]);
        $this->assertTrue($registration->documentsComplete(true));
    }

    public function test_docx_template_is_private_and_macro_content_is_rejected(): void
    {
        Storage::fake('applicant-private');
        [$registration, $parent, $staff] = $this->fixture();
        $path = 'templates/'.$registration->unit_id.'/form.docx';
        Storage::disk('applicant-private')->makeDirectory('templates/'.$registration->unit_id);
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('applicant-private')->path($path), \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body/></w:document>');
        $zip->close();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'][0]['active'] = true;
        $data['document_requirements'][0]['template_path'] = $path;
        $configuration = $service->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->assertSee('Unduh Template Rapor');

        $url = route('registration.template', [$registration, 'report_card']);
        $this->get($url)->assertDownload('rapor.docx');
        $other = User::factory()->create(['is_active' => true]);
        $this->actingAs($other)->get($url)->assertNotFound();
        $zip->open(Storage::disk('applicant-private')->path($path));
        $zip->addFromString('word/vbaProject.bin', 'macro');
        $zip->close();
        $this->expectException(ValidationException::class);
        app(ApplicantUploadSecurity::class)->inspect($path, ['docx']);
    }

    public function test_receipt_is_immutable_and_is_not_issued_before_payment_verification(): void
    {
        [$registration, $parent] = $this->fixture();
        $payment = Payment::create(['registration_id' => $registration->id, 'amount' => 300000, 'status' => 'pending']);
        try {
            app(ReceiptService::class)->issue($payment);
            $this->fail('Pending payment received a receipt');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $payment->update(['status' => 'verified', 'verified_at' => now()]);
        $receipt = app(ReceiptService::class)->issue($payment);
        $payment->update(['amount' => 500000]);
        $this->assertSame($receipt->id, app(ReceiptService::class)->issue($payment)->id);
        $this->assertSame('300000.00', $receipt->fresh()->details['amount']);
        $this->actingAs($parent)->get(route('registration.receipt', [$registration, $receipt]))->assertOk()->assertSeeText('300.000');
    }

    public function test_database_channel_deduplicates_retry_without_resetting_read_status(): void
    {
        [, $parent] = $this->fixture();
        $notification = new SpmbDatabaseNotification('example', 'workflow', 'Contoh');
        $notification->id = (string) Str::uuid();
        $channel = app(IdempotentDatabaseChannel::class);
        $first = $channel->send($parent, $notification);
        $first->markAsRead();
        $channel->send($parent, $notification);
        $this->assertSame(1, $parent->notifications()->whereKey($notification->id)->count());
        $this->assertNotNull($first->fresh()->read_at);
    }

    public function test_template_cannot_escape_its_unit_directory(): void
    {
        Storage::fake('applicant-private');
        [$registration, , $staff] = $this->fixture();
        Storage::disk('applicant-private')->put('templates/other/form.pdf', '%PDF-1.4 example');
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'][0]['template_path'] = 'templates/'.$registration->unit_id.'/../other/form.pdf';

        $this->expectException(ValidationException::class);
        $service->save($draft, $staff, $data, true);
    }

    public function test_existing_registration_uses_latest_document_label_and_instructions_without_duplicate_summary_block(): void
    {
        Storage::fake('local');
        Storage::fake('applicant-private');

        [$registration, $parent, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);

        $old = $service->initialize($registration->unit);
        $registration->update(['unit_configuration_id' => $old->id]);

        $draft = $service->draft($registration->unit, $staff);
        $data = $draft->toArray();
        $data['document_requirements'] = collect($data['document_requirements'])
            ->map(function (array $requirement): array {
                if (($requirement['key'] ?? null) === 'supporting_document') {
                    $requirement['label'] = 'Surat Pernyataan';
                    $requirement['instructions'] = 'Unduh template, isi, tanda tangani, lalu unggah kembali.';
                }

                return $requirement;
            })
            ->all();

        $service->save($draft, $staff, $data, true);

        $requirement = collect($registration->fresh()->documentRequirements())
            ->firstWhere('key', 'supporting_document');

        $this->assertSame('Surat Pernyataan', $requirement['label']);
        $this->assertSame(
            'Unduh template, isi, tanda tangani, lalu unggah kembali.',
            $requirement['instructions'],
        );

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $page = Livewire::test(DocumentsUpload::class, ['registration' => $registration->uuid])
            ->assertSee('Surat Pernyataan')
            ->assertSee('Unduh template, isi, tanda tangani, lalu unggah kembali.')
            ->assertSee('Belum diunggah')
            ->assertDontSee('Maksimum 1 lampiran');

        $this->assertStringContainsString('Dokumen Pendaftaran', $page->html());
        $this->assertStringNotContainsString('Wajib · Maksimum', $page->html());
    }

    private function fixture(): array
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'SD Test', 'code' => 'SD', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'tu', 'unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('tu');
        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('pendaftar');
        $opening = RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open', 'registration_fee' => 0]);
        $registration = Registration::create(['user_id' => $parent->id, 'unit_id' => $unit->id, 'registration_opening_id' => $opening->id, 'full_name' => 'Peserta Test', 'nik' => '3273010101010001', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung', 'current_stage' => 'documents']);

        return [$registration, $parent, $staff];
    }
}
