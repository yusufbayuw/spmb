<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RegistrationResource\Pages;
use App\Filament\Forms\ParentInfoFields;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Services\RegistrationWorkflowService;
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

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Pendaftaran';

    protected static ?string $modelLabel = 'Pendaftaran';

    protected static ?string $pluralModelLabel = 'Pendaftaran';

    protected static ?string $navigationGroup = 'SPMB';

    protected static ?int $navigationSort = 1;

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Terkirim',
            'verified' => 'Terverifikasi',
            'payment_pending' => 'Menunggu Pembayaran',
            'payment_uploaded' => 'Bukti Bayar Terupload',
            'payment_verified' => 'Pembayaran Terverifikasi',
            'accepted' => 'Diterima',
            'rejected' => 'Ditolak',
            'waiting_list' => 'Daftar Tunggu',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kepemilikan Pendaftaran')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Akun Pendaftar')
                        ->relationship('user', 'name')
                        ->searchable(['name', 'email'])
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('registration_opening_id')
                        ->label('Pembukaan Pendaftaran')
                        ->relationship(
                            'opening',
                            'academic_year',
                            fn (Builder $query): Builder => auth()->user()?->isTU() && auth()->user()?->unit_id
                                ? $query->where('unit_id', auth()->user()->unit_id)
                                : $query,
                        )
                        ->getOptionLabelFromRecordUsing(fn (RegistrationOpening $record): string => $record->loadMissing('unit')->label())
                        ->searchable(['academic_year', 'wave'])
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            $opening = RegistrationOpening::query()->find($state);
                            $set('registration_pathway_id', null);
                            if ($opening) {
                                $set('unit_id', $opening->unit_id);
                            }
                        })
                        ->disabled(fn (?Registration $record): bool => filled($record?->registration_opening_id))
                        ->dehydrated()
                        ->required(),
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit Sekolah')
                        ->relationship(
                            'unit',
                            'name',
                            fn (Builder $query): Builder => auth()->user()?->isTU() && auth()->user()?->unit_id
                                ? $query->whereKey(auth()->user()->unit_id)
                                : $query,
                        )
                        ->default(fn () => auth()->user()?->isTU() ? auth()->user()?->unit_id : null)
                        ->disabled(fn (Forms\Get $get): bool => filled($get('registration_opening_id')) || (auth()->user()?->isTU() ?? false))
                        ->dehydrated()->searchable()->preload()->required(),
                    Forms\Components\Select::make('registration_pathway_id')
                        ->label('Jalur Pendaftaran')
                        ->options(fn (Forms\Get $get, ?Registration $record): array => static::pathwayOptions(
                            $get('registration_opening_id'),
                            $get('unit_id'),
                            $record,
                        ))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('registrant_type')
                        ->label('Yang Mendaftarkan')
                        ->options(['parent' => 'Orang Tua / Wali', 'self' => 'Calon Siswa Sendiri'])
                        ->required()->live(),
                    Forms\Components\Select::make('registrant_relationship')
                        ->label('Hubungan dengan Calon Siswa')
                        ->options(['father' => 'Ayah', 'mother' => 'Ibu', 'guardian' => 'Wali', 'self' => 'Diri Sendiri', 'other' => 'Lainnya'])
                        ->visible(fn (Forms\Get $get): bool => $get('registrant_type') === 'parent'),
                    Forms\Components\TextInput::make('registration_number')->label('No. Registrasi')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('current_stage')
                        ->label('Tahap Saat Ini')
                        ->formatStateUsing(fn ($state) => Registration::STAGES[$state] ?? $state)
                        ->disabled()->dehydrated(false),
                ]),

            Forms\Components\Section::make('Identitas Calon Siswa')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('nik')
                        ->label('NIK')->required()->length(16)
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Forms\Get $get): Unique => $rule
                                ->where('registration_opening_id', $get('registration_opening_id')),
                        ),
                    Forms\Components\TextInput::make('full_name')->label('Nama Lengkap')->required()->maxLength(150)->columnSpan(2),
                    Forms\Components\TextInput::make('nickname')->label('Nama Panggilan')->maxLength(50),
                    Forms\Components\Select::make('gender')->label('Jenis Kelamin')->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])->required(),
                    Forms\Components\Select::make('religion')->label('Agama')->options(['Islam' => 'Islam', 'Kristen' => 'Kristen', 'Katolik' => 'Katolik', 'Hindu' => 'Hindu', 'Buddha' => 'Buddha', 'Konghucu' => 'Konghucu'])->default('Islam'),
                    Forms\Components\TextInput::make('birth_place')->label('Tempat Lahir')->required(),
                    Forms\Components\DatePicker::make('birth_date')->label('Tanggal Lahir')->required()->native(false)->maxDate(now()),
                    Forms\Components\TextInput::make('phone')->label('Telepon')->tel(),
                    Forms\Components\TextInput::make('email')->label('Email')->email(),
                    Forms\Components\Textarea::make('home_address')->label('Alamat Rumah')->required()->columnSpanFull(),
                    Forms\Components\TextInput::make('previous_school')->label('Sekolah Asal')->columnSpan(2),
                    Forms\Components\TextInput::make('graduation_year')->label('Tahun Lulus')->numeric(),
                ]),

            Forms\Components\Section::make('Data Orang Tua')
                ->description('Data ayah dan ibu dipisahkan agar mudah diperiksa oleh TU.')
                ->relationship('parentInfo')
                ->schema(ParentInfoFields::schema()),

            Forms\Components\Section::make('Validasi')
                ->description('TU dapat memvalidasi atau meminta revisi langsung dari halaman Edit Pendaftaran.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('data_validation_status')
                        ->label('Status Validasi')
                        ->options(['pending' => 'Menunggu Validasi', 'valid' => 'Valid', 'revision' => 'Perlu Revisi'])
                        ->required()
                        ->disabled(fn (?Registration $record): bool => ! ($record && $record->isOperational() && $record->current_stage === 'data_validation' && (auth()->user()?->can('validate_data_registration') ?? false))),
                    Forms\Components\DateTimePicker::make('data_validated_at')->label('Divalidasi pada')->disabled()->dehydrated(false),
                    Forms\Components\Textarea::make('data_validation_notes')
                        ->label('Catatan Validasi')
                        ->helperText('Wajib diisi bila meminta revisi.')
                        ->required(fn (Forms\Get $get): bool => $get('data_validation_status') === 'revision')
                        ->disabled(fn (?Registration $record): bool => ! ($record && $record->isOperational() && $record->current_stage === 'data_validation' && (auth()->user()?->can('validate_data_registration') ?? false)))
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
                Tables\Columns\TextColumn::make('registration_number')->label('No. Registrasi')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('full_name')->label('Calon Siswa')->searchable(['full_name', 'nik'])->description(fn (Registration $record) => $record->nik),
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('opening.academic_year')->label('Tahun Ajaran')->placeholder('-'),
                Tables\Columns\TextColumn::make('opening.wave')->label('Gelombang')->placeholder('-'),
                Tables\Columns\TextColumn::make('pathway.name')->label('Jalur')->badge()->placeholder('-'),
                Tables\Columns\TextColumn::make('opening.registration_fee')->label('Biaya')->money('IDR', locale: 'id')->placeholder('-'),
                Tables\Columns\TextColumn::make('current_stage')->label('Tahap')->badge()->formatStateUsing(fn ($state) => Registration::STAGES[$state] ?? $state),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label('Lifecycle')->badge()
                    ->formatStateUsing(fn ($state) => Registration::LIFECYCLE_STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'withdrawn' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Tanggal Daftar')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit_id')->label('Unit')->relationship('unit', 'name'),
                Tables\Filters\SelectFilter::make('registration_opening_id')
                    ->label('Pembukaan')
                    ->options(fn (): array => RegistrationOpening::query()
                        ->with('unit')
                        ->when(auth()->user()?->isTU() && auth()->user()?->unit_id, fn (Builder $query): Builder => $query->where('unit_id', auth()->user()->unit_id))
                        ->latest()->get()
                        ->mapWithKeys(fn (RegistrationOpening $opening): array => [$opening->id => $opening->label()])
                        ->all()),
                Tables\Filters\SelectFilter::make('current_stage')->label('Tahap')->options(Registration::STAGES),
                Tables\Filters\SelectFilter::make('lifecycle_status')->label('Lifecycle')->options(Registration::LIFECYCLE_STATUSES),
                Tables\Filters\SelectFilter::make('registrant_type')->label('Pendaftar')->options(['parent' => 'Orang Tua/Wali', 'self' => 'Anak Langsung']),
            ])
            ->actions([
                Tables\Actions\Action::make('validateData')
                    ->label('Validasi Data')->icon('heroicon-o-check-badge')->color('info')
                    ->visible(fn (Registration $record) => $record->isOperational() && auth()->user()?->can('validate_data_registration') && $record->current_stage === 'data_validation')
                    ->form([
                        Forms\Components\Toggle::make('approved')->label('Data Valid')->default(true),
                        Forms\Components\Textarea::make('notes')->label('Catatan'),
                    ])
                    ->action(function (Registration $record, array $data) {
                        app(RegistrationWorkflowService::class)->validateData($record, auth()->user(), (bool) $data['approved'], $data['notes'] ?? null);
                        $record->refresh();
                        if (! (bool) $data['approved']) {
                            Notification::make()->title('Data dikembalikan untuk revisi')->warning()->send();
                        } elseif ($record->current_stage === 'payment') {
                            Notification::make()->title('Data valid & VA masuk antrean email')->success()->send();
                        } else {
                            Notification::make()->title('Data valid, tetapi pool VA unit kosong')->body('Upload pool VA agar sistem dapat melakukan assignment otomatis.')->warning()->send();
                        }
                    }),
                Tables\Actions\Action::make('assignVa')
                    ->label('Assign VA dari Pool')->icon('heroicon-o-credit-card')->color('warning')->requiresConfirmation()
                    ->visible(fn (Registration $record) => $record->isOperational() && auth()->user()?->can('send_va_registration') && $record->current_stage === 'virtual_account')
                    ->action(function (Registration $record) {
                        $payment = app(RegistrationWorkflowService::class)->assignAvailableVirtualAccount($record, auth()->user());
                        $payment
                            ? Notification::make()->title('VA berhasil di-assign; email masuk queue')->success()->send()
                            : Notification::make()->title('Pool VA unit kosong')->body('Upload nomor VA terlebih dahulu pada menu Pool Virtual Account.')->warning()->send();
                    }),
                Tables\Actions\Action::make('issueCard')
                    ->label('Terbitkan Kartu')->icon('heroicon-o-identification')->color('success')->requiresConfirmation()
                    ->visible(fn (Registration $record) => $record->isOperational() && auth()->user()?->can('issue_card_registration') && $record->current_stage === 'applicant_card')
                    ->action(function (Registration $record) {
                        app(RegistrationWorkflowService::class)->issueApplicantCard($record, auth()->user());
                        Notification::make()->title('Kartu pendaftar diterbitkan')->success()->send();
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Batalkan')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Registration $record): bool => $record->isOperational() && $record->current_stage !== 'completed' && (auth()->user()?->can('update_registration') ?? false))
                    ->form([Forms\Components\Textarea::make('reason')->label('Alasan pembatalan')->required()])
                    ->requiresConfirmation()
                    ->action(function (Registration $record, array $data): void {
                        $record->changeLifecycle('cancelled', auth()->user(), $data['reason']);
                        Notification::make()->title('Pendaftaran dibatalkan tanpa menghapus data')->warning()->send();
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Arsipkan')->icon('heroicon-o-archive-box')->color('gray')->requiresConfirmation()
                    ->visible(fn (Registration $record): bool => $record->lifecycle_status !== 'archived' && ($record->current_stage === 'completed' || ! $record->isOperational()) && (auth()->user()?->can('update_registration') ?? false))
                    ->action(function (Registration $record): void {
                        $record->changeLifecycle('archived', auth()->user(), $record->lifecycle_reason ?: 'Diarsipkan setelah proses selesai.');
                        Notification::make()->title('Pendaftaran diarsipkan')->success()->send();
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label('Aktifkan Kembali')->icon('heroicon-o-arrow-path')->color('success')->requiresConfirmation()
                    ->visible(fn (Registration $record): bool => in_array($record->lifecycle_status, ['withdrawn', 'cancelled'], true) && (auth()->user()?->can('update_registration') ?? false))
                    ->action(function (Registration $record): void {
                        $record->changeLifecycle('active', auth()->user());
                        Notification::make()->title('Pendaftaran diaktifkan kembali')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'create' => Pages\CreateRegistration::route('/create'),
            'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['unit', 'user', 'parentInfo', 'opening.unit', 'pathway']);
        if (auth()->user()?->isTU() && auth()->user()->unit_id) {
            $query->where('unit_id', auth()->user()->unit_id);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->where('lifecycle_status', 'active')
            ->whereNotIn('current_stage', ['completed'])
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['registration_number', 'full_name', 'nik', 'email', 'phone'];
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function pathwayOptions($openingId, $unitId, ?Registration $record = null): array
    {
        $selectedUnitId = RegistrationOpening::query()->whereKey($openingId)->value('unit_id') ?: $unitId;

        if (! $selectedUnitId) {
            return [];
        }

        return RegistrationPathway::query()
            ->where('unit_id', $selectedUnitId)
            ->where(function (Builder $query) use ($record): void {
                $query->where(function (Builder $available): void {
                    $available->where('is_active', true)->whereNull('archived_at');
                });

                if ($record?->registration_pathway_id) {
                    $query->orWhereKey($record->registration_pathway_id);
                }
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
