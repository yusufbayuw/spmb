<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Document;
use App\Models\Registration;
use App\Services\ApplicantFileStorage;
use App\Services\ApplicantUploadSecurity;
use App\Services\RegistrationWorkflowService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Throwable;

class DocumentsUpload extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Dokumen Pendaftaran';
    protected static ?string $slug = 'dokumen/{registration}';
    protected static string $view = 'filament.applicant.pages.documents-upload';

    public Registration $registrationRecord;
    public ?array $data = [];

    public function mount(int|string $registration): void
    {
        $this->registrationRecord = Registration::query()
            ->where('user_id', auth()->id())
            ->with('documents')
            ->findOrFail($registration);

        abort_unless(
            $this->registrationRecord->isOperational()
            && in_array($this->registrationRecord->current_stage, ['documents', 'document_verification'], true),
            403,
        );

        $this->form->fill([]);
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::FiveExtraLarge;
    }

    public function form(Form $form): Form
    {
        $maxMb = ((int) config('spmb.uploads.max_kb', 5120)) / 1024;
        $documentUpload = fn (string $field, string $label, bool $imageOnly = false): FileUpload => FileUpload::make($field)
            ->label($label)
            ->disk(ApplicantFileStorage::PRIVATE_DISK)
            ->directory(fn (): string => 'documents/'.$this->registrationRecord->id)
            ->visibility('private')
            ->previewable(false)
            ->acceptedFileTypes($imageOnly ? ['image/jpeg', 'image/png'] : ['application/pdf', 'image/jpeg', 'image/png'])
            ->maxSize((int) config('spmb.uploads.max_kb', 5120))
            ->helperText(($imageOnly ? 'JPG/PNG' : 'PDF/JPG/PNG')." · maksimal {$maxMb} MB · diverifikasi berdasarkan isi file");

        return $form
            ->schema([
                Section::make('Dokumen Wajib')
                    ->description('File diperiksa MIME, signature, hash SHA-256, dan antivirus bila diwajibkan server sebelum disimpan sebagai dokumen pendaftaran.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        $documentUpload('report_card', 'Rapor'),
                        $documentUpload('family_card', 'Kartu Keluarga'),
                        $documentUpload('birth_certificate', 'Akta Kelahiran'),
                        $documentUpload('photo', 'Pas Foto', true),
                    ])->columns(2),
                Section::make('Dokumen Pendukung')
                    ->collapsed()
                    ->schema([$documentUpload('supporting_document', 'Dokumen pendukung')]),
            ])
            ->statePath('data');
    }

    public function submit(ApplicantFileStorage $storage, ApplicantUploadSecurity $security): void
    {
        $this->registrationRecord = Registration::query()
            ->with('documents')
            ->findOrFail($this->registrationRecord->id);
        $this->registrationRecord->assertCurrentStage(['documents', 'document_verification']);

        $data = $this->form->getState();

        foreach (['report_card', 'family_card', 'birth_certificate', 'photo', 'supporting_document'] as $type) {
            $newPath = $data[$type] ?? null;

            if (! $newPath) {
                continue;
            }

            $existing = $this->registrationRecord->documents()->where('type', $type)->first();

            if ($existing?->file_path === $newPath) {
                continue;
            }

            try {
                $inspection = $security->inspect($newPath);
            } catch (Throwable $exception) {
                $storage->delete($newPath);
                throw $exception;
            }

            if ($existing?->file_path) {
                $storage->delete($existing->file_path);
            }

            Document::updateOrCreate(
                ['registration_id' => $this->registrationRecord->id, 'type' => $type],
                [
                    'file_path' => $newPath,
                    'original_name' => basename($newPath),
                    'file_type' => pathinfo($newPath, PATHINFO_EXTENSION),
                    'mime_type' => $inspection['mime_type'],
                    'file_size' => $inspection['size'],
                    'sha256' => $inspection['sha256'],
                    'malware_scan_status' => $inspection['malware_scan_status'],
                    'security_scanned_at' => $inspection['security_scanned_at'],
                    'is_verified' => false,
                    'verified_at' => null,
                    'verified_by' => null,
                ],
            );
        }

        $required = RegistrationWorkflowService::REQUIRED_DOCUMENTS;
        $uploaded = $this->registrationRecord->documents()->whereIn('type', $required)->pluck('type')->unique();
        $complete = collect($required)->every(fn (string $type): bool => $uploaded->contains($type));

        $this->registrationRecord->transitionTo(
            $complete ? 'document_verification' : 'documents',
            [
                'documents_completed_at' => $complete ? ($this->registrationRecord->documents_completed_at ?: now()) : null,
                'documents_verified_at' => null,
            ],
        );

        $this->registrationRecord->load('documents');

        Notification::make()
            ->title($complete ? 'Dokumen wajib sudah lengkap' : 'Dokumen berhasil disimpan')
            ->body($complete ? 'Dokumen lolos pemeriksaan keamanan awal dan menunggu verifikasi Tata Usaha.' : 'Anda masih dapat kembali untuk melengkapi dokumen wajib lainnya.')
            ->success()
            ->send();

        $this->redirect(RegistrationStatus::getUrl(['registration' => $this->registrationRecord->id]));
    }
}
