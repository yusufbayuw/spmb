<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RegistrationResource\Pages;
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
        return $form
            ->schema([
                Forms\Components\Section::make('Pendaftaran')
                    ->description('Identitas akun pendaftar, unit tujuan, dan nomor registrasi.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Akun Pendaftar')
                            ->relationship('user', 'name', fn (Builder $query) => $query->where('role', 'user'))
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('unit_id')
                            ->label('Unit Sekolah')
                            ->relationship('unit', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()?->isTU() ? auth()->user()->unit_id : null)
                            ->disabled(fn () => auth()->user()?->isTU() ?? false)
                            ->dehydrated()
                            ->required(),
                        Forms\Components\TextInput::make('registration_number')
                            ->label('No. Registrasi')
                            ->placeholder('Dibuat otomatis jika dikosongkan')
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(static::statusOptions())
                            ->default('draft')
                            ->required()
                            ->live(),
                    ]),
                Forms\Components\Section::make('Identitas Calon Siswa')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->length(16)
                            ->numeric()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('full_name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(150)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('nickname')
                            ->label('Nama Panggilan')
                            ->maxLength(50),
                        Forms\Components\Select::make('gender')
                            ->label('Jenis Kelamin')
                            ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                            ->required(),
                        Forms\Components\Select::make('religion')
                            ->label('Agama')
                            ->options([
                                'Islam' => 'Islam',
                                'Kristen' => 'Kristen',
                                'Katolik' => 'Katolik',
                                'Hindu' => 'Hindu',
                                'Buddha' => 'Buddha',
                                'Konghucu' => 'Konghucu',
                            ])
                            ->default('Islam')
                            ->searchable(),
                        Forms\Components\TextInput::make('birth_place')
                            ->label('Tempat Lahir')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Tanggal Lahir')
                            ->required()
                            ->native(false)
                            ->maxDate(now()),
                        Forms\Components\TextInput::make('child_order')
                            ->label('Anak ke-')
                            ->numeric()
                            ->minValue(1),
                        Forms\Components\TextInput::make('siblings_count')
                            ->label('Jumlah Saudara')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),
                Forms\Components\Section::make('Alamat & Kontak')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Textarea::make('home_address')
                            ->label('Alamat Rumah')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('rt')->label('RT')->maxLength(5),
                        Forms\Components\TextInput::make('rw')->label('RW')->maxLength(5),
                        Forms\Components\TextInput::make('village')->label('Kelurahan / Desa')->maxLength(100),
                        Forms\Components\TextInput::make('district')->label('Kecamatan')->maxLength(100),
                        Forms\Components\TextInput::make('city')->label('Kota / Kabupaten')->maxLength(100),
                        Forms\Components\TextInput::make('province')->label('Provinsi')->maxLength(100),
                        Forms\Components\TextInput::make('postal_code')->label('Kode Pos')->maxLength(10),
                        Forms\Components\TextInput::make('phone')->label('No. Telepon')->tel()->maxLength(20),
                        Forms\Components\TextInput::make('email')->label('Email')->email()->maxLength(100)->columnSpan(2),
                    ]),
                Forms\Components\Section::make('Sekolah Asal')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('previous_school')
                            ->label('Nama Sekolah Asal')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('graduation_year')
                            ->label('Tahun Lulus')
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue((int) now()->format('Y') + 2),
                        Forms\Components\Textarea::make('previous_school_address')
                            ->label('Alamat Sekolah Asal')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Workflow & Keputusan')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'rejected')
                            ->required(fn (Forms\Get $get) => $get('status') === 'rejected')
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('submitted_at')->label('Terkirim pada')->seconds(false),
                        Forms\Components\DateTimePicker::make('verified_at')->label('Terverifikasi pada')->seconds(false),
                        Forms\Components\DateTimePicker::make('payment_verified_at')->label('Pembayaran diverifikasi pada')->seconds(false),
                        Forms\Components\DateTimePicker::make('accepted_at')->label('Diterima pada')->seconds(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration_number')
                    ->label('No. Registrasi')
                    ->searchable()
                    ->copyable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nama Calon Siswa')
                    ->searchable(['full_name', 'nik'])
                    ->description(fn (Registration $record) => $record->nik),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label('JK')
                    ->formatStateUsing(fn (string $state) => $state === 'L' ? 'L' : 'P')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'accepted', 'payment_verified' => 'success',
                        'rejected' => 'danger',
                        'verified' => 'info',
                        'waiting_list', 'payment_pending', 'payment_uploaded', 'submitted' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Dokumen')
                    ->counts('documents')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('payments_count')
                    ->label('Pembayaran')
                    ->counts('payments')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Daftar')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->visible(fn () => auth()->user()?->isAdmin() ?? false),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::statusOptions()),
                Tables\Filters\SelectFilter::make('gender')
                    ->label('Jenis Kelamin')
                    ->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
                Tables\Filters\Filter::make('tanggal_daftar')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Registration $record) => in_array($record->status, ['submitted', 'draft'], true))
                    ->action(fn (Registration $record) => $record->update([
                        'status' => 'verified',
                        'verified_at' => now(),
                    ])),
                Tables\Actions\Action::make('accept')
                    ->label('Terima')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Registration $record) => ! in_array($record->status, ['accepted', 'rejected'], true))
                    ->action(fn (Registration $record) => $record->update([
                        'status' => 'accepted',
                        'accepted_at' => now(),
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->isAdmin() ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false),
                ]),
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
        $query = parent::getEloquentQuery()->with(['unit', 'user']);
        $user = auth()->user();

        if ($user?->isTU() && $user->unit_id) {
            $query->where('unit_id', $user->unit_id);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->whereIn('status', ['submitted', 'verified', 'payment_uploaded'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['registration_number', 'full_name', 'nik', 'email', 'phone'];
    }
}
