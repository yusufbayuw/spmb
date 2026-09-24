<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Document;
use App\Models\Registration;
use App\Services\ApplicantFileStorage;
use App\Services\ApplicantUploadSecurity;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class IdentityPhotoUpload extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Foto Identitas';

    protected static ?string $slug = 'foto-identitas/{registration}';

    protected static string $view = 'filament.applicant.pages.identity-photo-upload';

    public Registration $registrationRecord;

    public ?array $data = [];

    public function mount(int|string $registration): void
    {
        abort_unless(Str::isUuid($registration), 404);

        $this->registrationRecord = Registration::query()
            ->where('user_id', auth()->id())
            ->with(['documents', 'unit'])
            ->where('uuid', $registration)
            ->firstOrFail();

        abort_unless(
            $this->registrationRecord->isOperational()
                && filled($this->registrationRecord->registration_number),
            403,
        );

        $this->form->fill([]);
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::ThreeExtraLarge;
    }

    public function form(Form $form): Form
    {
        $verifiedPhoto = $this->registrationRecord->documents
            ->first(fn (Document $document): bool => $document->type === 'photo'
                && $document->superseded_at === null
                && $document->is_verified);

        return $form
            ->schema([
                Section::make('Foto Peserta')
                    ->description('Foto ini menjadi identitas visual pada Kartu Pendaftaran. Gunakan foto terbaru, wajah terlihat jelas, dan latar yang rapi.')
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Foto Identitas')
                            ->helperText($verifiedPhoto
                                ? 'Foto telah diverifikasi petugas dan tidak dapat diganti.'
                                : 'Format JPG/PNG, maksimal 5 MB. Foto disimpan pada private storage.')
                            ->disk(ApplicantFileStorage::PRIVATE_DISK)
                            ->directory('documents/'.$this->registrationRecord->id)
                            ->visibility('private')
                            ->storeFiles(false)
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->maxSize((int) config('spmb.uploads.max_kb', 5120))
                            ->image()
                            ->disabled((bool) $verifiedPhoto)
                            ->required(! $verifiedPhoto),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(ApplicantFileStorage $storage, ApplicantUploadSecurity $security): void
    {
        $state = $this->form->getState();
        $upload = $state['photo'] ?? null;

        if (! $upload instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages([
                'data.photo' => 'Pilih foto identitas dari perangkat Anda.',
            ]);
        }

        $storedPath = null;

        try {
            DB::transaction(function () use ($upload, $storage, $security, &$storedPath): void {
                $registration = Registration::query()
                    ->where('user_id', auth()->id())
                    ->lockForUpdate()
                    ->findOrFail($this->registrationRecord->id);

                abort_unless($registration->isOperational() && filled($registration->registration_number), 403);

                $verifiedPhotoExists = $registration->documents()
                    ->where('type', 'photo')
                    ->whereNull('superseded_at')
                    ->where('is_verified', true)
                    ->exists();

                if ($verifiedPhotoExists) {
                    throw ValidationException::withMessages([
                        'data.photo' => 'Foto identitas sudah diverifikasi petugas dan tidak dapat diganti.',
                    ]);
                }

                $extension = strtolower($upload->getClientOriginalExtension());
                $extension = $extension === 'jpeg' ? 'jpg' : $extension;

                if (! in_array($extension, ['jpg', 'png'], true)) {
                    throw ValidationException::withMessages([
                        'data.photo' => 'Foto identitas harus berformat JPG atau PNG.',
                    ]);
                }

                $storedPath = $upload->storeAs(
                    'documents/'.$registration->id,
                    Str::uuid().'.'.$extension,
                    ApplicantFileStorage::PRIVATE_DISK,
                );

                if (! is_string($storedPath) || ! $storage->privateDisk()->exists($storedPath)) {
                    throw ValidationException::withMessages([
                        'data.photo' => 'Foto gagal disimpan. Silakan coba unggah ulang.',
                    ]);
                }

                $inspection = $security->inspect($storedPath, ['jpg', 'png']);

                $registration->documents()
                    ->where('type', 'photo')
                    ->whereNull('superseded_at')
                    ->where('is_verified', false)
                    ->update(['superseded_at' => now()]);

                Document::create([
                    'registration_id' => $registration->id,
                    'requirement_key' => 'photo',
                    'attachment_index' => 0,
                    'type' => 'photo',
                    'file_path' => $storedPath,
                    'original_name' => Str::limit(
                        pathinfo(str_replace('\\', '/', $upload->getClientOriginalName()), PATHINFO_BASENAME),
                        255,
                        '',
                    ),
                    'file_type' => $extension,
                    'mime_type' => $inspection['mime_type'],
                    'file_size' => $inspection['size'],
                    'sha256' => $inspection['sha256'],
                    'malware_scan_status' => $inspection['malware_scan_status'],
                    'security_scanned_at' => $inspection['security_scanned_at'],
                    'is_verified' => false,
                ]);
            }, 5);
        } catch (Throwable $exception) {
            if ($storedPath && ! Document::query()->where('file_path', $storedPath)->exists()) {
                $storage->delete($storedPath);
            }

            throw $exception;
        }

        Notification::make()
            ->title('Foto identitas berhasil disimpan')
            ->body('Kartu pendaftaran akan menggunakan foto ini.')
            ->success()
            ->send();

        $this->redirect(RegistrationStatus::getUrl(['registration' => $this->registrationRecord->uuid]));
    }
}
