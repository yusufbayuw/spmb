<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Document;
use App\Models\Registration;
use App\Services\ApplicantFileStorage;
use App\Services\RegistrationWorkflowService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Storage;

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
            in_array($this->registrationRecord->current_stage, ['documents', 'document_verification'], true),
            403,
        );

        // Existing files are shown via authenticated preview links in the Blade view.
        // Do not hydrate private storage paths into FileUpload because that would make
        // Filament generate temporary file URLs outside our authorization controller.
        $this->form->fill([]);
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::FiveExtraLarge;
    }

    public function form(Form $form): Form
    {
        $documentUpload = fn (string $field, string $label, bool $imageOnly = false): FileUpload => FileUpload::make($field)
            ->label($label)
            ->disk(ApplicantFileStorage::PRIVATE_DISK)
            ->directory(fn (): string => 'documents/'.$this->registrationRecord->id)
            ->visibility('private')
            ->previewable(false)
            ->acceptedFileTypes($imageOnly ? ['image/jpeg', 'image/png'] : ['application/pdf', 'image/jpeg', 'image/png'])
            ->maxSize(5120)
            ->helperText(($imageOnly ? 'JPG/PNG' : 'PDF/JPG/PNG').' · maksimal 5 MB · tersimpan privat');

        return $form
            ->schema([
                Section::make('Dokumen Wajib')
                    ->description('Lengkapi empat dokumen wajib. File tersimpan privat dan hanya dapat diakses oleh akun berwenang.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        $documentUpload('report_card', 'Rapor'),
                        $documentUpload('family_card', 'Kartu Keluarga'),
                        $documentUpload('birth_certificate', 'Akta Kelahiran'),
                        $documentUpload('photo', 'Pas Foto', true),
                    ])->columns(2),
                Section::make('Dokumen Pendukung')
                    ->collapsed()
                    ->schema([
                        $documentUpload('supporting_document', 'Dokumen pendukung'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(ApplicantFileStorage $storage): void
    {
        $this->registrationRecord = Registration::query()
            ->with('documents')
            ->findOrFail($this->registrationRecord->id);
        $this->registrationRecord->assertCurrentStage(['documents', 'document_verification']);

        $data = $this->form->getState();
        $private = Storage::disk(ApplicantFileStorage::PRIVATE_DISK);

        foreach (['report_card', 'family_card', 'birth_certificate', 'photo', 'supporting_document'] as $type) {
            $newPath = $data[$type] ?? null;

            if (! $newPath) {
                continue;
            }

            $existing = $this->registrationRecord->documents()->where('type', $type)->first();

            if ($existing?->file_path && $existing->file_path !== $newPath) {
                $storage->delete($existing->file_path);
            }

            if ($existing?->file_path === $newPath) {
                continue;
            }

            Document::updateOrCreate(
                ['registration_id' => $this->registrationRecord->id, 'type' => $type],
                [
                    'file_path' => $newPath,
                    'original_name' => basename($newPath),
                    'file_type' => pathinfo($newPath, PATHINFO_EXTENSION),
                    'file_size' => $private->exists($newPath) ? $private->size($newPath) : null,
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
                'documents_completed_at' => $complete
                    ? ($this->registrationRecord->documents_completed_at ?: now())
                    : null,
                'documents_verified_at' => null,
            ],
        );

        $this->registrationRecord->load('documents');

        Notification::make()
            ->title($complete ? 'Dokumen wajib sudah lengkap' : 'Dokumen berhasil disimpan')
            ->body($complete ? 'Dokumen tersimpan privat dan menunggu verifikasi Tata Usaha.' : 'Anda masih dapat kembali untuk melengkapi dokumen wajib lainnya.')
            ->success()
            ->send();

        $this->redirect(RegistrationStatus::getUrl(['registration' => $this->registrationRecord->id]));
    }
}
