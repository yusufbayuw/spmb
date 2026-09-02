<?php

namespace App\Filament\Applicant\Resources;

use App\Filament\Applicant\Pages\RegistrationStatus;
use App\Filament\Applicant\Resources\RegistrationResource\Pages;
use App\Models\Registration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Pendaftaran Saya';
    protected static ?string $modelLabel = 'Pendaftaran';
    protected static ?string $pluralModelLabel = 'Pendaftaran Saya';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tujuan Pendaftaran')
                ->description('Pilih unit sekolah dan siapa yang melakukan pendaftaran.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit Sekolah')
                        ->relationship('unit', 'name', fn (Builder $query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('registrant_type')
                        ->label('Pendaftaran dilakukan oleh')
                        ->options([
                            'parent' => 'Orang Tua / Wali',
                            'self' => 'Calon Siswa Sendiri',
                        ])
                        ->default('parent')
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('registrant_relationship')
                        ->label('Hubungan dengan calon siswa')
                        ->options([
                            'father' => 'Ayah',
                            'mother' => 'Ibu',
                            'guardian' => 'Wali',
                            'other' => 'Lainnya',
                        ])
                        ->required(fn (Forms\Get $get): bool => $get('registrant_type') === 'parent')
                        ->visible(fn (Forms\Get $get): bool => $get('registrant_type') === 'parent'),
                ]),

            Forms\Components\Section::make('Identitas Calon Siswa')
                ->description('Gunakan data yang sama dengan dokumen resmi calon siswa.')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('nik')
                        ->label('NIK')
                        ->required()
                        ->rule('digits:16')
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('full_name')->label('Nama Lengkap')->required()->maxLength(150)->columnSpan(2),
                    Forms\Components\TextInput::make('nickname')->label('Nama Panggilan')->maxLength(50),
                    Forms\Components\Select::make('gender')->label('Jenis Kelamin')->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])->required(),
                    Forms\Components\Select::make('religion')->label('Agama')->options(['Islam'=>'Islam','Kristen'=>'Kristen','Katolik'=>'Katolik','Hindu'=>'Hindu','Buddha'=>'Buddha','Konghucu'=>'Konghucu'])->default('Islam'),
                    Forms\Components\TextInput::make('birth_place')->label('Tempat Lahir')->required()->maxLength(100),
                    Forms\Components\DatePicker::make('birth_date')->label('Tanggal Lahir')->required()->native(false)->maxDate(now()->subDay()),
                    Forms\Components\TextInput::make('phone')->label('Nomor Telepon')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('email')->label('Email Calon Siswa')->email()->maxLength(100),
                    Forms\Components\Textarea::make('home_address')->label('Alamat Rumah')->required()->rows(3)->columnSpanFull(),
                    Forms\Components\TextInput::make('previous_school')->label('Sekolah Asal')->maxLength(150)->columnSpan(2),
                    Forms\Components\TextInput::make('graduation_year')->label('Tahun Lulus')->numeric()->minValue(2000)->maxValue(now()->year + 2),
                ]),

            Forms\Components\Section::make('Data Orang Tua / Wali')
                ->description('Data kontak keluarga untuk kebutuhan administrasi SPMB.')
                ->relationship('parentInfo')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('father_name')->label('Nama Ayah')->required()->maxLength(150),
                    Forms\Components\TextInput::make('father_nik')->label('NIK Ayah')->rule('digits:16'),
                    Forms\Components\TextInput::make('father_occupation')->label('Pekerjaan Ayah')->maxLength(100),
                    Forms\Components\TextInput::make('father_phone')->label('Telepon Ayah')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('father_income')->label('Penghasilan Ayah')->numeric()->minValue(0)->prefix('Rp'),
                    Forms\Components\TextInput::make('mother_name')->label('Nama Ibu')->required()->maxLength(150),
                    Forms\Components\TextInput::make('mother_nik')->label('NIK Ibu')->rule('digits:16'),
                    Forms\Components\TextInput::make('mother_occupation')->label('Pekerjaan Ibu')->maxLength(100),
                    Forms\Components\TextInput::make('mother_phone')->label('Telepon Ibu')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('mother_income')->label('Penghasilan Ibu')->numeric()->minValue(0)->prefix('Rp'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Calon Siswa')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Registration $record): ?string => $record->registration_number),
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('current_stage')
                    ->label('Tahap Saat Ini')
                    ->badge()
                    ->formatStateUsing(fn (Registration $record): string => $record->stageLabel()),
                Tables\Columns\TextColumn::make('data_validation_status')
                    ->label('Validasi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'valid' => 'Valid',
                        'revision' => 'Perlu Revisi',
                        default => 'Menunggu',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'valid' => 'success',
                        'revision' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('progress')
                    ->label('Lihat Progres')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(fn (Registration $record): string => RegistrationStatus::getUrl(['registration' => $record->id])),
                Tables\Actions\EditAction::make()
                    ->label('Perbaiki Data')
                    ->visible(fn (Registration $record): bool => static::canEdit($record)),
                Tables\Actions\Action::make('card')
                    ->label('Cetak Kartu')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->visible(fn (Registration $record): bool => filled($record->applicant_card_number))
                    ->url(fn (Registration $record): string => route('registration.card', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Belum ada calon siswa')
            ->emptyStateDescription('Tambahkan calon siswa pertama untuk memulai proses SPMB.')
            ->emptyStateIcon('heroicon-o-user-plus')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label('Daftarkan Calon Siswa'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['unit', 'latestPayment']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isUser() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isUser() ?? false;
    }

    public static function canEdit($record): bool
    {
        return $record->user_id === auth()->id()
            && $record->current_stage === 'data_validation'
            && in_array($record->data_validation_status, ['pending', 'revision'], true);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'create' => Pages\CreateRegistration::route('/create'),
            'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }
}
