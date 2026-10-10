<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StaffProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Profil Saya';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identitas akun')
                ->description('Nama dapat diperbarui. Identitas dan hak akses dikelola administrator.')
                ->schema([
                    $this->getNameFormComponent()->label('Nama lengkap'),
                    TextInput::make('username')
                        ->label('Username')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('email')
                        ->label('Email')
                        ->disabled()
                        ->dehydrated(false),
                    Placeholder::make('role_display')
                        ->label('Peran')
                        ->content(fn (): string => $this->getUser()->roles->pluck('name')->implode(', ') ?: '—'),
                    Placeholder::make('unit_display')
                        ->label('Unit')
                        ->content(fn (): string => $this->getUser()->unit?->name ?? '—'),
                ]),
            Section::make('Keamanan akun')
                ->description('Kosongkan kolom password jika tidak ingin mengubahnya.')
                ->schema([
                    TextInput::make('current_password')
                        ->label('Password saat ini')
                        ->password()
                        ->autocomplete('current-password')
                        ->required(fn (Get $get): bool => filled($get('password')))
                        ->rule('current_password:web')
                        ->dehydrated(false),
                    $this->getPasswordFormComponent()->label('Password baru'),
                    $this->getPasswordConfirmationFormComponent()->label('Konfirmasi password baru'),
                ]),
        ]);
    }

    /**
     * Never accept a role, unit, email, username, or activation change
     * from a self-service profile request, even if a client tampers with it.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'password']));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(
            $record instanceof User && $record->is_active
                && $record->hasAnyRole(['super_admin', 'admin_unit', 'tu']),
            403,
        );

        $record->name = $data['name'];

        if (array_key_exists('password', $data)) {
            // Filament already validates, confirms, and hashes this field.
            $record->password = $data['password'];
            $record->auth_version = ((int) $record->auth_version) + 1;
            $record->setRememberToken(Str::random(60));
        }

        $record->save();

        if (array_key_exists('password', $data) && request()->hasSession()) {
            // Other staff sessions fail the auth-version middleware check;
            // retain only the session which successfully changed the password.
            request()->session()->put('spmb_staff_auth_version', (int) $record->auth_version);
        }

        return $record;
    }
}
