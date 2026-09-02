<?php

namespace App\Filament\Applicant\Resources;

use App\Filament\Applicant\Pages\RegistrationOpenings;
use App\Filament\Applicant\Pages\RegistrationStatus;
use App\Filament\Applicant\Resources\RegistrationResource\Pages;
use App\Filament\Forms\ParentInfoFields;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Pendaftaran Saya';
    protected static ?string $modelLabel = 'Pendaftaran';
    protected static ?string $pluralModelLabel = 'Pendaftaran Saya';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pilihan Pendaftaran')
                ->description('Unit, tahun ajaran, gelombang, jalur, dan biaya mengikuti pembukaan yang Anda pilih.')
                ->columns(2)
                ->schema([
                    Forms\Components\Hidden::make('registration_opening_id')->required(),
                    Forms\Components\Hidden::make('unit_id')->required(),
                    Forms\Components\Placeholder::make('opening_summary')
                        ->label('Pembukaan Pendaftaran')
                        ->content(function (Forms\Get $get): string {
                            $opening = RegistrationOpening::query()->with('unit')->find($get('registration_opening_id'));
                            return $opening ? $opening->label().' · Biaya '.$opening->formattedFee() : 'Pilih pembukaan pendaftaran terlebih dahulu.';
                        })
                        ->columnSpanFull(),
                    Forms\Components\Select::make('registrant_type')
                        ->label('Pendaftaran dilakukan oleh')
                        ->options(['parent' => 'Orang Tua / Wali', 'self' => 'Calon Siswa Sendiri'])
                        ->default('parent')->required()->live(),
                    Forms\Components\Select::make('registrant_relationship')
                        ->label('Hubungan dengan calon siswa')
                        ->options(['father'=>'Ayah','mother'=>'Ibu','guardian'=>'Wali','other'=>'Lainnya'])
                        ->required(fn (Forms\Get $get): bool => $get('registrant_type') === 'parent')
                        ->visible(fn (Forms\Get $get): bool => $get('registrant_type') === 'parent'),
                ]),

            Forms\Components\Section::make('Identitas Calon Siswa')
                ->description('Gunakan data yang sama dengan dokumen resmi calon siswa.')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('nik')
                        ->label('NIK')->required()->rule('digits:16')
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Forms\Get $get): Unique => $rule
                                ->where('registration_opening_id', $get('registration_opening_id')),
                        ),
                    Forms\Components\TextInput::make('full_name')->label('Nama Lengkap')->required()->maxLength(150)->columnSpan(2),
                    Forms\Components\TextInput::make('nickname')->label('Nama Panggilan')->maxLength(50),
                    Forms\Components\Select::make('gender')->label('Jenis Kelamin')->options(['L'=>'Laki-laki','P'=>'Perempuan'])->required(),
                    Forms\Components\Select::make('religion')->label('Agama')->options(['Islam'=>'Islam','Kristen'=>'Kristen','Katolik'=>'Katolik','Hindu'=>'Hindu','Buddha'=>'Buddha','Konghucu'=>'Konghucu'])->default('Islam'),
                    Forms\Components\TextInput::make('birth_place')->label('Tempat Lahir')->required()->maxLength(100),
                    Forms\Components\DatePicker::make('birth_date')->label('Tanggal Lahir')->required()->native(false)->maxDate(now()->subDay()),
                    Forms\Components\TextInput::make('phone')->label('Nomor Telepon')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('email')->label('Email Calon Siswa')->email()->maxLength(100),
                    Forms\Components\Textarea::make('home_address')->label('Alamat Rumah')->required()->rows(3)->columnSpanFull(),
                    Forms\Components\TextInput::make('previous_school')->label('Sekolah Asal')->maxLength(150)->columnSpan(2),
                    Forms\Components\TextInput::make('graduation_year')->label('Tahun Lulus')->numeric()->minValue(2000)->maxValue(now()->year + 2),
                ]),

            Forms\Components\Section::make('Data Orang Tua')
                ->description('Data ayah dan ibu dipisahkan agar lebih mudah diisi dan diperiksa.')
                ->relationship('parentInfo')
                ->schema(ParentInfoFields::schema()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')->label('Calon Siswa')->searchable()->weight('medium')->description(fn (Registration $record): ?string => $record->registration_number),
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('opening.academic_year')->label('Tahun Ajaran'),
                Tables\Columns\TextColumn::make('opening.wave')->label('Gelombang')->description(fn (Registration $record): ?string => $record->opening?->pathway),
                Tables\Columns\TextColumn::make('opening.registration_fee')->label('Biaya')->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('current_stage')->label('Tahap Saat Ini')->badge()->formatStateUsing(fn (Registration $record): string => $record->stageLabel()),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label('Status Pendaftaran')->badge()
                    ->formatStateUsing(fn (Registration $record): string => $record->lifecycleLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'withdrawn' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('data_validation_status')
                    ->label('Validasi')->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'valid' => 'Valid', 'revision' => 'Perlu Revisi', default => 'Menunggu',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'valid' => 'success', 'revision' => 'warning', default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('progress')
                    ->label('Lihat Progres')->icon('heroicon-o-arrow-right-circle')
                    ->url(fn (Registration $record): string => RegistrationStatus::getUrl(['registration' => $record->id])),
                Tables\Actions\EditAction::make()
                    ->label('Perbaiki Data')
                    ->visible(fn (Registration $record): bool => static::canEdit($record)),
                Tables\Actions\Action::make('withdraw')
                    ->label('Mengundurkan Diri')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('danger')
                    ->visible(fn (Registration $record): bool => $record->isOperational() && $record->current_stage !== 'completed')
                    ->form([Forms\Components\Textarea::make('reason')->label('Alasan pengunduran diri')->required()])
                    ->requiresConfirmation()
                    ->action(function (Registration $record, array $data): void {
                        $record->changeLifecycle('withdrawn', auth()->user(), $data['reason']);
                        Notification::make()->title('Pendaftaran dinyatakan mengundurkan diri')->warning()->send();
                    }),
                Tables\Actions\Action::make('card')
                    ->label('Cetak Kartu')->icon('heroicon-o-printer')->color('gray')
                    ->visible(fn (Registration $record): bool => filled($record->applicant_card_number))
                    ->url(fn (Registration $record): string => route('registration.card', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Belum ada calon siswa')
            ->emptyStateDescription('Pilih pembukaan pendaftaran yang tersedia untuk mendaftarkan calon siswa.')
            ->emptyStateIcon('heroicon-o-user-plus')
            ->emptyStateActions([
                Tables\Actions\Action::make('chooseOpening')->label('Pilih Pendaftaran')->icon('heroicon-o-calendar-days')->url(RegistrationOpenings::getUrl()),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['unit', 'opening', 'latestPayment']);
    }

    public static function canViewAny(): bool { return auth()->user()?->isUser() ?? false; }

    public static function canCreate(): bool
    {
        return (auth()->user()?->isUser() ?? false)
            && (auth()->user()?->hasVerifiedEmail() ?? false)
            && RegistrationOpening::query()->where('status', 'open')->exists();
    }

    public static function canEdit($record): bool
    {
        return $record->user_id === auth()->id()
            && (auth()->user()?->hasVerifiedEmail() ?? false)
            && $record->isOperational()
            && $record->current_stage === 'data_validation'
            && in_array($record->data_validation_status, ['pending', 'revision'], true);
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'create' => Pages\CreateRegistration::route('/create'),
            'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }
}
