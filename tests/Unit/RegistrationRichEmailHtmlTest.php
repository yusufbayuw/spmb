<?php

namespace Tests\Unit;

use App\Services\RegistrationRichEmailHtml;
use Tests\TestCase;

class RegistrationRichEmailHtmlTest extends TestCase
{
    public function test_formatting_survives_html_sanitization(): void
    {
        $html = app(RegistrationRichEmailHtml::class)->sanitize(
            '<h2>Judul</h2><p><strong>Tebal</strong> dan <em>Miring</em></p><ul><li>Poin</li></ul>'
        );

        $this->assertStringContainsString('<h2>Judul</h2>', $html);
        $this->assertStringContainsString('<strong>Tebal</strong>', $html);
        $this->assertStringContainsString('<em>Miring</em>', $html);
        $this->assertStringContainsString('<li>Poin</li>', $html);
    }

    public function test_unsafe_html_is_removed_before_email_delivery(): void
    {
        $html = app(RegistrationRichEmailHtml::class)->sanitize(
            '<p onclick="go()">Pesan</p><script>alert(1)</script><a href="javascript:alert(1)">Link</a>'
        );

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('Pesan', $html);
    }

    public function test_plain_text_callers_keep_line_breaks(): void
    {
        $html = app(RegistrationRichEmailHtml::class)->sanitize("Baris pertama\nBaris kedua");

        $this->assertStringContainsString('Baris pertama', $html);
        $this->assertMatchesRegularExpression('/<br\s*\/?\s*>/i', $html);
        $this->assertStringContainsString('Baris kedua', $html);
    }
}
