<?php

namespace Tests\Feature;

use App\Mail\AnnouncementPublishedMail;
use App\Mail\VirtualAccountMail;
use App\Models\Announcement;
use App\Models\Payment;
use App\Services\AccountConsentService;
use App\Services\AdmissionTerminology;
use App\Services\AppBrandingService;
use App\Support\SpmbOperationalMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionTerminologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_modes_drive_public_branding_pwa_and_mail(): void
    {
        $cases = [
            SpmbOperationalMode::K12 => ['SPMB', 'Pendaftaran Murid Baru', 'PMB'],
            SpmbOperationalMode::HIGHER_EDUCATION => ['PMB', 'Pendaftaran Mahasiswa Baru', 'SPMB'],
            SpmbOperationalMode::MIXED => ['SPMB & PMB', 'Pendaftaran Peserta Baru', null],
        ];

        foreach ($cases as $mode => [$label, $registrationTitle, $forbidden]) {
            config()->set('spmb.operations.mode', $mode);
            config()->set('spmb.portal.name', 'Nama Portal Lama SPMB');

            $terms = app(AdmissionTerminology::class);
            $branding = app(AppBrandingService::class);
            $branding->refresh();

            $this->assertSame($label, $terms->portalName());
            $this->assertSame($registrationTitle, $terms->registrationTitle());
            $this->assertSame($label, $branding->portalName());
            $this->assertSame($label, $branding->effective()['name']);
            $this->assertSame('Virtual Account Pendaftaran '.$label, (new VirtualAccountMail(new Payment))->build()->subject);

            $announcement = new Announcement;
            $announcement->title = 'Pengumuman SPMB';
            $this->assertSame('Pengumuman Hasil '.$label, (new AnnouncementPublishedMail($announcement))->build()->subject);

            $this->get('/manifest.webmanifest')
                ->assertOk()
                ->assertJsonPath('name', $label)
                ->assertJsonPath('short_name', $label);

            foreach ([
                $terms->virtualAccountSubject(),
                $terms->announcementTitle(),
                $terms->dashboardButton(),
                $terms->committee(),
                $terms->configurationNavigation(),
                $terms->documentFooter(),
                $terms->paymentDescription(),
                $terms->notificationFallback(),
                $terms->applicantOpeningDescription(),
            ] as $surface) {
                $this->assertStringContainsString($label, $surface);
                if ($forbidden !== null) {
                    $this->assertDoesNotMatchRegularExpression('/(?<![A-Za-z])'.preg_quote($forbidden, '/').'(?![A-Za-z])/u', $surface);
                }
            }
        }
    }

    public function test_legacy_defaults_are_relabelled_only_for_presentation(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);
        $terms = app(AdmissionTerminology::class);

        $this->assertSame('Pengumuman Hasil PMB', $terms->announcementDisplayTitle('Pengumuman Hasil SPMB'));
        $this->assertSame('Pengumuman hasil PMB tersedia', $terms->notificationDisplayTitle('Pengumuman hasil SPMB tersedia'));
        $this->assertSame('Kelulusan Gelombang Khusus', $terms->announcementDisplayTitle('Kelulusan Gelombang Khusus'));
        $this->assertSame('Peringatan Admin', $terms->notificationDisplayTitle('Peringatan Admin'));
    }

    public function test_new_legal_default_titles_match_mode_without_rewriting_historical_snapshots(): void
    {
        foreach ([
            SpmbOperationalMode::K12 => 'SPMB',
            SpmbOperationalMode::HIGHER_EDUCATION => 'PMB',
            SpmbOperationalMode::MIXED => 'SPMB & PMB',
        ] as $mode => $label) {
            config()->set('spmb.operations.mode', $mode);
            $defaults = app(AccountConsentService::class)->defaults();

            $this->assertSame('Ketentuan Penggunaan Platform '.$label, $defaults['terms_title']);
            $this->assertSame('Kebijakan Privasi Platform '.$label, $defaults['privacy_title']);
            $this->assertStringContainsString('Platform '.$label, $defaults['required_confirmation_text']);
            $this->assertStringNotContainsString('Platform SPMB', $defaults['terms_content']);
            $this->assertStringNotContainsString('Platform SPMB', $defaults['privacy_content']);
        }
    }
}
