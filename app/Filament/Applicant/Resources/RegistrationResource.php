<?php

namespace App\Filament\Applicant\Resources;

use App\Filament\Applicant\Resources\RegistrationResource\Pages;
use App\Models\Document;
use App\Models\Registration;
use App\Services\RegistrationWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                        ->options(['parent' => 'Orang Tua / Wali', 'self' => 'Calon Siswa Sendiri'])
                        ->default('parent')
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('registrant_relationship')
                        ->label('Hubungan dengan calon siswa')
                        ->options(['father' => 'Ayah', 'mother' => 'Ibu', 'guardian' => 'Wali', 'other' => 'Lainnya'])
                        ->required(fn (Forms\Get $get) => $get('registrant_type') === 'parent')
                        ->visible(fn (Forms\Get $get) => $get('registrant_type') === 'parent'),
                ]),

            Forms\Components\Section::make('Identitas Calon Siswa')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('nik')->label('NIK')->required()->rule('digits:16')->unique(ignoreRecord: true),
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
                ->relationship('parentInfo')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('father_name')->label('Nama Ayah')->required()->maxLength(150),
                    Forms\Components\TextInput::make('father_nik')->label('NIK Ayah')->rule('digits:16'),
                    Forms\Components\TextInput::make('father_occupation')->label('Pekerjaan Ayah')->maxLength(100),
                    Forms\Components\TextInput::make('father_phone')->label('Telepon Ayah')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('father_income')->label('Penghasilan Ayah')->numeric()->prefix('Rp'),
                    Forms\Components\TextInput::make('mother_name')->label('Nama Ibu')->required()->maxLength(150),
                    Forms\Components\TextInput::make('mother_nik')->label('NIK Ibu')->rule('digits:16'),
                    Forms\Components\TextInput::make('mother_occupation')->label('Pekerjaan Ibu')->maxLength(100),
                    Forms\Components\TextInput::make('mother_phone')->label('Telepon Ibu')->tel()->maxLength(20),
                    Forms\Components\TextInput::make('mother_income')->label('Penghasilan Ibu')->numeric()->prefix('Rp'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')->label('Calon Siswa')->searchable()->description(fn (Registration $record) => $record->registration_number),
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('current_stage')->label('Tahap Saat Ini')->badge()->formatStateUsing(fn ($state) => Registration::STAGES[$state] ?? $state),
                Tables\Columns\TextColumn::make('data_validation_status')->label('Validasi')->badge()->formatStateUsing(fn ($state) => match ($state) { 'approved' => 'Valid', 'revision' => 'Perlu Revisi', default => 'Menunggu' }),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->date('d M Y')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('progress')
                    ->label('Lihat Progres')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalHeading(fn (Registration $record) => 'Progres '.$record->full_name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('2xl')
                    ->modalContent(fn (Registration $record) => view('filament.applicant.registration-progress', [
                        'record' => $record->loadMissing(['latestPayment', 'selection', 'announcement']),
                    ])),

                Tables\Actions\EditAction::make()
                    ->label('Perbaiki Data')
                    ->visible(fn (Registration $record) => static::canEdit($record)),

                Tables\Actions\Action::make('payment')
                    ->label('Upload Pembayaran')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn (Registration $record) => $record->current_stage === 'payment' && $record->latestPayment)
                    ->form([
                        Forms\Components\FileUpload::make('proof_path')
                            ->label('Bukti Pembayaran')
                            ->disk('public')
                            ->directory(fn (Registration $record) => 'payments/'.$record->id)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120)
                            ->required(),
                        Forms\Components\TextInput::make('payment_method')->label('Metode Pembayaran')->maxLength(50),
                    ])
                    ->action(function (Registration $record, array $data): void {
                        $payment = $record->latestPayment;
                        $payment->update([
                            'proof_path' => $data['proof_path'],
                            'proof_original_name' => basename($data['proof_path']),
                            'payment_method' => $data['payment_method'] ?? null,
                        ]);
                        app(RegistrationWorkflowService::class)->markPaymentUploaded($payment);
                        Notification::make()->title('Bukti pembayaran berhasil dikirim')->success()->send();
                    }),

                Tables\Actions\Action::make('documents')
                    ->label('Lengkapi Dokumen')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->visible(fn (Registration $record) => in_array($record->current_stage, ['documents', 'document_verification'], true))
                    ->form([
                        Forms\Components\FileUpload::make('report_card')->label('Rapor')->disk('public')->directory(fn (Registration $record) => 'documents/'.$record->id)->acceptedFileTypes(['application/pdf','image/jpeg','image/png'])->maxSize(5120),
                        Forms\Components\FileUpload::make('family_card')->label('Kartu Keluarga')->disk('public')->directory(fn (Registration $record) => 'documents/'.$record->id)->acceptedFileTypes(['application/pdf','image/jpeg','image/png'])->maxSize(5120),
                        Forms\Components\FileUpload::make('birth_certificate')->label('Akta Kelahiran')->disk('public')->directory(fn (Registration $record) => 'documents/'.$record->id)->acceptedFileTypes(['application/pdf','image/jpeg','image/png'])->maxSize(5120),
                        Forms\Components\FileUpload::make('photo')->label('Pas Foto')->disk('public')->directory(fn (Registration $record) => 'documents/'.$record->id)->image()->maxSize(5120),
                        Forms\Components\FileUpload::make('supporting_document')->label('Dokumen Pendukung')->disk('public')->directory(fn (Registration $record) => 'documents/'.$record->id)->acceptedFileTypes(['application/pdf','image/jpeg','image/png'])->maxSize(5120),
                    ])
                    ->action(function (Registration $record, array $data): void {
                        foreach (['report_card','family_card','birth_certificate','photo','supporting_document'] as $type) {
                            if (empty($data[$type])) {
                                continue;
                            }

                            $path = $data[$type];
                            Document::updateOrCreate(
                                ['registration_id' => $record->id, 'type' => $type],
                                [
                                    'file_path' => $path,
                                    'original_name' => basename($path),
                                    'file_type' => pathinfo($path, PATHINFO_EXTENSION),
                                    'is_verified' => false,
                                    'verified_at' => null,
                                    'verified_by' => null,
                                ],
                            );
                        }

                        $required = collect(RegistrationWorkflowService::REQUIRED_DOCUMENTS);
                        $uploaded = $record->documents()->whereIn('type', $required)->pluck('type')->unique();
                        $complete = $required->every(fn (string $type) => $uploaded->contains($type));

                        $record->update([
                            'current_stage' => $complete ? 'document_verification' : 'documents',
                            'documents_completed_at' => $complete ? now() : null,
                        ]);

                        Notification::make()
                            ->title($complete ? 'Dokumen lengkap dan menunggu verifikasi' : 'Dokumen berhasil disimpan')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('card')
                    ->label('Kartu Pendaftar')
                    ->icon('heroicon-o-identification')
                    ->color('gray')
                    ->visible(fn (Registration $record) => filled($record->applicant_card_number))
                    ->url(fn (Registration $record) => route('registration.card', $record))
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
