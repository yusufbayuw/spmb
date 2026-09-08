<?php

namespace App\Filament\Admin\Pages;

use App\Models\AdmissionTest;
use App\Models\TestSession;
use App\Services\TestBookingService;
use App\Services\UnitConfigurationService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TestSessions extends Page implements Forms\Contracts\HasForms, Tables\Contracts\HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Sesi Tes';

    protected static ?string $navigationGroup = 'Konfigurasi SPMB';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Sesi dan Kuota Tes';

    protected static string $view = 'filament.admin.pages.test-sessions';

    public string $statusTab = 'all';

    public static function canAccess(): bool
    {
        return UnitRegistrationSettings::canAccess();
    }

    private function sessionFields(): array
    {
        return [
            Forms\Components\Select::make('admission_test_uuid')->label('Jenis Tes')->options(fn (): array => AdmissionTest::query()->when(auth()->user()->isTU(), fn ($q) => $q->where('unit_id', auth()->user()->unit_id))->pluck('name', 'uuid')->all())->required(),
            Forms\Components\DateTimePicker::make('starts_at')->label('Mulai')->timezone(config('app.timezone'))->native(false)->displayFormat('d/m/Y H:i')->seconds(false)->required(),
            Forms\Components\DateTimePicker::make('ends_at')->label('Selesai')->timezone(config('app.timezone'))->native(false)->displayFormat('d/m/Y H:i')->seconds(false)->required(),
            Forms\Components\DateTimePicker::make('booking_closes_at')->label('Batas pemesanan/perpindahan')->timezone(config('app.timezone'))->native(false)->displayFormat('d/m/Y H:i')->seconds(false)->helperText('Kosongkan untuk 24 jam sebelum mulai.'),
            Forms\Components\TextInput::make('location')->label('Lokasi')->required(),
            Forms\Components\TextInput::make('capacity')->label('Kuota')->integer()->minValue(1)->required(),
            Forms\Components\Textarea::make('instructions')->label('Petunjuk peserta'),
            Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Aktif', 'closed' => 'Pemesanan ditutup', 'cancelled' => 'Dibatalkan'])->default('active')->required(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table->query(TestSession::query()
            ->with('admissionTest')
            ->withCount('bookings')
            ->when(auth()->user()->isTU(), fn ($query) => $query->whereHas('admissionTest', fn ($testQuery) => $testQuery->where('unit_id', auth()->user()->unit_id)))
            ->when($this->statusTab !== 'all', fn ($query) => $query->where('status', $this->statusTab))
            ->latest())
            ->columns([
                Tables\Columns\TextColumn::make('admissionTest.name')->label('Tes'),
                Tables\Columns\TextColumn::make('starts_at')->label('Mulai')->dateTime('d/m/Y H:i', timezone: config('app.timezone')),
                Tables\Columns\TextColumn::make('location')->label('Lokasi'),
                Tables\Columns\TextColumn::make('bookings_count')->label('Terisi'),
                Tables\Columns\TextColumn::make('capacity')->label('Kuota'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            ])->headerActions([
                Tables\Actions\Action::make('create')->label('Tambah Sesi')->form($this->sessionFields())->action(fn (array $data) => app(TestBookingService::class)->saveSession(null, $this->sessionData($data), auth()->user())),
            ])->actions([
                Tables\Actions\Action::make('edit')->label('Ubah Sesi')->form($this->sessionFields())->fillForm(fn (TestSession $record): array => $this->sessionFormData($record))->action(fn (TestSession $record, array $data) => app(TestBookingService::class)->saveSession($record, $this->sessionData($data), auth()->user())),
            ]);
    }

    public function selectStatusTab(string $status): void
    {
        abort_unless(in_array($status, ['all', 'active', 'closed', 'cancelled'], true), 404);

        $this->statusTab = $status;
        $this->resetPage();
    }

    private function sessionData(array $data): array
    {
        $test = AdmissionTest::query()->where('uuid', $data['admission_test_uuid'] ?? null)->firstOrFail();
        app(UnitConfigurationService::class)->authorize(auth()->user(), $test->unit_id);
        $data['admission_test_id'] = $test->id;
        unset($data['admission_test_uuid']);

        foreach (['starts_at', 'ends_at', 'booking_closes_at'] as $field) {
            if (filled($data[$field] ?? null)) {
                $data[$field] = Carbon::parse($data[$field])
                    ->timezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    private function sessionFormData(TestSession $record): array
    {
        $timezone = config('app.timezone');

        return [
            'admission_test_uuid' => $record->admissionTest->uuid,
            'starts_at' => $record->starts_at?->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
            'ends_at' => $record->ends_at?->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
            'booking_closes_at' => $record->booking_closes_at?->copy()->timezone($timezone)->format('Y-m-d H:i:s'),
            'location' => $record->location,
            'capacity' => $record->capacity,
            'instructions' => $record->instructions,
            'status' => $record->status,
        ];
    }
}
