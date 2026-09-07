<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Document;
use App\Models\Registration;
use App\Services\ApplicantFileStorage;
use App\Services\ApplicantUploadSecurity;
use App\Services\RegistrationWorkflowService;
use App\Services\SpmbNotificationService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        abort_unless(Str::isUuid($registration), 404);
        $this->registrationRecord = Registration::query()->where('user_id', auth()->id())->with(['documents', 'unit', 'configuration'])->where('uuid', $registration)->firstOrFail();
        abort_unless($this->registrationRecord->isOperational() && in_array($this->registrationRecord->current_stage, ['documents', 'document_verification'], true), 403);
        // Existing private files are rendered through authenticated routes in the Blade view.
        // Never hydrate private storage paths back into FileUpload: FilePond would try to
        // resolve them as browser-accessible files instead of our authorized controller.
        $this->form->fill([]);
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::FiveExtraLarge;
    }

    public function form(Form $form): Form
    {
        $fields = [];
        foreach ($this->registrationRecord->documentRequirements() as $requirement) {
            $mimes = array_map(fn (string $format): string => match ($format) {
                'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'png' => 'image/png', default => 'image/jpeg'
            }, $requirement['formats']);

            $existing = $this->registrationRecord->documents
                ->filter(fn (Document $document): bool => ($document->requirement_key ?: $document->type) === $requirement['key']);
            $verifiedCount = $existing->where('is_verified', true)->count();
            $remainingSlots = max(0, (int) $requirement['max_files'] - $verifiedCount);
            $locked = $remainingSlots === 0;

            $helper = trim((string) ($requirement['instructions'] ?? ''));
            $helper .= ($helper !== '' ? ' · ' : '').strtoupper(implode('/', $requirement['formats'])).' · maksimal 5 MB/file';
            if ($verifiedCount > 0) {
                $helper .= " · {$verifiedCount} lampiran telah diverifikasi TU dan tidak dapat diganti";
            }

            $field = FileUpload::make($requirement['key'])
                ->label($requirement['label'].($requirement['required'] ? ' (wajib)' : ' (opsional)'))
                ->helperText($helper)
                ->disk(ApplicantFileStorage::PRIVATE_DISK)
                ->directory('documents/'.$this->registrationRecord->id)
                ->visibility('private')
                ->previewable(false)
                ->fetchFileInformation(false)
                ->acceptedFileTypes($mimes)
                ->maxSize((int) config('spmb.uploads.max_kb', 5120))
                ->multiple($requirement['max_files'] > 1)
                ->disabled($locked);

            if ($requirement['max_files'] > 1) {
                $field->maxFiles(max(1, $remainingSlots));
            }

            $fields[] = $field;
        }

        return $form->schema([Section::make('Dokumen Pendaftaran')->schema($fields)->columns(2)])->statePath('data');
    }

    public function submit(ApplicantUploadSecurity $security): void
    {
        $data = $this->form->getState();
        $changed = false;
        DB::transaction(function () use ($data, $security, &$changed): void {
            $registration = Registration::query()->where('user_id', auth()->id())->lockForUpdate()->findOrFail($this->registrationRecord->id);
            $registration->assertCurrentStage(['documents', 'document_verification']);
            foreach ($registration->documentRequirements() as $requirement) {
                $paths = array_values(array_filter((array) ($data[$requirement['key']] ?? [])));
                $existing = $registration->documents()->where(fn ($q) => $q->where('requirement_key', $requirement['key'])->orWhere(fn ($q) => $q->whereNull('requirement_key')->where('type', $requirement['key'])))->get();
                $verified = $existing->where('is_verified', true);
                $replaceable = $existing->where('is_verified', false)->sortBy('attachment_index')->values();
                $remainingSlots = max(0, (int) $requirement['max_files'] - $verified->count());

                // Existing verified files are immutable. Empty upload fields mean "no change"
                // because existing private files are rendered separately above the form.
                if ($paths === []) {
                    continue;
                }

                if ($remainingSlots === 0) {
                    throw ValidationException::withMessages([
                        'data.'.$requirement['key'] => 'Berkas ini sudah diverifikasi TU dan tidak dapat diunggah ulang.',
                    ]);
                }

                if (count($paths) > $remainingSlots) {
                    throw ValidationException::withMessages([
                        'data.'.$requirement['key'] => "Maksimal {$remainingSlots} lampiran masih dapat diunggah. Lampiran yang sudah diverifikasi TU tidak dapat diganti.",
                    ]);
                }

                $reservedIndexes = $verified->pluck('attachment_index')->map(fn ($index): int => (int) $index)->all();
                $usedReplaceableIds = [];

                foreach ($paths as $path) {
                    if ($verified->contains('file_path', $path)) {
                        continue;
                    }
                    if (! str_starts_with($path, 'documents/'.$registration->id.'/')) {
                        throw ValidationException::withMessages(['file' => 'Lokasi berkas tidak sesuai pendaftaran.']);
                    }

                    $inspection = $security->inspect($path, $requirement['formats']);
                    $document = $replaceable->first(fn (Document $candidate): bool => ! in_array($candidate->id, $usedReplaceableIds, true));

                    if ($document) {
                        $usedReplaceableIds[] = $document->id;
                        $attachmentIndex = (int) $document->attachment_index;
                    } else {
                        $attachmentIndex = 0;
                        while (in_array($attachmentIndex, $reservedIndexes, true)) {
                            $attachmentIndex++;
                        }
                        $reservedIndexes[] = $attachmentIndex;
                        $document = new Document(['registration_id' => $registration->id]);
                    }

                    $document->fill([
                        'requirement_key' => $requirement['key'], 'attachment_index' => $attachmentIndex,
                        'type' => in_array($requirement['key'], ['report_card', 'family_card', 'birth_certificate', 'photo', 'supporting_document'], true) ? $requirement['key'] : 'supporting_document',
                        'file_path' => $path, 'original_name' => basename($path), 'file_type' => pathinfo($path, PATHINFO_EXTENSION),
                        'mime_type' => $inspection['mime_type'], 'file_size' => $inspection['size'], 'sha256' => $inspection['sha256'], 'malware_scan_status' => $inspection['malware_scan_status'], 'security_scanned_at' => $inspection['security_scanned_at'],
                        'is_verified' => false, 'verified_at' => null, 'verified_by' => null, 'rejection_reason' => null, 'superseded_at' => null,
                    ])->save();
                    $changed = true;
                }

                foreach ($replaceable as $document) {
                    if (! in_array($document->id, $usedReplaceableIds, true)) {
                        $document->update(['superseded_at' => now()]);
                        $changed = true;
                    }
                }
            }
            $complete = $registration->documentsComplete();
            $registration->transitionTo($complete ? 'document_verification' : 'documents', ['documents_completed_at' => $complete ? ($registration->documents_completed_at ?: now()) : null, 'documents_verified_at' => null]);
            if ($complete) {
                app(RegistrationWorkflowService::class)->refreshDocumentStage($registration);
            }
            if ($changed) {
                app(SpmbNotificationService::class)->workflowEvent($registration, 'documents.submitted', 'Berkas pendaftaran diperbarui', 'Berkas baru menunggu pemeriksaan petugas.', false, true);
            }
        });
        Notification::make()->title('Dokumen berhasil disimpan')->success()->send();
        $this->redirect(RegistrationStatus::getUrl(['registration' => $this->registrationRecord->uuid]));
    }
}
