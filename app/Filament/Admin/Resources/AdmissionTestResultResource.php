<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdmissionTestResultResource\Pages;
use App\Models\AdmissionTestResult;
use App\Models\TestSession;
use App\Services\TestBookingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AdmissionTestResultResource extends Resource
{
    protected static ?string $model = AdmissionTestResult::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Hasil Tes';

    protected static ?string $modelLabel = 'Hasil Tes';

    protected static ?string $pluralModelLabel = 'Hasil Tes';

    protected static ?string $navigationGroup = 'Pendaftaran';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([Forms\Components\Select::make('registration_id')->relationship('registration', 'registration_number')->label('Pendaftaran')->searchable()->preload()->required(), Forms\Components\Select::make('admission_test_id')->relationship('admissionTest', 'name')->label('Tes')->preload()->required(), Forms\Components\Select::make('status')->options(['unbooked' => 'Belum Terjadwal', 'scheduled' => 'Terjadwal', 'completed' => 'Selesai', 'absent' => 'Tidak Hadir', 'exempted' => 'Dibebaskan'])->required(), Forms\Components\TextInput::make('score')->numeric()->label('Nilai'), Forms\Components\Select::make('result')->options(['pending' => 'Belum Dinilai', 'pass' => 'Lulus', 'fail' => 'Tidak Lulus'])->required(), Forms\Components\Textarea::make('notes')->label('Catatan')]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi'),
                Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable(),
                Tables\Columns\TextColumn::make('admissionTest.name')->label('Tes'),
                Tables\Columns\TextColumn::make('score')->label('Nilai'),
                Tables\Columns\TextColumn::make('result')
                    ->label('Hasil')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pass' => 'Lulus',
                        'fail' => 'Tidak Lulus',
                        default => 'Belum Dinilai',
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unbooked' => 'Belum Terjadwal',
                        'completed' => 'Selesai',
                        'absent' => 'Tidak Hadir',
                        'exempted' => 'Dibebaskan',
                        default => 'Terjadwal',
                    })
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('assignSession')
                    ->label(fn (AdmissionTestResult $record): string => $record->status === 'scheduled' ? 'Pindahkan Sesi' : 'Jadwalkan')
                    ->icon('heroicon-o-calendar-days')
                    ->color(fn (AdmissionTestResult $record): string => $record->status === 'scheduled' ? 'warning' : 'primary')
                    ->visible(fn (AdmissionTestResult $record): bool => $record->registration?->current_stage === 'tests'
                        && $record->registration?->isOperational()
                        && in_array($record->status, ['unbooked', 'scheduled'], true)
                        && $record->result === 'pending'
                        && blank($record->assessed_at))
                    ->form([
                        Forms\Components\Select::make('test_session_id')
                            ->label('Sesi Tes')
                            ->options(fn (AdmissionTestResult $record): array => static::sessionOptions($record))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Petugas boleh menetapkan sesi setelah batas pemilihan peserta berakhir, selama sesi masih aktif, belum dimulai, dan kuota tersedia.'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan Penjadwalan Manual')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->modalHeading(fn (AdmissionTestResult $record): string => $record->status === 'scheduled' ? 'Pindahkan Sesi Tes' : 'Tetapkan Sesi Tes')
                    ->modalSubmitActionLabel('Simpan Jadwal')
                    ->action(function (AdmissionTestResult $record, array $data): void {
                        $session = TestSession::query()->findOrFail($data['test_session_id']);

                        try {
                            app(TestBookingService::class)->assignByStaff(
                                $record,
                                $session,
                                auth()->user(),
                                (string) $data['reason'],
                            );
                        } catch (ValidationException $exception) {
                            throw $exception;
                        }

                        Notification::make()
                            ->title($record->status === 'scheduled' ? 'Sesi tes berhasil diperbarui' : 'Sesi tes berhasil ditetapkan')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    private static function sessionOptions(AdmissionTestResult $record): array
    {
        $currentSessionId = $record->registration?->testBookings()
            ->where('admission_test_id', $record->admission_test_id)
            ->value('test_session_id');

        return TestSession::query()
            ->where('admission_test_id', $record->admission_test_id)
            ->where('status', 'active')
            ->where('starts_at', '>', now())
            ->withCount('bookings')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (TestSession $session): bool => $session->id === $currentSessionId || $session->bookings_count < $session->capacity)
            ->mapWithKeys(function (TestSession $session) use ($currentSessionId): array {
                $capacity = $session->bookings_count.'/'.$session->capacity.' peserta';
                $deadline = $session->booking_closes_at?->isPast() ? ' · deadline peserta lewat' : '';
                $current = $session->id === $currentSessionId ? ' · sesi saat ini' : '';

                return [
                    $session->id => $session->label().' · '.$capacity.$deadline.$current,
                ];
            })
            ->all();
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->where('status', 'scheduled')
            ->where('result', 'pending')
            ->whereHas('registration', fn (Builder $query) => $query
                ->where('lifecycle_status', 'active')
                ->where('current_stage', 'tests'))
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('test_bookings')
                    ->whereColumn('test_bookings.registration_id', 'admission_test_results.registration_id')
                    ->whereColumn('test_bookings.admission_test_id', 'admission_test_results.admission_test_id')
                    ->whereNotNull('test_bookings.test_session_id');
            })
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAdmissionTestResults::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()
            ->with(['registration.unit', 'admissionTest'])
            ->whereHas('registration', fn (Builder $registration): Builder => $registration
                ->whereIn('current_stage', [
                    'tests',
                    'selection',
                    'announcement',
                    'waiting_list',
                    'admission_offer',
                    're_registration',
                    'enrollment',
                    'completed',
                ]));

        if (auth()->user()?->isTU()) {
            $q->whereHas('registration', fn (Builder $x) => $x->where('unit_id', auth()->user()->unit_id));
        }

        return $q;
    }
}
