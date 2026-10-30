<?php

namespace App\Services;

use App\Models\RegistrationFormDraft;
use App\Models\RegistrationOpening;
use App\Models\UnitConfiguration;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationFormDraftService
{
    private const MAX_BYTES = 262144;

    private const FORM_FIELDS = [
        'registration_pathway_uuid', 'registrant_type', 'registrant_relationship',
        'nik', 'birth_date', 'full_name', 'nisn', 'nickname', 'gender',
        'religion', 'birth_place', 'phone', 'email', 'home_address', 'rt', 'rw',
        'province', 'city', 'district', 'village', 'postal_code',
        'province_code', 'city_code', 'district_code', 'village_code',
        'previous_school', 'previous_school_address', 'graduation_year',
        'child_order', 'siblings_count',
    ];

    private const PARENT_FIELDS = [
        'father_name', 'father_nik', 'father_birth_place', 'father_birth_date',
        'father_education', 'father_occupation', 'father_workplace',
        'father_phone', 'father_email', 'father_income',
        'mother_name', 'mother_nik', 'mother_birth_place', 'mother_birth_date',
        'mother_education', 'mother_occupation', 'mother_workplace',
        'mother_phone', 'mother_email', 'mother_income',
    ];

    public function find(User $user, RegistrationOpening $opening, UnitConfiguration $configuration): ?RegistrationFormDraft
    {
        $this->authorize($user, $opening, $configuration);

        return RegistrationFormDraft::query()
            ->where('user_id', $user->id)
            ->where('registration_opening_id', $opening->id)
            ->where('unit_configuration_id', $configuration->id)
            ->first();
    }

    public function save(
        User $user,
        RegistrationOpening $opening,
        UnitConfiguration $configuration,
        array $formData,
        int $expectedRevision,
    ): RegistrationFormDraft {
        $this->authorize($user, $opening, $configuration);

        $payload = $this->sanitize($formData, $configuration);

        if (strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'draft' => 'Draft terlalu besar untuk disimpan. Kurangi isi keterangan dan coba lagi.',
            ]);
        }

        return DB::transaction(function () use ($user, $opening, $configuration, $payload, $expectedRevision): RegistrationFormDraft {
            $current = RegistrationFormDraft::query()
                ->where('user_id', $user->id)
                ->where('registration_opening_id', $opening->id)
                ->lockForUpdate()
                ->first();

            if ($current && $current->unit_configuration_id !== $configuration->id) {
                throw ValidationException::withMessages([
                    'draft' => 'Draft berasal dari konfigurasi lama. Pilih Hapus Draft sebelum membuat draft baru.',
                ]);
            }

            if ((int) ($current?->revision ?? 0) !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'draft' => 'Draft telah diperbarui di tab atau perangkat lain. Muat ulang halaman sebelum menyimpan kembali.',
                ]);
            }

            if ($current) {
                $current->update([
                    'payload' => $payload,
                    'revision' => $current->revision + 1,
                ]);

                return $current;
            }

            return RegistrationFormDraft::create([
                'user_id' => $user->id,
                'registration_opening_id' => $opening->id,
                'unit_configuration_id' => $configuration->id,
                'payload' => $payload,
                'revision' => 1,
            ]);
        }, 3);
    }

    public function discard(User $user, RegistrationOpening $opening): void
    {
        abort_unless($user->is_active && $user->isUser() && $user->hasVerifiedEmail(), 403);

        RegistrationFormDraft::query()
            ->where('user_id', $user->id)
            ->where('registration_opening_id', $opening->id)
            ->delete();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data, UnitConfiguration $configuration): array
    {
        $payload = [];

        foreach (self::FORM_FIELDS as $field) {
            if (array_key_exists($field, $data) && $this->isScalar($data[$field])) {
                $payload[$field] = $data[$field];
            }
        }

        if (is_array($data['parentInfo'] ?? null)) {
            $payload['parentInfo'] = array_filter(
                Arr::only($data['parentInfo'], self::PARENT_FIELDS),
                fn (mixed $value): bool => $this->isScalar($value),
            );
        }

        $allowedCustom = [];
        foreach ($configuration->fields ?? [] as $definition) {
            if (! is_array($definition) || ! ($definition['active'] ?? false)
                || in_array($definition['type'] ?? '', ['file', 'placeholder'], true)
                || ! is_string($definition['key'] ?? null)) {
                continue;
            }

            $allowedCustom[] = $definition['key'];

            if (($definition['type'] ?? null) === 'boolean') {
                $allowedCustom[] = $definition['key'].'_yes_detail';
                $allowedCustom[] = $definition['key'].'_no_detail';
            }
        }

        if (is_array($data['custom_answers'] ?? null)) {
            $answers = Arr::only($data['custom_answers'], $allowedCustom);

            $booleanKeys = collect($configuration->fields ?? [])
                ->filter(fn (mixed $field): bool => is_array($field)
                    && ($field['active'] ?? false)
                    && ($field['type'] ?? null) === 'boolean'
                    && is_string($field['key'] ?? null))
                ->pluck('key')
                ->all();

            if (is_array($data['custom_answers']['_details'] ?? null)) {
                $details = Arr::only($data['custom_answers']['_details'], $booleanKeys);
                if ($details !== []) {
                    $answers['_details'] = $details;
                }
            }

            $payload['custom_answers'] = $this->cleanNested($answers);
        }

        if ($configuration->academic_scores_enabled && is_array($data['academic_scores'] ?? null)) {
            $payload['academic_scores'] = $this->cleanNested($data['academic_scores']);
        }

        if ($configuration->achievements_enabled && is_array($data['achievements'] ?? null)) {
            $payload['achievements'] = [];

            foreach (array_slice($data['achievements'], 0, 20, true) as $key => $achievement) {
                if (is_array($achievement)) {
                    $payload['achievements'][$key] = $this->cleanNested(Arr::only($achievement, [
                        'title', 'level', 'year', 'organizer', 'description',
                    ]));
                }
            }
        }

        return $payload;
    }

    private function isScalar(mixed $value): bool
    {
        return $value === null || is_string($value) || is_bool($value)
            || is_int($value) || is_float($value);
    }

    private function cleanNested(array $values, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }

        $clean = [];

        foreach (array_slice($values, 0, 200, true) as $key => $value) {
            if (is_string($key) && (strlen($key) > 100
                || in_array($key, ['__proto__', 'prototype', 'constructor', 'certificate_path', 'certificate_original_name'], true))) {
                continue;
            }

            if ($this->isScalar($value)) {
                $clean[$key] = $value;
            } elseif (is_array($value)) {
                $clean[$key] = $this->cleanNested($value, $depth + 1);
            }
        }

        return $clean;
    }

    private function authorize(User $user, RegistrationOpening $opening, UnitConfiguration $configuration): void
    {
        abort_unless($user->is_active && $user->isUser() && $user->hasVerifiedEmail(), 403);
        abort_unless($opening->isOpen() && $opening->unit_id === $configuration->unit_id, 403);

        $current = app(UnitConfigurationService::class)->current((int) $opening->unit_id);

        if ($current?->id !== $configuration->id) {
            throw ValidationException::withMessages([
                'draft' => 'Konfigurasi pendaftaran telah berubah. Muat ulang formulir sebelum mengelola draft.',
            ]);
        }
    }
}
