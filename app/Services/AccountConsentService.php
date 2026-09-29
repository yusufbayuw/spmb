<?php

namespace App\Services;

use App\Models\AccountConsentPolicy;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountConsentService
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'terms_title' => 'Ketentuan Penggunaan Platform SPMB',
            'terms_content' => <<<'HTML'
<p>Dengan membuat dan menggunakan akun pada Platform SPMB, pengguna memahami dan menyetujui ketentuan berikut:</p>
<ol>
<li>Akun digunakan untuk mengakses layanan penerimaan peserta didik/mahasiswa dan fitur terkait yang tersedia pada Platform SPMB.</li>
<li>Pengguna bertanggung jawab memberikan informasi akun yang benar, menjaga kerahasiaan kredensial, dan segera mengamankan akun apabila mengetahui adanya penggunaan tanpa izin.</li>
<li>Pengguna tidak diperkenankan menyalahgunakan platform, mengganggu layanan, mencoba memperoleh akses yang tidak berwenang, atau menggunakan akun untuk tindakan yang bertentangan dengan ketentuan yang berlaku.</li>
<li>Pembuatan akun tidak dengan sendirinya berarti pendaftaran pada suatu unit telah diterima. Setiap proses SPMB tetap mengikuti pembukaan, persyaratan, tahapan, dan keputusan pada unit/program yang dipilih.</li>
<li>Fitur, tampilan, dan mekanisme layanan dapat diperbarui untuk menjaga keamanan, keandalan, dan kebutuhan operasional SPMB.</li>
</ol>
HTML,
            'privacy_title' => 'Kebijakan Privasi Platform SPMB',
            'privacy_content' => <<<'HTML'
