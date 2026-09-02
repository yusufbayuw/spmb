<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RegistrationResource\Pages;
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
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Pendaftaran';
    protected static ?string $modelLabel = 'Pendaftaran';
    protected static ?string $pluralModelLabel = 'Pendaftaran';
    protected static ?string $navigationGroup = 'SPMB';
    protected static ?int $navigationSort = 1;

    public static function statusOptions(): array
    {
        return ['draft'=>'Draft','submitted'=>'Terkirim','verified'=>'Terverifikasi','payment_pending'=>'Menunggu Pembayaran','payment_uploaded'=>'Bukti Bayar Terupload','payment_verified'=>'Pembayaran Terverifikasi','accepted'=>'Diterima','rejected'=>'Ditolak','waiting_list'=>'Daftar Tunggu'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kepemilikan Pendaftaran')->columns(2)->schema([
                Forms\Components\Select::make('user_id')->label('Akun Pendaftar')->relationship('user','name')->searchable(['name','email'])->preload()->required(),
                Forms\Components\Select::make('unit_id')->label('Unit Sekolah')->relationship('unit','name')->searchable()->preload()->required(),
                Forms\Components\Select::make('registrant_type')->label('Yang Mendaftarkan')->options(['parent'=>'Orang Tua / Wali','self'=>'Calon Siswa Sendiri'])->required()->live(),
                Forms\Components\Select::make('registrant_relationship')->label('Hubungan dengan Calon Siswa')->options(['father'=>'Ayah','mother'=>'Ibu','guardian'=>'Wali','self'=>'Diri Sendiri','other'=>'Lainnya'])->visible(fn (Forms\Get $get) => $get('registrant_type') === 'parent'),
                Forms\Components\TextInput::make('registration_number')->label('No. Registrasi')->disabled()->dehydrated(false),
                Forms\Components\TextInput::make('current_stage')->label('Tahap Saat Ini')->formatStateUsing(fn ($state) => Registration::STAGES[$state] ?? $state)->disabled()->dehydrated(false),
            ]),
            Forms\Components\Section::make('Identitas Calon Siswa')->columns(3)->schema([
                Forms\Components\TextInput::make('nik')->label('NIK')->required()->length(16)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('full_name')->label('Nama Lengkap')->required()->maxLength(150)->columnSpan(2),
                Forms\Components\TextInput::make('nickname')->label('Nama Panggilan')->maxLength(50),
                Forms\Components\Select::make('gender')->label('Jenis Kelamin')->options(['L'=>'Laki-laki','P'=>'Perempuan'])->required(),
                Forms\Components\Select::make('religion')->label('Agama')->options(['Islam'=>'Islam','Kristen'=>'Kristen','Katolik'=>'Katolik','Hindu'=>'Hindu','Buddha'=>'Buddha','Konghucu'=>'Konghucu'])->default('Islam'),
                Forms\Components\TextInput::make('birth_place')->label('Tempat Lahir')->required(),
                Forms\Components\DatePicker::make('birth_date')->label('Tanggal Lahir')->required()->native(false)->maxDate(now()),
                Forms\Components\TextInput::make('phone')->label('Telepon')->tel(),
                Forms\Components\TextInput::make('email')->label('Email')->email(),
                Forms\Components\Textarea::make('home_address')->label('Alamat Rumah')->required()->columnSpanFull(),
                Forms\Components\TextInput::make('previous_school')->label('Sekolah Asal')->columnSpan(2),
                Forms\Components\TextInput::make('graduation_year')->label('Tahun Lulus')->numeric(),
            ]),
            Forms\Components\Section::make('Validasi')->columns(2)->schema([
                Forms\Components\TextInput::make('data_validation_status')->label('Status Validasi')->disabled()->dehydrated(false),
                Forms\Components\DateTimePicker::make('data_validated_at')->label('Divalidasi pada')->disabled()->dehydrated(false),
                Forms\Components\Textarea::make('data_validation_notes')->label('Catatan Validasi')->disabled()->dehydrated(false)->columnSpanFull(),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at','desc')->columns([
            Tables\Columns\TextColumn::make('registration_number')->label('No. Registrasi')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('full_name')->label('Calon Siswa')->searchable(['full_name','nik'])->description(fn (Registration $record) => $record->nik),
            Tables\Columns\TextColumn::make('user.name')->label('Akun Pendaftar')->searchable(),
            Tables\Columns\TextColumn::make('registrant_type')->label('Pendaftaran Oleh')->badge()->formatStateUsing(fn ($state) => $state === 'parent' ? 'Orang Tua/Wali' : 'Anak Langsung'),
            Tables\Columns\TextColumn::make('unit.name')->label('Unit')->badge(),
            Tables\Columns\TextColumn::make('current_stage')->label('Tahap')->badge()->formatStateUsing(fn ($state) => Registration::STAGES[$state] ?? $state),
            Tables\Columns\TextColumn::make('created_at')->label('Tanggal Daftar')->dateTime('d M Y H:i')->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('unit_id')->label('Unit')->relationship('unit','name'),
            Tables\Filters\SelectFilter::make('current_stage')->label('Tahap')->options(Registration::STAGES),
            Tables\Filters\SelectFilter::make('registrant_type')->label('Pendaftar')->options(['parent'=>'Orang Tua/Wali','self'=>'Anak Langsung']),
        ])->actions([
            Tables\Actions\Action::make('validateData')->label('Validasi Data')->icon('heroicon-o-check-badge')->color('info')
                ->visible(fn (Registration $record) => auth()->user()?->can('validate_data_registration') && $record->current_stage === 'data_validation')
                ->form([Forms\Components\Toggle::make('approved')->label('Data Valid')->default(true),Forms\Components\Textarea::make('notes')->label('Catatan')])
                ->action(function (Registration $record, array $data) {
                    app(RegistrationWorkflowService::class)->validateData($record, auth()->user(), (bool) $data['approved'], $data['notes'] ?? null);
                    $record->refresh();

                    if (! (bool) $data['approved']) {
                        Notification::make()->title('Data dikembalikan untuk revisi')->warning()->send();
                    } elseif ($record->current_stage === 'payment') {
                        Notification::make()->title('Data valid & VA otomatis dikirim')->success()->send();
                    } else {
                        Notification::make()->title('Data valid, tetapi pool VA unit kosong')->body('Upload pool VA agar sistem dapat melakukan assignment otomatis.')->warning()->send();
                    }
                }),
            Tables\Actions\Action::make('assignVa')->label('Assign VA dari Pool')->icon('heroicon-o-credit-card')->color('warning')->requiresConfirmation()
                ->visible(fn (Registration $record) => auth()->user()?->can('send_va_registration') && $record->current_stage === 'virtual_account')
                ->action(function (Registration $record) {
                    $payment = app(RegistrationWorkflowService::class)->assignAvailableVirtualAccount($record, auth()->user());
                    if ($payment) {
                        Notification::make()->title('VA berhasil di-assign dan dikirim ke email pendaftar')->success()->send();
                    } else {
                        Notification::make()->title('Pool VA unit kosong')->body('Upload nomor VA terlebh dahulu pada menu Pool Virtual Account.')->warning()->send();
                    }
                }),
            Tables\Actions\Action::make('issueCard')->label('Terbitkan Kartu')->icon('heroicon-o-identification')->color('success')->requiresConfirmation()
                ->visible(fn (Registration $record) => auth()->user()?->can('issue_card_registration') && $record->current_stage === 'applicant_card')
                ->action(function (Registration $record) { app(RegistrationWorkflowService::class)->issueApplicantCard($record, auth()->user()); Notification::make()->title('Kartu pendaftar diterbitkan')->success()->send(); }),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array { return ['index'=>Pages\ListRegistrations::route('/'),'create'=>Pages\CreateRegistration::route('/create'),'edit'=>Pages\EditRegistration::route('/{record}/edit')]; }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['unit','user']);
        if (auth()->user()?->isTU() && auth()->user()->unit_id) $query->where('unit_id', auth()->user()->unit_id);
        return $query;
    }

    public static function getNavigationBadge(): ?string { return (string) static::getEloquentQuery()->whereNotIn('current_stage',['completed'])->count(); }
    public static function getNavigationBadgeColor(): ?string { return 'warning'; }
    public static function getGloballySearchableAttributes(): array { return ['registration_number','full_name','nik','email','phone']; }
}
