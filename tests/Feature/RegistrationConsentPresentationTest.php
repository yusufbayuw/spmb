<?php

namespace Tests\Feature;

use App\Services\RegistrationConsentService;
use Tests\TestCase;

class RegistrationConsentPresentationTest extends TestCase
{
    public function test_rich_editor_structure_survives_consent_sanitization(): void
    {
        $html = app(RegistrationConsentService::class)->sanitizeHtml(
            '<h2>Judul Utama</h2><p><strong>Tebal</strong><br><em>Miring</em> <u>Garis</u></p>'
            .'<ol><li>Nomor satu</li><li>Nomor dua</li></ol>'
            .'<ul><li>Butir</li></ul>'
            .'<blockquote>Kutipan</blockquote>'
            .'<a href="https://example.test">Tautan</a>',
        );

        $this->assertStringContainsString('<h2>Judul Utama</h2>', $html);
        $this->assertStringContainsString('<strong>Tebal</strong>', $html);
        $this->assertStringContainsString('<br>', $html);
        $this->assertStringContainsString('<em>Miring</em>', $html);
        $this->assertStringContainsString('<u>Garis</u>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>Nomor satu</li>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<blockquote>Kutipan</blockquote>', $html);
        $this->assertStringContainsString('href="https://example.test"', $html);
    }

    public function test_consent_view_has_explicit_rich_text_styles(): void
    {
        $rendered = view('filament.applicant.registration-consent', [
            'content' => '<h2>Judul</h2><ol><li>Pertama</li></ol><p><strong>Isi</strong></p>',
        ])->render();

        $this->assertStringContainsString('registration-consent-content', $rendered);
        $this->assertStringContainsString('.registration-consent-content ol', $rendered);
        $this->assertStringContainsString('list-style: decimal', $rendered);
        $this->assertStringContainsString('.registration-consent-content ul', $rendered);
        $this->assertStringContainsString('list-style: disc', $rendered);
        $this->assertStringContainsString('<h2>Judul</h2>', $rendered);
        $this->assertStringContainsString('<li>Pertama</li>', $rendered);
    }
}
