<?php

namespace App\Filament\Applicant\Resources\RegistrationResource\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Support\ApplicantAchievementValidationErrors;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationFormDraft;
use App\Services\RegistrationFormDraftService;
use App\Models\RegistrationPathway;
use App\Services\ConfiguredRegistrationForm;
use App\Services\PausedOpeningApplicantRedirect;
use App\Services\ContinuationCandidateMatcher;
use App\Services\RegistrationConsentService;
use App\Services\RegistrationRegionService;
use App\Services\RegistrationSupplementalDataService;
use App\Services\UnitConfigurationService;
use Filament\Actions\Action;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    protected static bool $canCreateAnother = false;

    private array $validatedAcademicScores = [];

    private array $validatedAchievements = [];

    private ?int $validatedPrivacyConsentId = null;

    public ?string $openingUuid = null;

    #[Locked]
    public int $draftRevision = 0;

    #[Locked]
    public bool $draftHasBeenResumed = false;

    public ?string $configurationUuid = null;

    #[Locked]
    public int $draftOwnerId = 0;

    /** @var list<string> */
    #[Locked]
    public array $draftFileKeys = [];

    public ?string $privacyConsentUuid = null;

    public array $privacyConsentPresentation = [];

    public ?string $continuationLookupFingerprint = null;

    /** @var array<string, mixed> */
    public array $continuationPrefilledValues = [];

    public function mount(): void
    {
        $this->openingUuid = (string) request()->query('opening');

        $opening = RegistrationOpening::query()
            ->forOperationalMode()
            ->with(['unit', 'studyProgram'])
            ->where('uuid', $this->openingUuid)
            ->first();

        if ($opening?->isPaused()) {
            $target = app(PausedOpeningApplicantRedirect::class)->url($opening, auth()->user());
            abort_unless($target, 404);

            $this->redirect($target);

            return;
        }

        abort_unless($opening?->isOpen(), 403, 'Pendaftaran ini sedang tidak dibuka.');

        $configuration = app(UnitConfigurationService::class)->initialize($opening->unit);
        $this->configurationUuid = $configuration->uuid;
        $this->draftOwnerId = (int) auth()->id();
        $this->draftFileKeys = collect($configuration->fields ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && ($field['type'] ?? null) === 'file')
            ->pluck('key')->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()->all();

        parent::mount();
        $this->draftRevision = app(RegistrationFormDraftService::class)->find(auth()->user(), $opening, $configuration)?->revision ?? 0;

        $prefill = $this->previousRegistrationPrefill($opening);
        $availablePathways = RegistrationPathway::query()
            ->availableForUnit((int) $opening->unit_id)
            ->orderBy('name')
            ->pluck('uuid');

        if ($availablePathways->count() === 1) {
            $prefill['registration_pathway_uuid'] = $availablePathways->first();
        }

        $this->form->fill([
            ...$prefill,
            'unit_configuration_uuid' => $configuration->uuid,
            'registration_opening_uuid' => $opening->uuid,
            'unit_uuid' => $opening->unit->uuid,
            'registrant_type' => 'parent',
        ]);

        $consentService = app(RegistrationConsentService::class);
        if ($consentService->isEnabled($configuration, $opening->unit)) {
            $this->privacyConsentPresentation = $consentService->render($configuration, $opening);
            $this->mountAction('privacyConsent');
        }
    }

    public function updatedDataNik(): void
    {
        $this->applyContinuationPrefill();
    }

    public function updatedDataBirthDate(): void
    {
        $this->applyContinuationPrefill();
    }

    public function applyContinuationPrefill(): void
    {
        $matcher = app(ContinuationCandidateMatcher::class);
        $openingUuid = data_get($this->data, 'registration_opening_uuid') ?: $this->openingUuid;
        $nik = data_get($this->data, 'nik');
        $birthDate = data_get($this->data, 'birth_date');

        $fingerprint = $matcher->fingerprint($openingUuid, $nik, $birthDate);

        if ($fingerprint === null) {
            return;
        }

        $candidate = $matcher->match($openingUuid, $nik, $birthDate);

        if (! $candidate) {
            $opening = RegistrationOpening::query()
                ->where('uuid', $openingUuid)
                ->first(['id', 'unit_id', 'academic_year']);

            Log::warning('continuation.prefill_not_matched', [
                'opening_uuid' => $openingUuid,
                'opening_id' => $opening?->id,
                'unit_id' => $opening?->unit_id,
                'academic_year' => $opening?->academic_year,
                'nik_suffix' => substr(preg_replace('/\D+/', '', (string) $nik) ?: '', -4),
                'birth_date' => is_scalar($birthDate) ? (string) $birthDate : get_debug_type($birthDate),
                'active_identity_matches' => $opening
                    ? \App\Models\ContinuationCandidate::query()
                        ->where('nik', preg_replace('/\D+/', '', (string) $nik))
                        ->whereDate('birth_date', $birthDate)
                        ->where('is_active', true)
                        ->get(['id', 'unit_id', 'academic_year'])
                        ->map(fn ($row): array => [
                            'id' => $row->id,
                            'unit_id' => $row->unit_id,
                            'academic_year' => $row->academic_year,
                        ])
                        ->all()
                    : [],
            ]);

            return;
        }

        // If this exact identity was looked up before but the previous attempt failed
        // to populate the form, allow the same fingerprint to try again.
        if ($fingerprint === $this->continuationLookupFingerprint
            && $this->continuationPrefilledValues !== []) {
            return;
        }

        foreach ($this->continuationPrefilledValues as $path => $previousValue) {
            if (data_get($this->data, $path) === $previousValue) {
                data_set($this->data, $path, null);
            }
        }

        $this->continuationPrefilledValues = [];
        $this->continuationLookupFingerprint = $fingerprint;

        foreach ($matcher->prefill($candidate) as $path => $value) {
            $currentValue = data_get($this->data, $path);
            $isSystemDefault = $path === 'religion' && $currentValue === 'Islam';

            if ((! blank($currentValue) && ! $isSystemDefault) || blank($value)) {
                continue;
            }

            data_set($this->data, $path, $value);
            $this->continuationPrefilledValues[$path] = $value;
        }

        Log::warning('continuation.prefill_applied', [
            'candidate_id' => $candidate->id,
            'opening_uuid' => $openingUuid,
            'fields' => array_keys($this->continuationPrefilledValues),
        ]);
    }

    public function privacyConsentAction(): Action
    {
        return Action::make('privacyConsent')
            ->modalHeading(fn (): string => (string) ($this->privacyConsentPresentation['title'] ?? 'Persetujuan Privasi & Data Pribadi'))
            ->modalDescription('Baca seluruh isi persetujuan sebelum melanjutkan ke formulir pendaftaran.')
            ->modalContent(fn (): View => view('filament.applicant.registration-consent', [
                'content' => (string) ($this->privacyConsentPresentation['content'] ?? ''),
            ]))
            ->form([
                Forms\Components\Checkbox::make('accepted')
                    ->label(fn (): string => (string) ($this->privacyConsentPresentation['confirmation_text'] ?? 'Saya telah membaca dan menyetujui persetujuan di atas.'))
                    ->accepted()
                    ->required(),
            ])
            ->modalSubmitActionLabel('Saya Setuju & Lanjutkan')
            ->modalCancelAction(fn (StaticAction $action): StaticAction => $action
                ->label('Kembali')
                ->color('gray')
                ->url(fn (): string => route('admissions.show', [
                    'registrationOpening' => $this->openingUuid,
                ])))
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalWidth(MaxWidth::FourExtraLarge)
            ->action(function (array $data): void {
                if (! (bool) ($data['accepted'] ?? false)) {
                    throw ValidationException::withMessages([
                        'accepted' => 'Anda harus menyetujui persetujuan sebelum melanjutkan.',
                    ]);
                }

                [$opening, $configuration] = $this->currentConsentContext();
                $consent = app(RegistrationConsentService::class)->accept(
                    auth()->user(),
                    $opening,
                    $configuration,
                    request()->ip(),
                    request()->userAgent(),
                );

                $this->privacyConsentUuid = $consent->uuid;
            });
    }

    /**
     * @return array{RegistrationOpening, \App\Models\UnitConfiguration}
     */
    private function currentConsentContext(): array
    {
        $opening = RegistrationOpening::query()
            ->forOperationalMode()
            ->with('unit')
            ->where('uuid', $this->openingUuid)
            ->first();

        abort_unless($opening?->isOpen(), 403, 'Pendaftaran ini sedang tidak dibuka.');

        $configuration = app(UnitConfigurationService::class)->current((int) $opening->unit_id);
        if (! $configuration || $configuration->uuid !== $this->configurationUuid) {
            Notification::make()
                ->warning()
                ->title('Konfigurasi pendaftaran berubah')
                ->body('Muat ulang halaman untuk membaca dan menyetujui persetujuan terbaru.')
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'accepted' => 'Konfigurasi berubah. Muat ulang halaman sebelum menyetujui.',
            ]);
        }

        return [$opening, $configuration];
    }

    /**
     * @return array<string, mixed>
     */
    private function previousRegistrationPrefill(RegistrationOpening $opening): array
    {
        $registration = Registration::query()
            ->where('user_id', auth()->id())
            ->where('registration_opening_id', $opening->id)
            ->with(['parentInfo', 'pathway'])
            ->latest()
            ->first()
            ?? Registration::query()
                ->where('user_id', auth()->id())
                ->with('parentInfo')
                ->latest()
                ->first();

        if (! $registration) {
            return [];
        }

        $prefill = Arr::only($registration->attributesToArray(), [
            'home_address',
            'rt',
            'rw',
            'village',
            'district',
            'city',
            'province',
            'province_code',
            'city_code',
            'district_code',
            'village_code',
            'postal_code',
        ]);

        if ((int) $registration->registration_opening_id === (int) $opening->id && $registration->pathway) {
            $prefill['registration_pathway_uuid'] = $registration->pathway->uuid;
        }

        if ($registration->parentInfo) {
            $prefill['parentInfo'] = Arr::only($registration->parentInfo->attributesToArray(), [
                'father_name',
                'father_nik',
                'father_birth_place',
                'father_birth_date',
                'father_education',
                'father_occupation',
                'father_workplace',
                'father_phone',
                'father_email',
                'father_income',
                'mother_name',
                'mother_nik',
                'mother_birth_place',
                'mother_birth_date',
                'mother_education',
                'mother_occupation',
                'mother_workplace',
                'mother_phone',
                'mother_email',
                'mother_income',
            ]);
        }

        return $prefill;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $opening = RegistrationOpening::query()
            ->forOperationalMode()
            ->with(['unit', 'studyProgram'])
            ->where('uuid', $data['registration_opening_uuid'] ?? null)
            ->first();

        abort_unless($opening?->isOpen(), 403, 'Pendaftaran ini sudah ditutup.');

        $pathway = RegistrationPathway::query()
            ->availableForUnit((int) $opening->unit_id)
            ->where('uuid', $data['registration_pathway_uuid'] ?? null)
            ->first();

        if (! $pathway) {
            throw ValidationException::withMessages([
                'registration_pathway_uuid' => 'Pilih jalur pendaftaran yang masih aktif untuk unit tujuan.',
            ]);
        }

        if ($opening->unit?->isHigherEducation()) {
            abort_unless($opening->studyProgram, 422, 'Program studi pada pembukaan pendaftaran belum dikonfigurasi.');
            $opening->studyProgram->assertApplicantAge($data['birth_date'] ?? null);
        }

        $configuration = app(UnitConfigurationService::class)->current($opening->unit_id);
        if (! $configuration || ($data['unit_configuration_uuid'] ?? null) !== $configuration->uuid) {
            Notification::make()->warning()->title('Konfigurasi pendaftaran berubah')->body('Muat ulang halaman untuk menggunakan formulir terbaru sebelum mengirim.')->persistent()->send();
            throw ValidationException::withMessages(['unit_configuration_uuid' => 'Konfigurasi berubah. Muat ulang formulir sebelum mengirim.']);
        }
        if (! $configuration->payment_enabled && (float) $opening->registration_fee > 0) {
            throw ValidationException::withMessages(['registration_opening_uuid' => 'Biaya formulir harus nol untuk alur tanpa pembayaran.']);
        }
        if (($data['unit_uuid'] ?? null) !== $opening->unit->uuid) {
            throw ValidationException::withMessages(['unit_uuid' => 'Unit pendaftaran tidak sesuai pembukaan yang dipilih.']);
        }

        $this->validatedPrivacyConsentId = null;
        $consentService = app(RegistrationConsentService::class);
        if ($consentService->isEnabled($configuration, $opening->unit)) {
            $consent = $consentService->pendingFor(
                $this->privacyConsentUuid,
                auth()->user(),
                $opening,
                $configuration,
            );

            if (! $consent) {
                Notification::make()
                    ->warning()
                    ->title('Persetujuan diperlukan')
                    ->body('Baca dan setujui persetujuan data pribadi sebelum mengirim formulir.')
                    ->persistent()
                    ->send();

                throw ValidationException::withMessages([
                    'privacy_consent' => 'Persetujuan data pribadi belum tercatat untuk formulir ini.',
                ]);
            }

            $this->validatedPrivacyConsentId = $consent->id;
        }

        $configuredForm = app(ConfiguredRegistrationForm::class);

        if ($configuredForm->hasActiveRegionFields($configuration)) {
            $data = app(RegistrationRegionService::class)->normalize($data);
        }

        $data['custom_answers'] = $configuredForm->validateAnswers($configuration, $data['custom_answers'] ?? []);

        $supplemental = app(RegistrationSupplementalDataService::class);
        $this->validatedAcademicScores = $supplemental->validateAcademicScores(
            $configuration,
            $pathway->uuid,
            $data['academic_scores'] ?? [],
        );
        try {
            $this->validatedAchievements = $supplemental->validateAchievements(
                $configuration,
                $pathway->uuid,
                $data['achievements'] ?? [],
            );
        } catch (ValidationException $exception) {
            throw ApplicantAchievementValidationErrors::forForm(
                $exception,
                $this->data['achievements'] ?? [],
            );
        }
        unset($data['academic_scores'], $data['achievements']);

        $data['user_id'] = auth()->id();
        $data['registration_opening_id'] = $opening->id;
        $data['registration_pathway_id'] = $pathway->id;
        $data['unit_id'] = $opening->unit_id;
        $data['unit_configuration_id'] = $configuration->id;

        if (($data['registrant_type'] ?? 'parent') === 'self') {
            $data['registrant_relationship'] = 'self';
        } else {
            $relationship = (string) ($data['registrant_relationship'] ?? '');

            if (! array_key_exists($relationship, $configuration->registrantRelationshipOptions())) {
                throw ValidationException::withMessages([
                    'registrant_relationship' => 'Pilih hubungan pendaftar yang tersedia untuk unit tujuan.',
                ]);
            }

            $data['registrant_relationship'] = $relationship;
        }
        $data['status'] = 'submitted';
        $data['current_stage'] = 'data_validation';
        $data['data_validation_status'] = 'pending';
        $data['submitted_at'] = now();
        unset($data['registration_opening_uuid'], $data['registration_pathway_uuid'], $data['unit_uuid'], $data['unit_configuration_uuid']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            DB::table('units')->where('id', $data['unit_id'])->update(['id' => DB::raw('id')]);

            // Serialize a last-moment submission with pause/resume updates.
            // Rechecking inside the transaction prevents a stale open form
            // from creating a registration after intake has been paused.
            $opening = RegistrationOpening::query()
                ->whereKey($data['registration_opening_id'])
                ->lockForUpdate()
                ->first();

            if (! $opening?->isOpen()) {
                throw ValidationException::withMessages([
                    'registration_opening_uuid' => 'Pendaftaran baru untuk periode ini tidak tersedia.',
                ]);
            }

            $current = app(UnitConfigurationService::class)->current($data['unit_id']);
            if ($current?->id !== (int) $data['unit_configuration_id']) {
                Notification::make()->warning()->title('Konfigurasi pendaftaran berubah')->body('Muat ulang halaman untuk menggunakan formulir terbaru sebelum mengirim.')->persistent()->send();
                throw ValidationException::withMessages(['unit_configuration_uuid' => 'Konfigurasi berubah. Muat ulang formulir sebelum mengirim.']);
            }

            $record = parent::handleRecordCreation($data);

            app(RegistrationSupplementalDataService::class)->sync(
                $record,
                $this->validatedAcademicScores,
                $this->validatedAchievements,
            );

            if ($this->validatedPrivacyConsentId !== null) {
                app(RegistrationConsentService::class)->attachToRegistration(
                    $this->validatedPrivacyConsentId,
                    $record,
                );
            }

            app(ContinuationCandidateMatcher::class)->linkRegistration($record);

            return $record;
        }, 5);
    }

    public function hasRegistrationDraft(): bool
    {
        if (! $this->openingUuid || ! auth()->id()) {
            return false;
        }

        return RegistrationFormDraft::query()
            ->where('user_id', auth()->id())
            ->whereHas('opening', fn ($query) => $query->where('uuid', $this->openingUuid))
            ->exists();
    }

    public function hasCurrentRegistrationDraft(): bool
    {
        $opening = RegistrationOpening::query()->where('uuid', $this->openingUuid)->first();
        $configuration = $opening ? app(UnitConfigurationService::class)->current((int) $opening->unit_id) : null;

        return $opening && $configuration && $configuration->uuid === $this->configurationUuid
            && app(RegistrationFormDraftService::class)->find(auth()->user(), $opening, $configuration) !== null;
    }

    public function saveRegistrationDraft(): void
    {
        $opening = RegistrationOpening::query()->where('uuid', $this->openingUuid)->firstOrFail();
        $configuration = app(UnitConfigurationService::class)->current((int) $opening->unit_id);
        abort_unless($configuration && $configuration->uuid === $this->configurationUuid, 409);
        // Never overwrite an existing draft until the applicant explicitly
        // resumes it. Opening a fresh form must not clobber earlier progress.
        if ($this->draftRevision > 0 && ! $this->draftHasBeenResumed) {
            throw ValidationException::withMessages([
                'draft' => 'Lanjutkan Draft terlebih dahulu agar data sebelumnya tidak tertimpa.',
            ]);
        }

        $draft = app(RegistrationFormDraftService::class)->save(
            auth()->user(), $opening, $configuration, $this->data, $this->draftRevision,
        );
        $this->draftRevision = $draft->revision;
        $this->draftHasBeenResumed = true;
        $this->dispatch('spmb-draft-clean');
        Notification::make()->title('Draft tersimpan di server')->success()->send();
    }

    public function resumeRegistrationDraft(): void
    {
        $opening = RegistrationOpening::query()->where('uuid', $this->openingUuid)->firstOrFail();
        $configuration = app(UnitConfigurationService::class)->current((int) $opening->unit_id);
        abort_unless($configuration && $configuration->uuid === $this->configurationUuid, 409);
        $draft = app(RegistrationFormDraftService::class)->find(auth()->user(), $opening, $configuration);
        if (! $draft) {
            return;
        }

        $this->draftRevision = $draft->revision;
        $this->draftHasBeenResumed = true;
        $data = array_replace_recursive($this->data, $draft->payload);
        $data['registration_opening_uuid'] = $opening->uuid;
        $data['unit_configuration_uuid'] = $configuration->uuid;
        $data['unit_uuid'] = $opening->unit->uuid;
        $this->form->fill($data);
        $this->dispatch('spmb-draft-clean');
        Notification::make()->title('Draft dipulihkan')->success()->send();
    }

    public function discardRegistrationDraft(): void
    {
        $opening = RegistrationOpening::query()->where('uuid', $this->openingUuid)->firstOrFail();
        app(RegistrationFormDraftService::class)->discard(auth()->user(), $opening);
        $this->draftRevision = 0;
        $this->draftHasBeenResumed = false;
        $this->dispatch('spmb-draft-clean');
        Notification::make()->title('Draft dihapus')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Action::make('saveAndExitRegistrationDraft')
                ->label('Simpan Draft & Keluar')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Simpan isian ke database tanpa mengirim pendaftaran. Berkas unggahan belum disertakan.')
                ->action(function (): void {
                    $this->saveRegistrationDraft();
                    $this->dispatch('spmb-draft-clean');
                    $this->redirect(RegistrationResource::getUrl('index'));
                }),
            Action::make('exitRegistrationForm')
                ->label('Keluar Tanpa Menyimpan')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->dispatch('spmb-draft-clean');
                    $this->redirect(RegistrationResource::getUrl('index'));
                }),
            Action::make('saveRegistrationDraft')
                ->label('Simpan Draft')
                ->icon('heroicon-o-document-check')
                ->color('gray')
                ->action('saveRegistrationDraft'),
            Action::make('resumeRegistrationDraft')
                ->label('Lanjutkan Draft')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Isian saat ini akan diganti dengan draft tersimpan. File perlu diunggah ulang.')
                ->visible(fn (): bool => $this->hasCurrentRegistrationDraft())
                ->action('resumeRegistrationDraft'),
            Action::make('discardRegistrationDraft')
                ->label('Hapus Draft')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->hasRegistrationDraft())
                ->action('discardRegistrationDraft'),
        ];
    }

    protected function afterCreate(): void
    {
        // Browser storage is cleared only after the registration transaction succeeds.
        $key = 'spmb:registration-draft:v1:'.$this->draftOwnerId.':'.$this->openingUuid.':'.$this->configurationUuid;
        session()->flash('spmb_registration_draft_completed', $key);
        $opening = RegistrationOpening::query()->where('uuid', $this->openingUuid)->first();
        if ($opening) {
            app(RegistrationFormDraftService::class)->discard(auth()->user(), $opening);
        }
        $this->dispatch('spmb-registration-created', key: $key);
        $this->dispatch('spmb-draft-clean');
    }

    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        // Some supplementary validation rules are evaluated after Filament has
        // dehydrated the form (including repeater rows). Their error keys do not
        // always correspond to a currently rendered field. Never leave an
        // applicant with a submit button that appears to do nothing.
        $firstMessage = collect($exception->errors())->flatten()->first();

        Notification::make()
            ->danger()
            ->title('Pendaftaran belum dapat dikirim')
            ->body(is_string($firstMessage) && $firstMessage !== ''
                ? $firstMessage
                : 'Periksa kembali seluruh isian dan berkas yang diwajibkan.')
            ->persistent()
            ->send();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Kirim Pendaftaran');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pendaftaran berhasil dikirim dan menunggu validasi petugas';
    }
}
