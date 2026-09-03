<?php

namespace App\Filament\Applicant\Resources\RegistrationResource\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Models\RegistrationOpening;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    public function mount(): void
    {
        parent::mount();

        $opening = RegistrationOpening::query()
            ->with(['unit', 'studyProgram'])
            ->find(request()->integer('opening'));

        abort_unless($opening?->isOpen(), 403, 'Pendaftaran ini sedang tidak dibuka.');

        $this->form->fill([
            'registration_opening_id' => $opening->id,
            'unit_id' => $opening->unit_id,
            'registrant_type' => $opening->unit?->isHigherEducation() ? 'self' : 'parent',
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $opening = RegistrationOpening::query()
            ->with(['unit', 'studyProgram'])
            ->find($data['registration_opening_id'] ?? null);

        abort_unless($opening?->isOpen(), 403, 'Pendaftaran ini sudah ditutup.');

        if ($opening->unit?->isHigherEducation()) {
            abort_unless($opening->studyProgram, 422, 'Program studi pada pembukaan pendaftaran belum dikonfigurasi.');
            $opening->studyProgram->assertApplicantAge($data['birth_date'] ?? null);
            $data['registrant_type'] = 'self';
        }

        $data['user_id'] = auth()->id();
        $data['registration_opening_id'] = $opening->id;
        $data['unit_id'] = $opening->unit_id;
        $data['registrant_relationship'] = ($data['registrant_type'] ?? 'parent') === 'self'
            ? 'self'
            : ($data['registrant_relationship'] ?? null);
        $data['status'] = 'submitted';
        $data['current_stage'] = 'data_validation';
        $data['data_validation_status'] = 'pending';
        $data['submitted_at'] = now();

        return $data;
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
