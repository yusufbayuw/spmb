<?php

namespace Tests\Feature;

use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\Registration;
use App\Models\RegistrationConsent;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\RegistrationConsentService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_smp_uses_specific_template_generic_units_have_default_and_rich_html_is_sanitized(): void
    {
        $service = app(RegistrationConsentService::class);
        $smp = Unit::make([
            'name' => 'SMP Taruna Test',
            'code' => 'SMP',
            'institution_type' => 'school',
        ]);
        $generic = Unit::make([
            'name' => 'SMA Taruna Test',
            'code' => 'SMA',
            'institution_type' => 'school',
        ]);

        $smpDefault = $service->defaultConfiguration($smp);
        $genericDefault = $service->defaultConfiguration($generic);
        $sanitized = $service->normalizeConfiguration([
            'enabled' => true,
            'title' => 'Persetujuan',
            'content' => '<script>alert(1)</script><p onclick="alert(2)">Isi <a href="javascript:alert(3)">tautan</a></p>',
            'confirmation_text' => 'Saya setuju.',
        ], $generic);

        $this->assertStringContainsString('Undang-Undang No. 27 Tahun 2022', $smpDefault['content']);
        $this->assertStringContainsString('penyedia sistem, pembayaran, komunikasi', $genericDefault['content']);
        $this->assertStringNotContainsString('<script', $sanitized['content']);
        $this->assertStringNotContainsString('onclick=', $sanitized['content']);
        $this->assertStringNotContainsString('javascript:', $sanitized['content']);
        $this->assertStringContainsString('<p>Isi <a>tautan</a></p>', $sanitized['content']);
    }

    public function test_applicant_must_accept_pre_form_consent_and_registration_keeps_snapshot(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMP Taruna Test',
            'code' => 'SMP',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);
        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);
        $applicant = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $applicant->assignRole('pendaftar');

        $formData = [
            'registration_pathway_uuid' => $pathway->uuid,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'full_name' => 'Peserta Consent',
            'nik' => '3273010101010091',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2014-01-01',
            'home_address' => 'Bandung',
            'parentInfo' => [
                'father_name' => 'Ayah Consent',
                'mother_name' => 'Ibu Consent',
            ],
        ];

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->fillForm($formData)
            ->call('create')
            ->assertHasErrors(['privacy_consent']);

        $this->assertDatabaseMissing('registrations', ['nik' => '3273010101010091']);
        $this->assertDatabaseCount('registration_consents', 0);

        $page = Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->assertSee('FORMULIR PERSETUJUAN DATA PRIBADI CALON MURID')
            ->assertSee('Undang-Undang No. 27 Tahun 2022')
            ->setActionData(['accepted' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('registration_consents', 1);

        $page->fillForm($formData)
            ->call('create')
            ->assertHasNoFormErrors();

        $registration = Registration::query()
            ->where('nik', '3273010101010091')
            ->with('consent.configuration')
            ->firstOrFail();
        $consent = $registration->consent;

        $this->assertNotNull($consent);
        $this->assertSame($registration->id, $consent->registration_id);
        $this->assertSame($registration->unit_configuration_id, $consent->unit_configuration_id);
        $this->assertSame($applicant->id, $consent->user_id);
        $this->assertStringContainsString('SMP Taruna Test', $consent->content_snapshot);
        $this->assertStringContainsString('2027/2028', $consent->title_snapshot);
        $this->assertSame(64, strlen($consent->content_hash));
        $this->assertNotNull($consent->accepted_at);
    }

    public function test_disabled_pre_form_consent_allows_registration_without_consent_record(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMA Taruna Test',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);
        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');
        $applicant = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $applicant->assignRole('pendaftar');

        $configurationService = app(UnitConfigurationService::class);
        $configurationService->initialize($unit);
        $draft = $configurationService->draft($unit, $staff);
        $data = $draft->toArray();
        $data['pre_form_consent']['enabled'] = false;
        $configurationService->save($draft, $staff, $data, true);

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->fillForm([
                'registration_pathway_uuid' => $pathway->uuid,
                'registrant_type' => 'self',
                'full_name' => 'Peserta Tanpa Consent',
                'nik' => '3273010101010092',
                'gender' => 'P',
                'birth_place' => 'Bandung',
                'birth_date' => '2014-01-01',
                'home_address' => 'Bandung',
                'parentInfo' => [
                    'father_name' => 'Ayah',
                    'mother_name' => 'Ibu',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('registrations', ['nik' => '3273010101010092']);
        $this->assertSame(0, RegistrationConsent::query()->count());
    }
}
