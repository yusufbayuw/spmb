<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Registration;
use App\Models\ReRegistrationItem;
use App\Services\ApplicantFileStorage;
use App\Services\ApplicantUploadSecurity;
use App\Services\SpmbNotificationService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReRegistration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Daftar Ulang';

    protected static ?string $slug = 'daftar-ulang/{registration}';

    protected static string $view = 'filament.applicant.pages.re-registration';

    public Registration $registrationRecord;

    public ?array $data = [];

    public function mount(int|string $registration): void
    {
        abort_unless(Str::isUuid($registration), 404);
        $this->registrationRecord = Registration::query()
            ->where('user_id', auth()->id())
            ->with(['configuration', 'reRegistrationItems'])
            ->where('uuid', $registration)
            ->firstOrFail();
        abort_unless($this->registrationRecord->current_stage === 're_registration', 403);

        $this->form->fill($this->registrationRecord->reRegistrationItems->mapWithKeys(
            fn (ReRegistrationItem $item): array => [$item->requirement_key => $item->type === 'document' ? $item->file_path : ($item->value['value'] ?? null)],
        )->all());
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::FiveExtraLarge;
    }

    public function form(Form $form): Form
    {
        $fields = [];
        foreach ($this->registrationRecord->reRegistrationItems as $item) {
            $label = $item->label.($item->is_required ? ' (wajib)' : '');
            $helper = $item->rejection_reason ? 'Ditolak: '.$item->rejection_reason : match ($item->status) {
                'verified' => 'Terverifikasi',
                'submitted' => 'Menunggu verifikasi petugas',
                default => 'Belum dikirim',
            };
            $field = match ($item->type) {
                'document' => Forms\Components\FileUpload::make($item->requirement_key)
                    ->disk(ApplicantFileStorage::PRIVATE_DISK)
                    ->directory('re-registration/'.$this->registrationRecord->id)
                    ->visibility('private')
                    ->previewable(false)
                    ->fetchFileInformation(false)
                    ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'])
                    ->maxSize((int) config('spmb.uploads.max_kb', 5120)),
                'checklist' => Forms\Components\Toggle::make($item->requirement_key)->onColor('success'),
                default => Forms\Components\Textarea::make($item->requirement_key)->rows(3)->maxLength(2000),
            };
            $fields[] = $field->label($label)->helperText($helper)->disabled($item->status === 'verified');
        }

        return $form->schema([
            Forms\Components\Section::make('Persyaratan Daftar Ulang')
                ->description('Kirim persyaratan yang diminta. Berkas dan informasi akan diperiksa petugas.')
                ->schema($fields)
                ->columns(2),
        ])->statePath('data');
    }

    public function submit(ApplicantUploadSecurity $security): void
    {
        $data = $this->form->getState();
        DB::transaction(function () use ($data, $security): void {
            $registration = Registration::query()->where('user_id', auth()->id())->lockForUpdate()->findOrFail($this->registrationRecord->id);
            $registration->assertCurrentStage('re_registration');
            foreach ($registration->reRegistrationItems()->lockForUpdate()->get() as $item) {
                if ($item->status === 'verified') {
                    continue;
                }
                $value = $data[$item->requirement_key] ?? null;
                if ($item->is_required && blank($value) && $item->type !== 'checklist') {
                    throw ValidationException::withMessages(['data.'.$item->requirement_key => 'Persyaratan ini wajib diisi.']);
                }
                if ($item->is_required && $item->type === 'checklist' && ! $value) {
                    throw ValidationException::withMessages(['data.'.$item->requirement_key => 'Konfirmasi ini wajib disetujui.']);
                }
                if (blank($value) && ! $item->is_required) {
                    continue;
                }

                $attributes = ['status' => 'submitted', 'rejection_reason' => null, 'verified_at' => null, 'verified_by' => null];
                if ($item->type === 'document') {
                    if (! str_starts_with((string) $value, 're-registration/'.$registration->id.'/')) {
                        throw ValidationException::withMessages(['data.'.$item->requirement_key => 'Lokasi berkas tidak sesuai pendaftaran.']);
                    }
                    $inspection = $security->inspect($value, ['pdf', 'docx', 'jpg', 'png']);
                    $attributes += [
                        'file_path' => $value,
                        'original_name' => basename($value),
                        'mime_type' => $inspection['mime_type'],
                        'value' => null,
                    ];
                } else {
                    $attributes += ['value' => ['value' => $value]];
                }
                $item->update($attributes);
            }
            app(SpmbNotificationService::class)->workflowEvent($registration, 'reregistration.submitted', 'Daftar ulang diperbarui', 'Persyaratan daftar ulang pendaftar menunggu pemeriksaan.', false, true);
        }, 5);

        Notification::make()->title('Daftar ulang berhasil dikirim')->success()->send();
        $this->redirect(RegistrationStatus::getUrl(['registration' => $this->registrationRecord->uuid]));
    }
}
