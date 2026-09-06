<?php

namespace App\Services;

use App\Models\AdmissionTest;
use App\Models\Registration;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitConfigurationService
{
    public function authorize(User $user, int $unitId): void
    {
        Gate::forUser($user)->authorize('configureRegistration', Unit::findOrFail($unitId));
    }

    public function current(int $unitId): ?UnitConfiguration
    {
        return UnitConfiguration::query()->where('unit_id', $unitId)->where('status', 'published')->orderByDesc('version')->first();
    }

    public function defaults(Unit $unit): array
    {
        $labels = ['report_card' => 'Rapor', 'family_card' => 'Kartu Keluarga', 'birth_certificate' => 'Akta Kelahiran', 'photo' => 'Pas Foto', 'supporting_document' => 'Dokumen Pendukung'];
        $documents = [];
        foreach ($labels as $key => $label) {
            $documents[] = ['key' => $key, 'label' => $label, 'active' => $key !== 'report_card' || $unit->code !== 'SD', 'required' => $key !== 'supporting_document', 'max_files' => 1, 'formats' => $key === 'photo' ? ['jpg', 'png'] : ['pdf', 'jpg', 'png'], 'instructions' => '', 'template_path' => null];
        }
        $tests = $unit->admissionTests()->where('is_active', true)->get()->map(fn (AdmissionTest $test): array => $test->only(['id', 'name', 'study_program_id', 'is_required', 'result_type', 'passing_score']))->all();

        return ['payment_enabled' => true, 'documents_enabled' => true, 'tests_enabled' => count($tests) > 0, 'fields' => [], 'document_requirements' => $documents, 'test_definitions' => $tests];
    }

    public function initialize(Unit $unit): UnitConfiguration
    {
        return DB::transaction(function () use ($unit): UnitConfiguration {
            Unit::query()->lockForUpdate()->findOrFail($unit->id);
            $configuration = $this->current($unit->id);
            if (! $configuration) {
                $configuration = UnitConfiguration::create($this->defaults($unit) + ['unit_id' => $unit->id, 'version' => 1, 'status' => 'published', 'legacy' => true, 'published_at' => now()]);
            }
            Registration::query()->where('unit_id', $unit->id)->whereNull('unit_configuration_id')->update(['unit_configuration_id' => $configuration->id]);

            return $configuration;
        });
    }

    public function draft(Unit $unit, User $actor): UnitConfiguration
    {
        $this->authorize($actor, $unit->id);

        return DB::transaction(function () use ($unit): UnitConfiguration {
            Unit::query()->lockForUpdate()->findOrFail($unit->id);
            $existing = UnitConfiguration::query()->where('unit_id', $unit->id)->where('status', 'draft')->first();
            if ($existing) {
                return $existing;
            }
            $current = $this->initialize($unit);

            return UnitConfiguration::create($current->only(['payment_enabled', 'documents_enabled', 'tests_enabled', 'fields', 'document_requirements', 'test_definitions']) + ['unit_id' => $unit->id, 'version' => $current->version + 1, 'status' => 'draft']);
        });
    }

    public function save(UnitConfiguration $configuration, User $actor, array $data, bool $publish = false): UnitConfiguration
    {
        $this->authorize($actor, (int) $configuration->unit_id);

        return DB::transaction(function () use ($configuration, $data, $publish): UnitConfiguration {
            Unit::query()->lockForUpdate()->findOrFail($configuration->unit_id);
            $locked = UnitConfiguration::query()->lockForUpdate()->findOrFail($configuration->id);
            $validated = Validator::make($data, [
                'payment_enabled' => ['required', 'boolean'], 'documents_enabled' => ['required', 'boolean'], 'tests_enabled' => ['required', 'boolean'],
                'fields' => ['present', 'array', 'max:100'], 'fields.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct', 'max:60'],
                'fields.*.label' => ['required', 'string', 'max:150'], 'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'number', 'date', 'select', 'multiselect', 'boolean'])],
                'fields.*.active' => ['required', 'boolean'], 'fields.*.required' => ['required', 'boolean'], 'fields.*.group' => ['nullable', 'string', 'max:100'],
                'fields.*.help' => ['nullable', 'string', 'max:1000'], 'fields.*.options' => ['nullable', 'array'], 'fields.*.options.*' => ['string', 'max:150'],
                'document_requirements' => ['present', 'array', 'max:100'], 'document_requirements.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60', 'distinct'],
                'document_requirements.*.label' => ['required', 'string', 'max:150'], 'document_requirements.*.active' => ['required', 'boolean'], 'document_requirements.*.required' => ['required', 'boolean'],
                'document_requirements.*.max_files' => ['required', 'integer', 'min:1', 'max:20'], 'document_requirements.*.formats' => ['required', 'array', 'min:1'],
                'document_requirements.*.formats.*' => [Rule::in(['pdf', 'docx', 'jpg', 'png'])], 'document_requirements.*.instructions' => ['nullable', 'string', 'max:2000'],
                'document_requirements.*.template_path' => ['nullable', 'string'], 'test_definitions' => ['present', 'array'],
                'test_definitions.*.id' => ['required', 'integer', 'distinct'],
            ])->validate();
            foreach ($validated['fields'] as $field) {
                if (in_array($field['key'], ConfiguredRegistrationForm::CORE_FIELDS, true)) {
                    throw ValidationException::withMessages(['fields' => 'Identitas inti tidak boleh diubah.']);
                }
                if (in_array($field['type'], ['select', 'multiselect'], true) && empty($field['options'])) {
                    throw ValidationException::withMessages(['fields' => 'Field pilihan harus memiliki opsi.']);
                }
            }
            foreach ($validated['document_requirements'] as $requirement) {
                if (! empty($requirement['template_path'])) {
                    if (! str_starts_with($requirement['template_path'], 'templates/'.$locked->unit_id.'/')) {
                        throw ValidationException::withMessages(['document_requirements' => 'Template tidak sesuai unit.']);
                    }
                    app(ApplicantUploadSecurity::class)->inspect($requirement['template_path'], ['pdf', 'docx']);
                }
            }
            $tests = AdmissionTest::query()->where('unit_id', $locked->unit_id)->whereIn('id', array_column($validated['test_definitions'], 'id'))->get();
            if ($tests->count() !== count($validated['test_definitions'])) {
                throw ValidationException::withMessages(['test_definitions' => 'Tes tidak sesuai unit.']);
            }
            $validated['test_definitions'] = $tests->map(fn (AdmissionTest $test): array => $test->only(['id', 'name', 'study_program_id', 'is_required', 'result_type', 'passing_score']))->all();
            if ($publish) {
                if ($validated['tests_enabled'] && ! $tests->contains('is_required', true)) {
                    throw ValidationException::withMessages(['test_definitions' => 'Aktifkan minimal satu tes wajib.']);
                }
                if (! $validated['payment_enabled'] && $locked->unit->registrationOpenings()->where('status', 'open')->where('registration_fee', '>', 0)->exists()) {
                    throw ValidationException::withMessages(['payment_enabled' => 'Pembukaan aktif harus berbiaya nol sebelum pembayaran dinonaktifkan.']);
                }
                $validated += ['status' => 'published', 'published_at' => now()];
            }
            $locked->update($validated);
            app(AuditTrail::class)->record($publish ? 'configuration.published' : 'configuration.saved', $locked, unitId: $locked->unit_id);

            return $locked;
        });
    }
}
