<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class UnitConfiguration extends Model
{
    use HasPublicUuid;

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

    protected $fillable = ['unit_id', 'version', 'status', 'payment_enabled', 'documents_enabled', 'tests_enabled', 'selection_mode', 'post_announcement_enabled', 'workflow_stage_labels', 'applicant_visible_stages', 'applicant_portal_blocks', 'completion_after_stage', 'completion_title', 'completion_message', 'registration_number_prefix', 'registration_number_digits', 'applicant_card_header_label', 'applicant_card_header_title', 'pre_form_consent', 'workflow_blocks', 'builtin_field_policy', 'academic_scores_enabled', 'academic_score_settings', 'achievements_enabled', 'achievement_settings', 'fields', 'form_groups', 'form_layout', 'document_requirements', 'test_definitions', 're_registration_requirements', 'published_at', 'legacy'];

    protected $casts = ['payment_enabled' => 'boolean', 'documents_enabled' => 'boolean', 'tests_enabled' => 'boolean', 'post_announcement_enabled' => 'boolean', 'workflow_stage_labels' => 'array', 'applicant_visible_stages' => 'array', 'applicant_portal_blocks' => 'array', 'registration_number_digits' => 'integer', 'pre_form_consent' => 'array', 'workflow_blocks' => 'array', 'academic_scores_enabled' => 'boolean', 'academic_score_settings' => 'array', 'achievements_enabled' => 'boolean', 'achievement_settings' => 'array', 'fields' => 'array', 'form_groups' => 'array', 'form_layout' => 'array', 'document_requirements' => 'array', 'test_definitions' => 'array', 're_registration_requirements' => 'array', 'published_at' => 'datetime', 'legacy' => 'boolean'];

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
