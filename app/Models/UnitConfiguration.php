<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UnitConfiguration extends Model
{
    use HasPublicUuid;

    public const PARTICIPANT_CARD_MODE_BOTH = 'both';

    public const PARTICIPANT_CARD_MODE_REGISTRATION_ONLY = 'registration_only';

    public const PARTICIPANT_CARD_MODE_TEST_ONLY = 'test_only';

    public const PARTICIPANT_CARD_MODES = [
        self::PARTICIPANT_CARD_MODE_BOTH => 'Keduanya',
        self::PARTICIPANT_CARD_MODE_REGISTRATION_ONLY => 'Hanya Kartu Pendaftaran',
        self::PARTICIPANT_CARD_MODE_TEST_ONLY => 'Hanya Kartu Tes',
    ];

    public const ACHIEVEMENT_CERTIFICATE_MODE_NONE = 'none';

    public const ACHIEVEMENT_CERTIFICATE_MODE_OPTIONAL = 'optional';

    public const ACHIEVEMENT_CERTIFICATE_MODE_REQUIRED = 'required';

    public const ACHIEVEMENT_CERTIFICATE_MODES = [
        self::ACHIEVEMENT_CERTIFICATE_MODE_NONE => 'Tidak digunakan',
        self::ACHIEVEMENT_CERTIFICATE_MODE_OPTIONAL => 'Opsional',
        self::ACHIEVEMENT_CERTIFICATE_MODE_REQUIRED => 'Wajib',
    ];

    public const DEFAULT_REGISTRANT_RELATIONSHIP_OPTIONS = [
        ['key' => 'father', 'label' => 'Ayah'],
        ['key' => 'mother', 'label' => 'Ibu'],
        ['key' => 'guardian', 'label' => 'Wali'],
        ['key' => 'other', 'label' => 'Lainnya'],
    ];

    public const APPLICANT_PORTAL_BLOCK_LABELS = [
        'additional_information' => 'Informasi Tambahan',
        'selection_tests' => 'Tes Seleksi',
        'announcement' => 'Pengumuman Hasil',
        'post_announcement' => 'Status Pasca-Pengumuman',
        'summary' => 'Ringkasan',
        'payment' => 'Pembayaran',
        'required_documents' => 'Dokumen Wajib',
    ];

    public const DEFAULT_APPLICANT_PORTAL_BLOCKS = [
        'additional_information',
        'selection_tests',
        'announcement',
        'post_announcement',
        'summary',
        'payment',
        'required_documents',
    ];

    protected $fillable = ['unit_id', 'version', 'status', 'payment_enabled', 'documents_enabled', 'tests_enabled', 'selection_mode', 'post_announcement_enabled', 'workflow_stage_labels', 'applicant_visible_stages', 'applicant_portal_blocks', 'applicant_progress_description', 'completion_after_stage', 'completion_title', 'completion_message', 'registration_number_prefix', 'registration_number_digits', 'participant_card_mode', 'applicant_card_header_label', 'applicant_card_header_title', 'pre_form_consent', 'workflow_blocks', 'builtin_field_policy', 'registrant_relationship_options', 'academic_scores_enabled', 'academic_score_settings', 'achievements_enabled', 'achievement_settings', 'fields', 'form_groups', 'form_layout', 'document_requirements', 'test_definitions', 're_registration_requirements', 'published_at', 'legacy'];

    protected $casts = ['payment_enabled' => 'boolean', 'documents_enabled' => 'boolean', 'tests_enabled' => 'boolean', 'post_announcement_enabled' => 'boolean', 'workflow_stage_labels' => 'array', 'applicant_visible_stages' => 'array', 'applicant_portal_blocks' => 'array', 'registration_number_digits' => 'integer', 'pre_form_consent' => 'array', 'workflow_blocks' => 'array', 'registrant_relationship_options' => 'array', 'academic_scores_enabled' => 'boolean', 'academic_score_settings' => 'array', 'achievements_enabled' => 'boolean', 'achievement_settings' => 'array', 'fields' => 'array', 'form_groups' => 'array', 'form_layout' => 'array', 'document_requirements' => 'array', 'test_definitions' => 'array', 're_registration_requirements' => 'array', 'published_at' => 'datetime', 'legacy' => 'boolean'];

    protected static function booted(): void
    {
        static::updating(function (self $configuration): void {
            if ($configuration->getOriginal('status') === 'published') {
                throw ValidationException::withMessages(['configuration' => 'Versi terpublikasi tidak dapat diubah. Buat draft versi baru.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['configuration' => 'Konfigurasi tidak dapat dihapus.']));
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function participantCardMode(): string
    {
        $mode = (string) ($this->participant_card_mode ?: self::PARTICIPANT_CARD_MODE_BOTH);

        return array_key_exists($mode, self::PARTICIPANT_CARD_MODES)
            ? $mode
            : self::PARTICIPANT_CARD_MODE_BOTH;
    }

    public function registrationCardEnabled(): bool
    {
        return $this->participantCardMode() !== self::PARTICIPANT_CARD_MODE_TEST_ONLY;
    }

    public function testCardEnabled(): bool
    {
        return $this->participantCardMode() !== self::PARTICIPANT_CARD_MODE_REGISTRATION_ONLY;
    }

    public function achievementCertificateMode(): string
    {
        $mode = (string) data_get(
            $this->achievement_settings,
            'certificate_mode',
            self::ACHIEVEMENT_CERTIFICATE_MODE_OPTIONAL,
        );

        return array_key_exists($mode, self::ACHIEVEMENT_CERTIFICATE_MODES)
            ? $mode
            : self::ACHIEVEMENT_CERTIFICATE_MODE_OPTIONAL;
    }

    /** @return list<array{key:string,label:string}> */
    public static function defaultRegistrantRelationshipOptions(): array
    {
        return self::DEFAULT_REGISTRANT_RELATIONSHIP_OPTIONS;
    }

    /** @return array<string,string> */
    public static function defaultRegistrantRelationshipOptionMap(): array
    {
        return collect(self::DEFAULT_REGISTRANT_RELATIONSHIP_OPTIONS)
            ->pluck('label', 'key')
            ->all();
    }

    /** @return list<array{key:string,label:string}> */
    public static function normalizeRegistrantRelationshipOptions(mixed $options): array
    {
        $normalized = collect(is_array($options) ? $options : [])
            ->map(function (mixed $option): ?array {
                if (! is_array($option)) {
                    return null;
                }

                $label = trim((string) ($option['label'] ?? ''));
                $key = trim((string) ($option['key'] ?? ''));

                if ($label === '') {
                    return null;
                }

                if ($key === '') {
                    $key = 'relationship_'.substr(sha1(mb_strtolower($label)), 0, 12);
                }

                if (! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $key)) {
                    return null;
                }

                return ['key' => $key, 'label' => $label];
            })
            ->filter()
            ->unique('key')
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : self::defaultRegistrantRelationshipOptions();
    }

    /** @return array<string,string> */
    public function registrantRelationshipOptions(?string $includeValue = null): array
    {
        $options = collect(self::normalizeRegistrantRelationshipOptions($this->registrant_relationship_options))
            ->pluck('label', 'key')
            ->all();

        if (filled($includeValue) && ! array_key_exists($includeValue, $options)) {
            $legacyLabels = self::defaultRegistrantRelationshipOptionMap() + ['self' => 'Diri Sendiri'];
            $options[$includeValue] = $legacyLabels[$includeValue]
                ?? Str::of($includeValue)->replace('_', ' ')->headline()->toString();
        }

        return $options;
    }

    /** @return list<array{key:string,active:bool}> */
    public static function defaultApplicantPortalBlocks(): array
    {
        return collect(self::DEFAULT_APPLICANT_PORTAL_BLOCKS)
            ->map(fn (string $key): array => ['key' => $key, 'active' => true])
            ->all();
    }

    /** @return list<array{key:string,active:bool}> */
    public static function normalizeApplicantPortalBlocks(mixed $blocks): array
    {
        $normalized = collect(is_array($blocks) ? $blocks : [])
            ->map(function (mixed $block): ?array {
                if (is_string($block)) {
                    $block = ['key' => $block, 'active' => true];
                }

                if (! is_array($block)) {
                    return null;
                }

                $key = $block['key'] ?? null;

                if (! is_string($key) || ! array_key_exists($key, self::APPLICANT_PORTAL_BLOCK_LABELS)) {
                    return null;
                }

                return [
                    'key' => $key,
                    'active' => (bool) ($block['active'] ?? true),
                ];
            })
            ->filter()
            ->unique('key')
            ->values();

        foreach (self::DEFAULT_APPLICANT_PORTAL_BLOCKS as $key) {
            if (! $normalized->contains('key', $key)) {
                $normalized->push(['key' => $key, 'active' => true]);
            }
        }

        return $normalized->values()->all();
    }

    /** @return list<array{key:string,active:bool}> */
    public function applicantPortalBlocks(): array
    {
        return self::normalizeApplicantPortalBlocks($this->applicant_portal_blocks);
    }
}
