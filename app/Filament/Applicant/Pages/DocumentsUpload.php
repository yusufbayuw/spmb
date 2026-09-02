<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Document;
use App\Models\Registration;
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

        $documents = $this->registrationRecord->documents->keyBy('type');

        $this->form->fill([
            'report_card' => $documents->get('report_card')?->file_path,
            'family_card' => $documents->get('family_card')?->file_path,
            'birth_certificate' => $documents->get('birth_certificate')?->file_path,
            'photo' => $documents->get('photo')?->file_path,
            'supporting_document' => $documents->get('supporting_document')?->file_path,
        ]);
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::FiveExtraLarge;
    }

    public function form(Form $form): Form
    {
        $documentUpload = fn (string $field, string $label, bool $imageOnly = false): FileUpload => FileUpload::make($field)
            ->label($label)
            ->disk('public')
            ->directory(fn (): string => 'documents/'.$this->registrationRecord->id)
            ->acceptedFileTypes($imageOnly ? ['image/jpeg', 'image/png'] : ['application/pdf', 'image/jpeg', 'image/png'])
            ->maxSize(5120)
            ->downloadable()
            ->openable()
            ->helperText(($imageOnly ? 'JPG/PNG' : 'PDF/JPG/PNG').' · maksimal 5 MB');

        return $form
            ->schema([
                Section::make('Dokumen Wajib')
                    ->description('Lengkapi empat dokumen wajib. Dokumen akan diperiksa oleh Tata Usaha.')
                    ->icon('heroicon-o-document-check')
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

    public function submit(): void
    {
        abort_unless(
            in_array($this->registrationRecord->fresh()->current_stage, ['documents', 'document_verification'], true),
            403,
        );

        $data = $this->form->getState();

        foreach (['report_card', 'family_card', 'birth_certificate', 'photo', 'supporting_document'] as $type) {
            $newPath = $data[$type] ?? null;
            if (! $newPath) {
                continue;
            }

            $existing = $this->registrationRecord->documents()->where('type', $type)->first();

            if ($existing?->file_path && $existing->file_path !== $newPath) {
                Storage::disk('public')->delete($existing->file_path);
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
                    'file_size' => Storage::disk('public')->exists($newPath) ? Storage::disk('public')->size($newPath) : null,
                    'is_verified' => false,
                    'verified_at' => null,
                    'verified_by' => null,
                ],
            );
        }

        $required = RegistrationWorkflowService::REQUIRED_DOCUMENTS;
        $uploaded = $this->registrationRecord->documents()->whereIn('type', $required)->pluck('type')->unique();
        $complete = collect($required)->every(fn (string $type): bool => $uploaded->contains($type));

        $this->registrationRecord->update([
            'current_stage' => $complete ? 'document_verification' : 'documents',
            'documents_completed_at' => $complete ? ($this->registrationRecord->documents_completed_at ?: now()) : null,
        ]);

        Notification::make()
            ->title($complete ? 'Dokumen wajib sudah lengkap' : 'Dokumen berhasil disimpan')
            ->body($complete ? 'Dokumen menunggu verifikasi Tata Usaha.' : 'Anda masih dapat kembali untuk melengkapi dokumen wajib lainnya.')
            ->success()
            ->send();

        $this->redirect(RegistrationStatus::getUrl(['registration' => $this->registrationRecord->id]));
    }
}
