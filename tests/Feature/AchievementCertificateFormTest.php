<?php

namespace Tests\Feature;

use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\EditRegistration;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantFileStorage;
use App\Services\PendingAchievementUpload;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AchievementCertificateFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_upload_repeater_can_rerender_without_accessing_an_uninitialized_action_container(): void
    {
        [$applicant, $opening, $pathway] = $this->fixture();

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->set('data.registration_pathway_uuid', $pathway->uuid)
            ->set('data.achievements', [[
                'title' => 'Olimpiade Sains',
                'level' => 'Nasional',
            ]])
            ->assertSee('Sertifikat / Bukti Prestasi')
            ->assertDontSee('Lihat Bukti Prestasi Tersimpan');
    }

    public function test_three_new_private_certificates_reload_using_authenticated_urls_not_public_storage(): void
    {
        [$applicant] = $this->fixture();
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);

        $this->actingAs($applicant);

        $uploads = app(PendingAchievementUpload::class);
        $paths = [
            'sertifikat-pertama.pdf' => '%PDF-1.4 first',
            'sertifikat-kedua.pdf' => '%PDF-1.4 second',
            'sertifikat-ketiga.png' => "\x89PNG\r\n\x1a\n",
        ];

        $urls = [];

        foreach ($paths as $filename => $contents) {
            $path = 'pre-registration/'.$applicant->id.'/achievements/'.$filename;
            Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, $contents);

            $metadata = $uploads->uploadedFile($path, 'Bukti '.$filename);

            $this->assertNotNull($metadata);
            $this->assertSame('Bukti '.$filename, $metadata['name']);
            $this->assertSame(strlen($contents), $metadata['size']);
            $this->assertSame(
                route('files.applicant.pending-achievement-certificate', ['filename' => $filename]),
                $metadata['url'],
            );
            $this->assertStringNotContainsString('/storage/', $metadata['url']);

            $response = $this->get($metadata['url'])->assertOk();
            $this->assertStringContainsString(
                'attachment',
                (string) $response->headers->get('Content-Disposition'),
            );

            $urls[] = $metadata['url'];
            Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertExists($path);
        }

        $this->assertCount(3, $urls);
        $this->assertNull($uploads->uploadedFile(
            'pre-registration/99999/achievements/stolen.pdf',
            null,
        ));

        $other = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $other->assignRole('pendaftar');

        // Another applicant never gets access to the first applicant's files.
        $this->actingAs($other)->get($urls[0])->assertNotFound();
        $this->assertNull($uploads->uploadedFile(
            'pre-registration/'.$applicant->id.'/achievements/sertifikat-pertama.pdf',
            null,
        ));

        $this->actingAs($applicant)
            ->get(route('files.applicant.pending-achievement-certificate', ['filename' => 'missing.pdf']))
            ->assertNotFound();
    }

    public function test_submit_with_three_required_private_certificates_creates_registration(): void
    {
        [$applicant, $opening, $pathway] = $this->fixture();
        $this->requireAchievementCertificates($opening, $pathway);
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);

        $achievements = [];
        for ($number = 1; $number <= 3; $number++) {
            $filename = 'piagam-'.$number.'.pdf';
            $path = 'pre-registration/'.$applicant->id.'/achievements/'.$filename;
            Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put(
                $path,
                "%PDF-1.4\nPiagam prestasi ke-{$number}\n",
            );

            $achievements[] = [
                'title' => 'Prestasi '.$number,
                'level' => 'Nasional',
                'certificate_path' => $path,
                'certificate_original_name' => $filename,
            ];
        }

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->fillForm($this->registrationFormData($pathway, $achievements, '3273010101010097'))
            ->call('create')
            ->assertHasNoFormErrors();

        $registration = Registration::query()
            ->where('nik', '3273010101010097')
            ->firstOrFail();

        $this->assertSame('submitted', $registration->status);
        $this->assertCount(3, $registration->achievements);
        foreach ($achievements as $achievement) {
            $this->assertTrue($registration->achievements()
                ->where('certificate_path', $achievement['certificate_path'])
                ->exists());
            Storage::disk(ApplicantFileStorage::PRIVATE_DISK)
                ->assertExists($achievement['certificate_path']);
        }
    }

    public function test_rejected_achievement_certificate_reports_a_specific_error_and_never_submits(): void
    {
        [$applicant, $opening, $pathway] = $this->fixture();
        $this->requireAchievementCertificates($opening, $pathway);
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);

        $path = 'pre-registration/'.$applicant->id.'/achievements/rejected.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put(
            $path,
            "%PDF-1.4\n<script>unsafe content</script>\n",
        );

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->fillForm($this->registrationFormData($pathway, [[
                'title' => 'Prestasi dengan sertifikat ditolak',
                'level' => 'Nasional',
                'certificate_path' => $path,
                'certificate_original_name' => 'rejected.pdf',
            ]], '3273010101010098'))
            ->call('create')
            ->assertHasErrors(['achievements.0.certificate_path']);

        $this->assertDatabaseMissing('registrations', ['nik' => '3273010101010098']);
    }

    private function requireAchievementCertificates(RegistrationOpening $opening, RegistrationPathway $pathway): void
    {
        $staff = User::query()->where('unit_id', $opening->unit_id)->firstOrFail();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($opening->unit, $staff);
        $configuration = $draft->toArray();

        $configuration['achievements_enabled'] = true;
        $configuration['achievement_settings'] = [
            'required' => true,
            'max_entries' => 3,
            'levels' => ['Nasional'],
            'pathway_uuids' => [$pathway->uuid],
            'certificate_mode' => 'required',
        ];
        data_set($configuration, 'pre_form_consent.enabled', false);

        $service->save($draft, $staff, $configuration, true);
    }

    /** @param array<int, array<string, mixed>> $achievements */
    private function registrationFormData(RegistrationPathway $pathway, array $achievements, string $nik): array
    {
        return [
            'registration_pathway_uuid' => $pathway->uuid,
            'registrant_type' => 'self',
            'full_name' => 'Peserta Uji Prestasi',
            'nik' => $nik,
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2014-01-01',
            'home_address' => 'Bandung',
            'parentInfo' => [
                'father_name' => 'Ayah Uji',
                'mother_name' => 'Ibu Uji',
            ],
            // FileUpload keeps an array keyed by FilePond upload IDs until
            // Filament dehydrates it to a single stored path on submit.
            'achievements' => array_map(function (array $item): array {
                if (is_string($item['certificate_path'] ?? null)) {
                    $item['certificate_path'] = [
                        (string) \Illuminate\Support\Str::uuid() => $item['certificate_path'],
                    ];
                }

                return $item;
            }, $achievements),
        ];
    }

    public function test_existing_certificate_remains_viewable_after_repeater_rerender(): void
    {
        [$applicant, , , $registration] = $this->fixture();
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);

        $path = 'pre-registration/'.$applicant->id.'/achievements/sertifikat.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, "%PDF-1.4\ncertificate");

        $achievement = $registration->achievements()->create([
            'title' => 'Olimpiade Matematika',
            'level' => 'Nasional',
            'certificate_path' => $path,
            'certificate_original_name' => 'piagam.pdf',
        ]);

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(EditRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertSee('Lihat Bukti Prestasi Tersimpan')
            ->assertSee(route('files.applicant.achievements.certificate', $achievement), escape: false)
            ->set('data.full_name', 'Peserta Diperbarui')
            ->assertSee('Lihat Bukti Prestasi Tersimpan');

        $this->get(route('files.applicant.achievements.certificate', $achievement))
            ->assertOk();

        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertExists($path);
    }

    /** @return array{User, RegistrationOpening, RegistrationPathway, Registration} */
    private function fixture(): array
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMP Uji Sertifikat',
            'code' => 'SMP-ACH',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');

        $applicant = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $applicant->assignRole('pendaftar');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);
        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Prestasi',
        ]);

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $settings = $draft->toArray();
        $settings['achievements_enabled'] = true;
        $settings['achievement_settings'] = [
            'required' => false,
            'max_entries' => 3,
            'pathway_uuids' => [$pathway->uuid],
            'levels' => ['Nasional'],
            'certificate_mode' => 'optional',
        ];
        $configuration = $service->save($draft, $staff, $settings, true);

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'unit_configuration_id' => $configuration->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'full_name' => 'Peserta Uji',
            'nik' => '3273010101010001',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2012-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);

        return [$applicant, $opening, $pathway, $registration];
    }
}
