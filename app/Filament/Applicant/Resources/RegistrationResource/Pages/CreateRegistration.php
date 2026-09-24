<?php

namespace App\Filament\Applicant\Resources\RegistrationResource\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Services\ConfiguredRegistrationForm;
use App\Services\RegistrationRegionService;
use App\Services\RegistrationSupplementalDataService;
use App\Services\UnitConfigurationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    protected static bool $canCreateAnother = false;

    private array $validatedAcademicScores = [];

    private array $validatedAchievements = [];

    public ?string $openingUuid = null;

    public ?string $configurationUuid = null;

    public function mount(): void
    {
        $this->openingUuid = (string) request()->query('opening');

        $opening = RegistrationOpening::query()
            ->forOperationalMode()
            ->with(['unit', 'studyProgram'])
            ->where('uuid', $this->openingUuid)
            ->first();

        abort_unless($opening?->isOpen(), 403, 'Pendaftaran ini sedang tidak dibuka.');

        $configuration = app(UnitConfigurationService::class)->initialize($opening->unit);
        $this->configurationUuid = $configuration->uuid;

        parent::mount();

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
        $this->validatedAchievements = $supplemental->validateAchievements(
            $configuration,
            $pathway->uuid,
            $data['achievements'] ?? [],
        );
        unset($data['academic_scores'], $data['achievements']);

        $data['user_id'] = auth()->id();
        $data['registration_opening_id'] = $opening->id;
        $data['registration_pathway_id'] = $pathway->id;
        $data['unit_id'] = $opening->unit_id;
        $data['unit_configuration_id'] = $configuration->id;
        $data['registrant_relationship'] = ($data['registrant_type'] ?? 'parent') === 'self'
            ? 'self'
            : ($data['registrant_relationship'] ?? null);
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

            return $record;
        }, 5);
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
