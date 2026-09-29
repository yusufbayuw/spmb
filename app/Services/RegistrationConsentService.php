<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationConsent;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Validation\ValidationException;

class RegistrationConsentService
{
    private const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        'h2',
        'h3',
        'ul',
        'ol',
        'li',
        'blockquote',
        'a',
    ];

    private const DROP_WITH_CONTENT = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'svg',
        'math',
    ];

    /**
     * @return array{enabled:bool,title:string,content:string,confirmation_text:string}
     */
    public function defaultConfiguration(?Unit $unit = null): array
    {
        $isSmp = mb_strtoupper(trim((string) ($unit?->code ?? ''))) === 'SMP';

        return [
            'enabled' => true,
            'title' => 'Persetujuan Privasi & Data Pribadi — {{ unit_name }} {{ academic_year }}',
            'content' => $isSmp ? $this->smpDefaultContent() : $this->genericDefaultContent(),
            'confirmation_text' => 'Saya/Kami telah membaca, memahami, dan menyetujui Syarat dan Ketentuan serta Kebijakan Privasi {{ unit_name }}.',
        ];
    }

    /**
     * @return array{enabled:bool,title:string,content:string,confirmation_text:string}
     */
    public function normalizeConfiguration(mixed $configuration, ?Unit $unit = null): array
    {
        $defaults = $this->defaultConfiguration($unit);
        $configuration = is_array($configuration) ? $configuration : [];

        return [
            'enabled' => (bool) ($configuration['enabled'] ?? $defaults['enabled']),
            'title' => filled($configuration['title'] ?? null)
                ? trim((string) $configuration['title'])
                : $defaults['title'],
            'content' => $this->sanitizeHtml(
                filled($configuration['content'] ?? null)
                    ? (string) $configuration['content']
                    : $defaults['content'],
            ),
            'confirmation_text' => filled($configuration['confirmation_text'] ?? null)
                ? trim((string) $configuration['confirmation_text'])
                : $defaults['confirmation_text'],
        ];
    }

    public function isEnabled(UnitConfiguration $configuration, ?Unit $unit = null): bool
    {
        return $this->normalizeConfiguration($configuration->pre_form_consent, $unit)['enabled'];
    }

    /**
     * @return array{enabled:bool,title:string,content:string,confirmation_text:string,content_hash:string}
     */
    public function render(UnitConfiguration $configuration, RegistrationOpening $opening): array
    {
        $opening->loadMissing('unit');
        $configured = $this->normalizeConfiguration($configuration->pre_form_consent, $opening->unit);

        $plainTokens = $this->tokens($opening, false);
        $htmlTokens = $this->tokens($opening, true);

        $rendered = [
            'enabled' => $configured['enabled'],
            'title' => strtr($configured['title'], $plainTokens),
            'content' => strtr($configured['content'], $htmlTokens),
            'confirmation_text' => strtr($configured['confirmation_text'], $plainTokens),
        ];

        $rendered['content_hash'] = hash('sha256', (string) json_encode([
            'title' => $rendered['title'],
            'content' => $rendered['content'],
            'confirmation_text' => $rendered['confirmation_text'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $rendered;
    }

    public function accept(
        User $user,
        RegistrationOpening $opening,
        UnitConfiguration $configuration,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): RegistrationConsent {
        if ((int) $configuration->unit_id !== (int) $opening->unit_id || $configuration->status !== 'published') {
            throw ValidationException::withMessages([
                'privacy_consent' => 'Konfigurasi persetujuan tidak sesuai dengan pembukaan pendaftaran.',
            ]);
        }

        $rendered = $this->render($configuration, $opening);

        if (! $rendered['enabled']) {
            throw ValidationException::withMessages([
                'privacy_consent' => 'Persetujuan sebelum formulir sedang tidak diwajibkan.',
            ]);
        }

        return RegistrationConsent::create([
            'user_id' => $user->id,
            'unit_id' => $opening->unit_id,
            'registration_opening_id' => $opening->id,
            'unit_configuration_id' => $configuration->id,
            'title_snapshot' => $rendered['title'],
            'content_snapshot' => $rendered['content'],
            'confirmation_snapshot' => $rendered['confirmation_text'],
            'content_hash' => $rendered['content_hash'],
            'accepted_at' => now(),
            'ip_address' => filled($ipAddress) ? mb_substr((string) $ipAddress, 0, 45) : null,
            'user_agent' => filled($userAgent) ? mb_substr((string) $userAgent, 0, 2000) : null,
        ]);
    }

    public function pendingFor(
        ?string $uuid,
        User $user,
        RegistrationOpening $opening,
        UnitConfiguration $configuration,
    ): ?RegistrationConsent {
        if (blank($uuid)) {
            return null;
        }

        return RegistrationConsent::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->where('unit_id', $opening->unit_id)
            ->where('registration_opening_id', $opening->id)
            ->where('unit_configuration_id', $configuration->id)
            ->whereNull('registration_id')
            ->first();
    }

    public function attachToRegistration(int $consentId, Registration $registration): RegistrationConsent
    {
        $consent = RegistrationConsent::query()->lockForUpdate()->findOrFail($consentId);

        if ($consent->registration_id !== null
            || (int) $consent->user_id !== (int) $registration->user_id
            || (int) $consent->unit_id !== (int) $registration->unit_id
            || (int) $consent->registration_opening_id !== (int) $registration->registration_opening_id
            || (int) $consent->unit_configuration_id !== (int) $registration->unit_configuration_id) {
            throw ValidationException::withMessages([
                'privacy_consent' => 'Persetujuan tidak dapat digunakan untuk pendaftaran ini.',
            ]);
        }

        $consent->update(['registration_id' => $registration->id]);

        return $consent;
    }

    public function sanitizeHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<div id="consent-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        $root = (new DOMXPath($document))->query('//*[@id="consent-root"]')->item(0);

        if (! $root instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return '';
        }

        $this->sanitizeNode($root);

        $sanitized = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $sanitized .= $document->saveHTML($child);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return trim($sanitized);
    }

    private function sanitizeNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = mb_strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeNode($child);

                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            $allowedAttributes = $tag === 'a' ? ['href', 'target', 'rel'] : [];
            $attributeNames = [];
            foreach ($child->attributes as $attribute) {
                $attributeNames[] = $attribute->nodeName;
            }

            foreach ($attributeNames as $attributeName) {
                if (! in_array(mb_strtolower($attributeName), $allowedAttributes, true)) {
                    $child->removeAttribute($attributeName);
                }
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if ($href !== '' && ! preg_match('/^(https?:\\/\\/|mailto:|tel:|#|\\/)/i', $href)) {
                    $child->removeAttribute('href');
                }

                if ($child->hasAttribute('target') && $child->getAttribute('target') !== '_blank') {
                    $child->removeAttribute('target');
                }

                if ($child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $child->removeAttribute('rel');
                }
            }

            $this->sanitizeNode($child);
        }
    }

    /**
     * @return array<string, string>
     */
    private function tokens(RegistrationOpening $opening, bool $escapeHtml): array
    {
        $values = [
            'unit_name' => (string) ($opening->unit?->name ?: 'Unit Pendidikan'),
            'academic_year' => (string) ($opening->academic_year ?: ''),
            'wave' => (string) ($opening->wave ?: ''),
            'participant_label' => $opening->unit?->isHigherEducation() ? 'calon mahasiswa' : 'calon murid',
        ];

        if ($escapeHtml) {
            $values = array_map(fn (string $value): string => e($value), $values);
        }

        $tokens = [];
        foreach ($values as $key => $value) {
            $tokens['{{ '.$key.' }}'] = $value;
            $tokens['{{'.$key.'}}'] = $value;
        }

        return $tokens;
    }

    private function smpDefaultContent(): string
    {
        return <<<'HTML'
<p><strong>FORMULIR PERSETUJUAN DATA PRIBADI CALON MURID</strong></p>
<ol>
<li>Kami selaku orang tua/wali memberikan persetujuan kepada {{ unit_name }} untuk mengumpulkan dan memproses Data Pribadi kami dan {{ participant_label }}, termasuk nama lengkap, alamat, nomor kontak, data orang tua/wali, data sekolah, data akademik, serta data lain yang diperlukan dalam proses pendaftaran SPMB.</li>
<li>{{ unit_name }} akan menyimpan Data Pribadi orang tua/wali dan {{ participant_label }} selama diperlukan untuk proses SPMB, administrasi penerimaan, dan kebutuhan penyelenggaraan pendidikan sesuai dengan ketentuan yang berlaku.</li>
<li>{{ unit_name }} berkomitmen menjaga keamanan, kerahasiaan, dan hak {{ participant_label }} serta orang tua/wali sebagai subjek Data Pribadi sesuai dengan Undang-Undang No. 27 Tahun 2022 tentang Pelindungan Data Pribadi dan kebijakan internal sekolah.</li>
<li>Dalam rangka pelaksanaan SPMB, Data Pribadi dapat diproses oleh pihak ketiga yang ditunjuk oleh {{ unit_name }}, seperti penyedia sistem pendaftaran atau layanan tes/asesmen, sepanjang diperlukan untuk pelaksanaan SPMB dan sesuai dengan tujuan yang telah diinformasikan.</li>
<li>Apabila {{ unit_name }} akan memproses Data Pribadi untuk tujuan yang berbeda dari yang tercantum dalam persetujuan ini, sekolah akan memberikan informasi dan meminta persetujuan sesuai dengan ketentuan yang berlaku.</li>
<li>Kami menyatakan bahwa seluruh Data Pribadi dan informasi yang diberikan dalam formulir pendaftaran SPMB adalah benar, lengkap, dan terkini.</li>
</ol>
HTML;
    }

    private function genericDefaultContent(): string
    {
        return <<<'HTML'
<p><strong>PERSETUJUAN PRIVASI DAN PEMROSESAN DATA PRIBADI</strong></p>
<ol>
<li>Saya/Kami sebagai pendaftar atau orang tua/wali memberikan persetujuan kepada {{ unit_name }} untuk mengumpulkan dan memproses Data Pribadi yang diperlukan dalam proses SPMB {{ academic_year }}.</li>
<li>Data yang dapat diproses meliputi identitas {{ participant_label }}, data orang tua/wali, alamat dan kontak, riwayat pendidikan, data akademik, dokumen pendukung, hasil asesmen, serta data lain yang secara wajar diperlukan untuk penerimaan peserta didik/mahasiswa.</li>
<li>Data digunakan untuk pendaftaran, verifikasi, komunikasi, pembayaran bila berlaku, seleksi, pengumuman hasil, administrasi penerimaan, dan penyelenggaraan pendidikan sesuai kebutuhan serta ketentuan yang berlaku.</li>
<li>{{ unit_name }} akan menyimpan Data Pribadi selama diperlukan untuk tujuan tersebut dan menerapkan langkah yang wajar untuk menjaga keamanan serta kerahasiaannya.</li>
<li>Dalam pelaksanaan SPMB, Data Pribadi dapat diproses oleh penyedia sistem, pembayaran, komunikasi, tes/asesmen, atau pihak ketiga lain yang ditunjuk sepanjang diperlukan untuk tujuan yang telah diinformasikan.</li>
<li>Apabila terdapat tujuan pemrosesan baru yang memerlukan persetujuan tambahan, {{ unit_name }} akan memberikan informasi dan meminta persetujuan sesuai ketentuan yang berlaku.</li>
<li>Saya/Kami memahami hak sebagai subjek Data Pribadi sesuai Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi dan ketentuan lain yang berlaku.</li>
<li>Saya/Kami menyatakan bahwa data dan informasi yang diberikan dalam formulir pendaftaran adalah benar, lengkap, dan terkini.</li>
</ol>
HTML;
    }
}
