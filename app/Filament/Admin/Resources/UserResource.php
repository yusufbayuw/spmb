<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\ManualApplicantEmailVerificationService;
use App\Services\RegistrationEmailDeliveryService;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $navigationGroup = 'Sistem & Akses';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required(),
            Forms\Components\TextInput::make('username')
                ->label('Username')
                ->helperText('Opsional. Gunakan huruf kecil, angka, titik, garis bawah, atau tanda minus. Username dapat digunakan untuk login.')
                ->maxLength(100)
                ->rule('regex:/^[a-z0-9._-]+$/')
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('phone')->label('Telepon')->tel(),
            Forms\Components\Select::make('roles')->relationship('roles', 'name')->multiple()->preload()->searchable()->required(),
            Forms\Components\Select::make('unit_id')->relationship('unit', 'name')->label('Unit')->preload(),
            Forms\Components\TextInput::make('password')->password()->revealable()->dehydrated(fn ($state) => filled($state))->required(fn (string $context) => $context === 'create'),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
            Forms\Components\Section::make('Persetujuan Akun')
                ->description('Riwayat persetujuan platform yang disimpan sebagai snapshot per versi kebijakan.')
                ->schema([
                    Forms\Components\Placeholder::make('account_consents_audit')
                        ->label('')
                        ->content(fn (?User $record) => view('user.consent-summary', ['user' => $record]))
                        ->columnSpanFull(),
                ])
                ->hiddenOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('username')->label('Username')->searchable()->placeholder('-'),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Role')->badge(),
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->default('-'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('manualVerifyApplicantEmail')
                    ->label('Verifikasi Manual')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(fn (User $record): bool => (bool) auth()->user()?->is_active
                        && (auth()->user()?->isAdmin() ?? false)
                        && $record->is_active
                        && $record->hasRole('pendaftar')
                        && ! $record->hasAnyRole(['super_admin', 'admin_unit', 'tu'])
                        && ! $record->hasVerifiedEmail())
                    ->modalHeading(fn (User $record): string => 'Verifikasi manual: '.$record->email)
                    ->modalDescription('Pengecualian ini menandai email sebagai terverifikasi tanpa tautan email. Periksa identitas pendaftar lebih dahulu. Semua tindakan dicatat di Audit Trail.')
                    ->modalSubmitActionLabel('Konfirmasi verifikasi manual')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('confirmed_email')
                            ->label('Ketik ulang alamat email yang akan diverifikasi')
                            ->email()->required()->maxLength(254),
                        Forms\Components\Select::make('verification_method')
                            ->label('Metode pemeriksaan identitas')
                            ->options(ManualApplicantEmailVerificationService::METHODS)
                            ->required(),
                        Forms\Components\TextInput::make('evidence_reference')
                            ->label('Nomor tiket / referensi bukti pemeriksaan')
                            ->required()->minLength(4)->maxLength(120)
                            ->helperText('Isi ID tiket atau referensi berkas saja; jangan cantumkan NIK, nomor telepon, atau dokumen pribadi.'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan verifikasi manual')
                            ->required()->minLength(10)->maxLength(500)
                            ->helperText('Jelaskan mengapa verifikasi email normal tidak dapat digunakan. Jangan masukkan data pribadi sensitif.'),
                        Forms\Components\Checkbox::make('identity_checked')
                            ->label('Saya sudah memeriksa identitas pendaftar dan memastikan bukti pemeriksaannya tersimpan.')
                            ->required()->rule('accepted'),
                    ])
                    ->action(function (User $record, array $data): void {
                        app(ManualApplicantEmailVerificationService::class)->verify(
                            auth()->user(),
                            $record,
                            $data,
                        );

                        Notification::make()
                            ->title('Email pendaftar berhasil diverifikasi secara manual')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('recoverApplicantAccount')
                    ->label('Pemulihan Email Akun')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->visible(fn (User $record): bool => auth()->user() !== null
                        && app(RegistrationEmailDeliveryService::class)->canRecoverAccount(auth()->user(), $record))
                    ->form([
                        Forms\Components\Select::make('type')->label('Jenis email')->required()->options(
                            fn (User $record): array => $record->hasVerifiedEmail()
                                ? ['password_reset' => 'Reset Password']
                                : ['verification' => 'Verifikasi Email', 'password_reset' => 'Reset Password']
                        ),
                        Forms\Components\Textarea::make('reason')->label('Alasan')->required()->maxLength(500),
                    ])
                    ->action(function (User $record, array $data): void {
                        app(RegistrationEmailDeliveryService::class)->recoverAccount(
                            $record, auth()->user(), $data['type'], $data['reason']
                        );
                        Notification::make()->title('Pemulihan diantrekan')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
