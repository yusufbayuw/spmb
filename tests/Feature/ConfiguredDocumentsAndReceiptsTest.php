<?php

namespace Tests\Feature;

use App\Filament\Applicant\Pages\DocumentsUpload;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguredDocumentsAndReceiptsTest extends TestCase
{
    use RefreshDatabase;

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
        $url = route('registration.template', [$registration, 'report_card']);
        $this->actingAs($parent)->get($url)->assertDownload('form.docx');
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
