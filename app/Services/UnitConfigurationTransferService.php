<?php

namespace App\Services;

use App\Models\AdmissionQuota;
use App\Models\AdmissionTest;
use App\Models\EducationLevel;
use App\Models\Faq;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\StudyProgram;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitConfigurationTransferService
{
    public const FORMAT = 'spmb-unit-configuration';

    public const SCHEMA_VERSION = 1;

    private const PROFILE_FIELDS = [
        'description',
        'public_headline',
        'public_body',
        'pre_registration_heading',
        'pre_registration_body',
        'pre_registration_items',
        'public_contact_name',
        'public_email',
        'public_phone',
        'public_whatsapp',
        'public_service_hours',
        'public_website_url',
        'public_address',
        'logo_path',
    ];

    private const CONFIGURATION_FIELDS = [
        'payment_enabled',
        'documents_enabled',
        'tests_enabled',
        'selection_mode',
        'post_announcement_enabled',
        'workflow_stage_labels',
        'applicant_visible_stages',
        'applicant_progress_description',
        'completion_after_stage',
        'completion_title',
        'completion_message',
        'registration_number_prefix',
        'registration_number_digits',
        'participant_card_mode',
        'applicant_card_header_label',
        'applicant_card_header_title',
        'pre_form_consent',
        'workflow_blocks',
        'builtin_field_policy',
        'academic_scores_enabled',
        'academic_score_settings',
        'achievements_enabled',
        'achievement_settings',
        'fields',
        'form_groups',
        'form_layout',
        'document_requirements',
        'test_definitions',
        're_registration_requirements',
    ];

    /** @return array<string, mixed> */
    public function export(Unit $unit): array
    {
        $unit->load([
            'faqs.studyProgram',
            'faqs.registrationPathway',
            'registrationPathways',
            'studyPrograms.educationLevel',
            'registrationOpenings.studyProgram',
            'registrationOpenings.admissionQuotas.pathway',
            'admissionTests.studyProgram',
            'admissionTests.sessions',
        ]);

        $configuration = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'draft')
            ->first()
            ?? app(UnitConfigurationService::class)->current($unit->id);

        $configurationData = $configuration
            ? $configuration->toArray()
            : app(UnitConfigurationService::class)->defaults($unit);

        return [
            'format' => self::FORMAT,
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => now()->toIso8601String(),
            'source_unit' => [
                'name' => $unit->name,
                'code' => $unit->code,
                'institution_type' => $unit->institution_type,
            ],
            'profile' => Arr::only($unit->toArray(), self::PROFILE_FIELDS),
            'registration_pathways' => $unit->registrationPathways
                ->map(fn (RegistrationPathway $pathway): array => [
                    'name' => $pathway->name,
                    'description' => $pathway->description,
                    'is_active' => $pathway->is_active,
                    'archived_at' => $pathway->archived_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'study_programs' => $unit->studyPrograms
                ->map(fn (StudyProgram $program): array => [
                    'code' => $program->code,
                    'education_level_code' => $program->educationLevel?->code,
                    'name' => $program->name,
                    'degree_level' => $program->degree_level,
                    'faculty' => $program->faculty,
                    'description' => $program->description,
                    'public_headline' => $program->public_headline,
                    'public_body' => $program->public_body,
                    'study_duration' => $program->study_duration,
                    'public_highlights' => $program->public_highlights,
                    'public_target_audiences' => $program->public_target_audiences,
                    'max_age' => $program->max_age,
                    'sort_order' => $program->sort_order,
                    'is_active' => $program->is_active,
                    'workflow_steps' => $program->workflow_steps,
                ])
                ->values()
                ->all(),
            'registration_openings' => $unit->registrationOpenings
                ->map(fn (RegistrationOpening $opening): array => [
                    'study_program_code' => $opening->studyProgram?->code,
                    'academic_year' => $opening->academic_year,
                    'wave' => $opening->wave,
                    'registration_fee' => $opening->registration_fee,
                    'description' => $opening->description,
                    'status' => $opening->status,
                    'opened_at' => $opening->opened_at?->toIso8601String(),
                    'closed_at' => $opening->closed_at?->toIso8601String(),
                    'archived_at' => $opening->archived_at?->toIso8601String(),
                    'admission_quotas' => $opening->admissionQuotas
                        ->map(fn (AdmissionQuota $quota): array => [
                            'registration_pathway_name' => $quota->pathway?->name,
                            'capacity' => $quota->capacity,
                            'offer_expires_in_hours' => $quota->offer_expires_in_hours,
                            're_registration_due_in_days' => $quota->re_registration_due_in_days,
                            'is_active' => $quota->is_active,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'admission_tests' => $unit->admissionTests
                ->map(fn (AdmissionTest $test): array => [
                    'code' => $test->code,
                    'name' => $test->name,
                    'study_program_code' => $test->studyProgram?->code,
                    'description' => $test->description,
                    'sort_order' => $test->sort_order,
                    'is_required' => $test->is_required,
                    'is_active' => $test->is_active,
                    'scheduled_at' => $test->scheduled_at?->toIso8601String(),
                    'location' => $test->location,
                    'passing_score' => $test->passing_score,
                    'result_type' => $test->result_type,
                    'sessions' => $test->sessions
                        ->map(fn (TestSession $session): array => [
                            'starts_at' => $session->starts_at?->toIso8601String(),
                            'ends_at' => $session->ends_at?->toIso8601String(),
                            'booking_closes_at' => $session->booking_closes_at?->toIso8601String(),
                            'location' => $session->location,
                            'instructions' => $session->instructions,
                            'capacity' => $session->capacity,
                            'status' => $session->status,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'faqs' => $unit->faqs
                ->map(fn (Faq $faq): array => [
                    'study_program_code' => $faq->studyProgram?->code,
                    'registration_pathway_name' => $faq->registrationPathway?->name,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'sort_order' => $faq->sort_order,
                    'is_active' => $faq->is_active,
                ])
                ->values()
                ->all(),
            'registration_settings' => [
                'source_version' => $configuration?->version,
                'source_status' => $configuration?->status ?? 'defaults',
                'data' => $this->portableConfiguration($unit, $configurationData),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{pathways:int,programs:int,openings:int,quotas:int,tests:int,sessions:int,faqs:int,configuration:bool}
     */
    public function import(Unit $unit, User $actor, array $payload): array
    {
        $configurationService = app(UnitConfigurationService::class);
        $configurationService->authorize($actor, $unit->id);
        $this->assertPayload($unit, $payload);

        return DB::transaction(function () use ($unit, $actor, $payload, $configurationService): array {
            $profile = Arr::only(
                is_array($payload['profile'] ?? null) ? $payload['profile'] : [],
                self::PROFILE_FIELDS,
            );

            if (array_key_exists('pre_registration_items', $profile)) {
                $profile['pre_registration_items'] = collect($profile['pre_registration_items'] ?? [])
                    ->filter(fn (mixed $item): bool => is_array($item) && filled($item['title'] ?? null))
                    ->map(fn (array $item): array => [
                        'title' => trim((string) $item['title']),
                        'description' => trim((string) ($item['description'] ?? '')),
                    ])
                    ->values()
                    ->all();
            }

            $unit->update($profile);

            $pathwayCount = $this->importPathways($unit, $payload['registration_pathways'] ?? []);
            $programCount = $this->importStudyPrograms($unit, $payload['study_programs'] ?? []);
            [$openingCount, $quotaCount] = $this->importRegistrationOpenings($unit, $payload['registration_openings'] ?? []);
            [$testCount, $sessionCount] = $this->importAdmissionTests($unit, $payload['admission_tests'] ?? []);
            $faqCount = $this->importFaqs($unit, $payload['faqs'] ?? []);

            $configurationImported = false;
            $portableData = $payload['registration_settings']['data'] ?? null;

            if (is_array($portableData)) {
                $data = $this->restoreConfiguration($unit, $portableData);
                $draft = $configurationService->draft($unit, $actor);
                $configurationService->save($draft, $actor, $data);
                $configurationImported = true;
            }

            app(AuditTrail::class)->record(
                'configuration.unit_imported',
                $unit,
                newValues: [
                    'pathways' => $pathwayCount,
                    'programs' => $programCount,
                    'openings' => $openingCount,
                    'quotas' => $quotaCount,
                    'tests' => $testCount,
                    'sessions' => $sessionCount,
                    'faqs' => $faqCount,
                    'registration_configuration' => $configurationImported,
                ],
                metadata: [
                    'format' => self::FORMAT,
                    'schema_version' => self::SCHEMA_VERSION,
                    'source_unit' => $payload['source_unit'] ?? null,
                ],
                actor: $actor,
                unitId: $unit->id,
                description: 'Konfigurasi unit diimpor dari berkas konfigurasi portabel.',
            );

            return [
                'pathways' => $pathwayCount,
                'programs' => $programCount,
                'openings' => $openingCount,
                'quotas' => $quotaCount,
                'tests' => $testCount,
                'sessions' => $sessionCount,
                'faqs' => $faqCount,
                'configuration' => $configurationImported,
            ];
        });
    }

    /** @param array<string, mixed> $payload */
    private function assertPayload(Unit $unit, array $payload): void
    {
        if (($payload['format'] ?? null) !== self::FORMAT) {
            throw ValidationException::withMessages([
                'import_file' => 'Berkas bukan hasil ekspor konfigurasi SPMB yang didukung.',
            ]);
        }

        if ((int) ($payload['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            throw ValidationException::withMessages([
                'import_file' => 'Versi format konfigurasi tidak didukung oleh aplikasi ini.',
            ]);
        }

        $sourceType = $payload['source_unit']['institution_type'] ?? null;

        if (filled($sourceType) && $sourceType !== $unit->institution_type) {
            throw ValidationException::withMessages([
                'import_file' => 'Jenis institusi sumber berbeda dengan unit tujuan.',
            ]);
        }

        if (! is_array($payload['registration_settings']['data'] ?? null)) {
            throw ValidationException::withMessages([
                'import_file' => 'Berkas tidak memuat Pengaturan Pendaftaran Unit.',
            ]);
        }
    }

    /** @param array<int, mixed> $rows */
    private function importPathways(Unit $unit, array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['name'] ?? null)) {
                continue;
            }

            $archivedAt = filled($row['archived_at'] ?? null)
                ? Carbon::parse($row['archived_at'])
                : null;

            RegistrationPathway::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'name' => trim((string) $row['name']),
                ],
                [
                    'description' => $row['description'] ?? null,
                    'is_active' => $archivedAt ? false : (bool) ($row['is_active'] ?? true),
                    'archived_at' => $archivedAt,
                ],
            );

            $count++;
        }

        return $count;
    }

    /** @param array<int, mixed> $rows */
    private function importStudyPrograms(Unit $unit, array $rows): int
    {
        if ($rows !== [] && ! $unit->isHigherEducation()) {
            throw ValidationException::withMessages([
                'import_file' => 'Berkas memuat Program Studi tetapi unit tujuan bukan perguruan tinggi.',
            ]);
        }

        $count = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['code'] ?? null) || blank($row['name'] ?? null)) {
                continue;
            }

            $educationLevel = null;
            if (filled($row['education_level_code'] ?? null)) {
                $educationLevel = EducationLevel::query()
                    ->where('code', mb_strtoupper(trim((string) $row['education_level_code'])))
                    ->first();

                if (! $educationLevel) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Jenjang Program Studi '.($row['education_level_code'] ?? '').' tidak tersedia pada sistem tujuan.',
                    ]);
                }
            }

            StudyProgram::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'code' => mb_strtoupper(trim((string) $row['code'])),
                ],
                [
                    'education_level_id' => $educationLevel?->id,
                    'name' => trim((string) $row['name']),
                    'degree_level' => $row['degree_level'] ?? $educationLevel?->code,
                    'faculty' => $row['faculty'] ?? null,
                    'description' => $row['description'] ?? null,
                    'public_headline' => $row['public_headline'] ?? null,
                    'public_body' => $row['public_body'] ?? null,
                    'study_duration' => $row['study_duration'] ?? null,
                    'public_highlights' => is_array($row['public_highlights'] ?? null) ? $row['public_highlights'] : [],
                    'public_target_audiences' => is_array($row['public_target_audiences'] ?? null) ? $row['public_target_audiences'] : [],
                    'max_age' => $row['max_age'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'workflow_steps' => is_array($row['workflow_steps'] ?? null) ? $row['workflow_steps'] : null,
                ],
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array{int,int}
     */
    private function importRegistrationOpenings(Unit $unit, array $rows): array
    {
        $openingCount = 0;
        $quotaCount = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['academic_year'] ?? null) || blank($row['wave'] ?? null)) {
                continue;
            }

            $programId = null;
            if (filled($row['study_program_code'] ?? null)) {
                $programId = StudyProgram::query()
                    ->where('unit_id', $unit->id)
                    ->where('code', mb_strtoupper(trim((string) $row['study_program_code'])))
                    ->value('id');

                if (! $programId) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Program Studi untuk pembukaan '.($row['wave'] ?? '').' tidak ditemukan pada unit tujuan.',
                    ]);
                }
            }

            $opening = RegistrationOpening::query()
                ->where('unit_id', $unit->id)
                ->where('academic_year', trim((string) $row['academic_year']))
                ->where('wave', trim((string) $row['wave']))
                ->when(
                    $programId,
                    fn ($query) => $query->where('study_program_id', $programId),
                    fn ($query) => $query->whereNull('study_program_id'),
                )
                ->first()
                ?? new RegistrationOpening(['unit_id' => $unit->id]);

            $archivedAt = filled($row['archived_at'] ?? null)
                ? Carbon::parse($row['archived_at'])
                : null;

            $opening->fill([
                'study_program_id' => $programId,
                'academic_year' => trim((string) $row['academic_year']),
                'wave' => trim((string) $row['wave']),
                'registration_fee' => $row['registration_fee'] ?? 0,
                'description' => $row['description'] ?? null,
                'status' => $archivedAt ? 'archived' : ($row['status'] ?? 'draft'),
                'opened_at' => filled($row['opened_at'] ?? null) ? Carbon::parse($row['opened_at']) : null,
                'closed_at' => filled($row['closed_at'] ?? null) ? Carbon::parse($row['closed_at']) : null,
                'archived_at' => $archivedAt,
            ]);
            $opening->unit_id = $unit->id;
            $opening->save();
            $openingCount++;

            foreach (is_array($row['admission_quotas'] ?? null) ? $row['admission_quotas'] : [] as $quotaRow) {
                if (! is_array($quotaRow)) {
                    continue;
                }

                $pathwayId = null;
                if (filled($quotaRow['registration_pathway_name'] ?? null)) {
                    $pathwayId = RegistrationPathway::query()
                        ->where('unit_id', $unit->id)
                        ->where('name', trim((string) $quotaRow['registration_pathway_name']))
                        ->value('id');

                    if (! $pathwayId) {
                        throw ValidationException::withMessages([
                            'import_file' => 'Jalur Pendaftaran untuk daya tampung pembukaan '.($row['wave'] ?? '').' tidak ditemukan pada unit tujuan.',
                        ]);
                    }
                }

                $quota = AdmissionQuota::query()
                    ->where('registration_opening_id', $opening->id)
                    ->when(
                        $pathwayId,
                        fn ($query) => $query->where('registration_pathway_id', $pathwayId),
                        fn ($query) => $query->whereNull('registration_pathway_id'),
                    )
                    ->first()
                    ?? new AdmissionQuota(['registration_opening_id' => $opening->id]);

                $quota->fill([
                    'registration_pathway_id' => $pathwayId,
                    'capacity' => (int) ($quotaRow['capacity'] ?? 0),
                    'offer_expires_in_hours' => (int) ($quotaRow['offer_expires_in_hours'] ?? 72),
                    're_registration_due_in_days' => (int) ($quotaRow['re_registration_due_in_days'] ?? 14),
                    'is_active' => (bool) ($quotaRow['is_active'] ?? true),
                ]);
                $quota->registration_opening_id = $opening->id;
                $quota->save();
                $quotaCount++;
            }
        }

        return [$openingCount, $quotaCount];
    }

    /**
     * @param array<int, mixed> $rows
     * @return array{int,int}
     */
    private function importAdmissionTests(Unit $unit, array $rows): array
    {
        $testCount = 0;
        $sessionCount = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['name'] ?? null)) {
                continue;
            }

            $programId = null;
            if (filled($row['study_program_code'] ?? null)) {
                $programId = StudyProgram::query()
                    ->where('unit_id', $unit->id)
                    ->where('code', mb_strtoupper(trim((string) $row['study_program_code'])))
                    ->value('id');

                if (! $programId) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Program Studi untuk tes '.($row['name'] ?? '').' tidak ditemukan pada unit tujuan.',
                    ]);
                }
            }

            $query = AdmissionTest::query()->where('unit_id', $unit->id);

            if (filled($row['code'] ?? null)) {
                $query->where('code', trim((string) $row['code']));
            } else {
                $query->where('name', trim((string) $row['name']));
            }

            $test = $query->first() ?? new AdmissionTest(['unit_id' => $unit->id]);
            $test->fill([
                'study_program_id' => $programId,
                'name' => trim((string) $row['name']),
                'code' => filled($row['code'] ?? null) ? trim((string) $row['code']) : null,
                'description' => $row['description'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_required' => (bool) ($row['is_required'] ?? true),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'scheduled_at' => $row['scheduled_at'] ?? null,
                'location' => $row['location'] ?? null,
                'passing_score' => $row['passing_score'] ?? null,
                'result_type' => $row['result_type'] ?? 'score',
            ]);
            $test->unit_id = $unit->id;
            $test->save();
            $testCount++;

            foreach (is_array($row['sessions'] ?? null) ? $row['sessions'] : [] as $sessionRow) {
                if (! is_array($sessionRow) || blank($sessionRow['starts_at'] ?? null)) {
                    continue;
                }

                $startsAt = Carbon::parse($sessionRow['starts_at'])->format('Y-m-d H:i:s');
                $location = $sessionRow['location'] ?? null;

                $session = $test->sessions()
                    ->where('starts_at', $startsAt)
                    ->where('location', $location)
                    ->first()
                    ?? new TestSession(['admission_test_id' => $test->id]);

                $session->fill([
                    'starts_at' => $startsAt,
                    'ends_at' => filled($sessionRow['ends_at'] ?? null)
                        ? Carbon::parse($sessionRow['ends_at'])->format('Y-m-d H:i:s')
                        : null,
                    'booking_closes_at' => filled($sessionRow['booking_closes_at'] ?? null)
                        ? Carbon::parse($sessionRow['booking_closes_at'])->format('Y-m-d H:i:s')
                        : null,
                    'location' => $location,
                    'instructions' => $sessionRow['instructions'] ?? null,
                    'capacity' => $sessionRow['capacity'] ?? null,
                    'status' => $sessionRow['status'] ?? 'open',
                ]);
                $session->admission_test_id = $test->id;
                $session->save();
                $sessionCount++;
            }
        }

        return [$testCount, $sessionCount];
    }

    /** @param array<int, mixed> $rows */
    private function importFaqs(Unit $unit, array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['question'] ?? null) || blank($row['answer'] ?? null)) {
                continue;
            }

            $programId = null;
            if (filled($row['study_program_code'] ?? null)) {
                $programId = StudyProgram::query()
                    ->where('unit_id', $unit->id)
                    ->where('code', mb_strtoupper(trim((string) $row['study_program_code'])))
                    ->value('id');

                if (! $programId) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Konteks Program Studi untuk FAQ tidak ditemukan pada unit tujuan.',
                    ]);
                }
            }

            $pathwayId = null;
            if (filled($row['registration_pathway_name'] ?? null)) {
                $pathwayId = RegistrationPathway::query()
                    ->where('unit_id', $unit->id)
                    ->where('name', trim((string) $row['registration_pathway_name']))
                    ->value('id');

                if (! $pathwayId) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Konteks Jalur Pendaftaran untuk FAQ tidak ditemukan pada unit tujuan.',
                    ]);
                }
            }

            Faq::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'study_program_id' => $programId,
                    'registration_pathway_id' => $pathwayId,
                    'question' => trim((string) $row['question']),
                ],
                [
                    'answer' => (string) $row['answer'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ],
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function portableConfiguration(Unit $unit, array $data): array
    {
        $portable = Arr::only($data, self::CONFIGURATION_FIELDS);
        $pathwayNames = RegistrationPathway::query()
            ->where('unit_id', $unit->id)
            ->pluck('name', 'uuid');

        foreach (['academic_score_settings', 'achievement_settings'] as $key) {
            $settings = is_array($portable[$key] ?? null) ? $portable[$key] : [];
            $settings['pathway_names'] = collect($settings['pathway_uuids'] ?? [])
                ->map(fn (string $uuid): ?string => $pathwayNames->get($uuid))
                ->filter()
                ->values()
                ->all();
            unset($settings['pathway_uuids']);
            $portable[$key] = $settings;
        }

        $tests = AdmissionTest::query()
            ->where('unit_id', $unit->id)
            ->get()
            ->keyBy('id');

        $portable['test_definitions'] = collect($portable['test_definitions'] ?? [])
            ->map(function (mixed $definition) use ($tests): ?array {
                if (! is_array($definition)) {
                    return null;
                }

                $test = $tests->get($definition['id'] ?? null);

                return $test ? [
                    'code' => $test->code,
                    'name' => $test->name,
                ] : null;
            })
            ->filter()
            ->values()
            ->all();

        return $portable;
    }

    /**
     * @param array<string, mixed> $portable
     * @return array<string, mixed>
     */
    private function restoreConfiguration(Unit $unit, array $portable): array
    {
        $data = Arr::only($portable, self::CONFIGURATION_FIELDS);

        foreach (['academic_score_settings', 'achievement_settings'] as $key) {
            $settings = is_array($data[$key] ?? null) ? $data[$key] : [];
            $pathwayNames = collect($settings['pathway_names'] ?? [])
                ->filter(fn (mixed $name): bool => is_string($name) && filled($name))
                ->values();

            $settings['pathway_uuids'] = $pathwayNames
                ->map(function (string $name) use ($unit): string {
                    $uuid = RegistrationPathway::query()
                        ->where('unit_id', $unit->id)
                        ->where('name', $name)
                        ->value('uuid');

                    if (! $uuid) {
                        throw ValidationException::withMessages([
                            'import_file' => 'Jalur Pendaftaran '.$name.' yang dipakai konfigurasi tidak ditemukan pada unit tujuan.',
                        ]);
                    }

                    return $uuid;
                })
                ->all();

            unset($settings['pathway_names']);
            $data[$key] = $settings;
        }

        $data['test_definitions'] = collect($data['test_definitions'] ?? [])
            ->map(function (mixed $definition) use ($unit): array {
                if (! is_array($definition)) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Referensi tes pada konfigurasi tidak valid.',
                    ]);
                }

                $query = AdmissionTest::query()->where('unit_id', $unit->id);

                if (filled($definition['code'] ?? null)) {
                    $query->where('code', $definition['code']);
                } elseif (filled($definition['name'] ?? null)) {
                    $query->where('name', $definition['name']);
                } else {
                    throw ValidationException::withMessages([
                        'import_file' => 'Referensi tes pada konfigurasi tidak memiliki kode atau nama.',
                    ]);
                }

                $id = $query->value('id');

                if (! $id) {
                    throw ValidationException::withMessages([
                        'import_file' => 'Tes '.($definition['name'] ?? $definition['code'] ?? '').' tidak ditemukan pada unit tujuan.',
                    ]);
                }

                return ['id' => $id];
            })
            ->values()
            ->all();

        return $data;
    }
}