<p>Platform SPMB memproses Data Pribadi yang diperlukan untuk menyediakan dan mengamankan akun pengguna.</p>
<ol>
<li>Data akun dapat meliputi nama, alamat email, nomor telepon apabila diberikan, informasi autentikasi, serta data teknis yang secara wajar diperlukan untuk keamanan dan operasional layanan.</li>
<li>Data tersebut digunakan untuk membuat dan mengelola akun, autentikasi, verifikasi, pemulihan akun, komunikasi layanan, keamanan sistem, pencatatan aktivitas yang relevan, dan dukungan operasional.</li>
<li>Data dapat diproses oleh penyedia layanan teknologi yang membantu pengoperasian platform sepanjang diperlukan untuk layanan yang telah diinformasikan.</li>
<li>Data disimpan selama diperlukan untuk tujuan layanan, keamanan, pemenuhan kewajiban yang berlaku, serta penanganan hak pengguna sebagai subjek Data Pribadi.</li>
<li>Pengelolaan Data Pribadi memperhatikan ketentuan yang berlaku, termasuk Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi.</li>
<li>Persetujuan akun ini berbeda dari persetujuan pemrosesan data SPMB. Saat pengguna memilih pembukaan pendaftaran tertentu, sistem dapat meminta persetujuan tambahan yang spesifik terhadap unit, calon peserta, dan proses pendaftaran tersebut.</li>
</ol>
HTML,
            'required_confirmation_text' => 'Saya menyetujui Ketentuan Penggunaan dan telah membaca Kebijakan Privasi Platform SPMB.',
            'marketing_enabled' => true,
            'marketing_text' => 'Saya bersedia menerima informasi program, kegiatan, dan promosi melalui email/WhatsApp. Persetujuan ini bersifat opsional.',
        ];
    }

    public function current(): AccountConsentPolicy
    {
        return AccountConsentPolicy::query()
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first()
            ?? $this->initialize();
    }

    public function initialize(): AccountConsentPolicy
    {
        return DB::transaction(function (): AccountConsentPolicy {
            $current = AccountConsentPolicy::query()
                ->where('status', 'published')
                ->orderByDesc('version')
                ->first();

            if ($current) {
                return $current;
            }

            return AccountConsentPolicy::query()->firstOrCreate(
                ['version' => 1],
                $this->normalized($this->defaults()) + [
                    'status' => 'published',
                    'published_at' => now(),
                ],
            );
        });
    }

    public function draft(User $actor): AccountConsentPolicy
    {
        $this->authorize($actor);

        return DB::transaction(function (): AccountConsentPolicy {
            $draft = AccountConsentPolicy::query()
                ->where('status', 'draft')
                ->orderByDesc('version')
                ->first();

            if ($draft) {
                return $draft;
            }

            $current = $this->current();

            return AccountConsentPolicy::create([
                ...$current->only([
                    'terms_title',
                    'terms_content',
                    'privacy_title',
                    'privacy_content',
                    'required_confirmation_text',
                    'marketing_enabled',
                    'marketing_text',
                ]),
                'version' => ((int) $current->version) + 1,
                'status' => 'draft',
            ]);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(AccountConsentPolicy $policy, User $actor, array $data, bool $publish = false): AccountConsentPolicy
    {
        $this->authorize($actor);

        if ($policy->status !== 'draft') {
            throw ValidationException::withMessages([
                'policy' => 'Hanya draft kebijakan yang dapat diedit atau dipublikasikan.',
            ]);
        }

        $data = $this->normalized($data);

        $validated = Validator::make($data, [
            'terms_title' => ['required', 'string', 'max:180'],
            'terms_content' => ['required', 'string', 'max:50000'],
            'privacy_title' => ['required', 'string', 'max:180'],
            'privacy_content' => ['required', 'string', 'max:50000'],
            'required_confirmation_text' => ['required', 'string', 'max:1000'],
            'marketing_enabled' => ['required', 'boolean'],
            'marketing_text' => [
                Rule::requiredIf((bool) ($data['marketing_enabled'] ?? false)),
                'nullable',
                'string',
                'max:1000',
            ],
        ])->validate();

        if ($publish) {
            $validated['status'] = 'published';
            $validated['published_at'] = now();
        }

        $policy->update($validated);

        return $policy->fresh();
    }

    public function recordSignupConsents(
        User $user,
        AccountConsentPolicy $policy,
        bool $marketingAccepted = false,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $current = $this->current();

        if ($policy->status !== 'published' || $current->id !== $policy->id) {
            throw ValidationException::withMessages([
                'account_consent_accepted' => 'Kebijakan akun telah diperbarui. Muat ulang halaman dan baca versi terbaru.',
            ]);
        }

        $acceptedAt = now();
        $confirmation = (string) $policy->required_confirmation_text;

        $rows = [
            [
                'type' => 'terms_of_service',
                'title' => (string) $policy->terms_title,
                'content' => (string) $policy->terms_content,
                'confirmation' => $confirmation,
            ],
            [
                'type' => 'privacy_policy',
                'title' => (string) $policy->privacy_title,
                'content' => (string) $policy->privacy_content,
                'confirmation' => $confirmation,
            ],
        ];

        if ($policy->marketing_enabled && $marketingAccepted) {
            $rows[] = [
                'type' => 'marketing_consent',
                'title' => 'Persetujuan Informasi & Promosi',
                'content' => (string) $policy->marketing_text,
                'confirmation' => (string) $policy->marketing_text,
            ];
        }

        foreach ($rows as $row) {
            UserConsent::create([
                'user_id' => $user->id,
                'account_consent_policy_id' => $policy->id,
                'consent_type' => $row['type'],
                'title_snapshot' => $row['title'],
                'content_snapshot' => $row['content'],
                'confirmation_snapshot' => $row['confirmation'],
                'content_hash' => $this->hashSnapshot($row['title'], $row['content'], $row['confirmation']),
                'accepted_at' => $acceptedAt,
                'ip_address' => filled($ipAddress) ? mb_substr((string) $ipAddress, 0, 45) : null,
                'user_agent' => filled($userAgent) ? mb_substr((string) $userAgent, 0, 2000) : null,
            ]);
        }
    }

    public function authorize(User $actor): void
    {
        abort_unless($actor->is_active && $actor->isAdmin(), 403);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalized(array $data): array
    {
        $defaults = $this->defaults();
        $sanitizer = app(RegistrationConsentService::class);

        return [
            'terms_title' => filled($data['terms_title'] ?? null)
                ? trim((string) $data['terms_title'])
                : $defaults['terms_title'],
            'terms_content' => $sanitizer->sanitizeHtml(
                filled($data['terms_content'] ?? null)
                    ? (string) $data['terms_content']
                    : $defaults['terms_content'],
            ),
            'privacy_title' => filled($data['privacy_title'] ?? null)
                ? trim((string) $data['privacy_title'])
                : $defaults['privacy_title'],
            'privacy_content' => $sanitizer->sanitizeHtml(
                filled($data['privacy_content'] ?? null)
                    ? (string) $data['privacy_content']
                    : $defaults['privacy_content'],
            ),
            'required_confirmation_text' => filled($data['required_confirmation_text'] ?? null)
                ? trim((string) $data['required_confirmation_text'])
                : $defaults['required_confirmation_text'],
            'marketing_enabled' => (bool) ($data['marketing_enabled'] ?? $defaults['marketing_enabled']),
            'marketing_text' => filled($data['marketing_text'] ?? null)
                ? trim((string) $data['marketing_text'])
                : $defaults['marketing_text'],
        ];
    }

    private function hashSnapshot(string $title, string $content, string $confirmation): string
    {
        return hash('sha256', (string) json_encode([
            'title' => $title,
            'content' => $content,
            'confirmation' => $confirmation,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
