<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\RegistrationEmailDeliveryService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ApplicantAccountRecovery extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Pemulihan Akun';
    protected static ?string $title = 'Pemulihan Akun Pendaftar';
    protected static ?string $navigationGroup = 'Sistem & Akses';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.admin.pages.applicant-account-recovery';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return (bool) $actor?->is_active
            && ($actor?->isAdmin() || ($actor?->isAdminUnit() && $actor?->unit_id !== null));
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('email')->label('Email akun pendaftar')->email()->required()->maxLength(254),
            Select::make('type')->label('Jenis bantuan')->options([
                'verification' => 'Kirim tautan verifikasi email',
                'password_reset' => 'Kirim tautan reset password',
            ])->required(),
            Textarea::make('reason')->label('Alasan bantuan')->maxLength(500)->required(),
        ])->statePath('data');
    }

    public function sendRecovery(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $actor = auth()->user();
        $email = mb_strtolower(trim($data['email']));

        // Do not expose whether an unscoped/unknown account exists.
        $user = User::query()->where('email', $email)
            ->when(! $actor->isAdmin(), fn ($query) => $query->where('unit_id', $actor->unit_id))
            ->first();

        $service = app(RegistrationEmailDeliveryService::class);
        if ($user && $service->canRecoverAccount($actor, $user)) {
            $service->recoverAccount($user, $actor, $data['type'], $data['reason']);
        }

        Notification::make()->success()
            ->title('Permintaan diperiksa')
            ->body('Jika akun memenuhi syarat, email bantuan dimasukkan ke antrean. Tidak ada kata sandi atau tahapan yang diubah.')
            ->send();
        $this->form->fill();
    }
}
