<?php

namespace App\Filament\Admin\Pages;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Services\OperationalReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class OperationalReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Laporan Operasional';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Laporan Operasional SPMB';
    protected static string $view = 'filament.admin.pages.operational-report';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill([
            'unit_id' => auth()->user()?->isTU() ? auth()->user()?->unit_id : null,
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'tu']) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filter Laporan')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Select::make('unit_id')
                            ->label('Unit')
                            ->options(fn (): array => Unit::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn () => auth()->user()?->isTU() ? auth()->user()?->unit_id : null)
                            ->disabled(fn (): bool => auth()->user()?->isTU() ?? false)
                            ->dehydrated()
                            ->searchable()
                            ->live(),
                        Forms\Components\Select::make('registration_opening_id')
                            ->label('Pembukaan')
                            ->options(function (Forms\Get $get): array {
                                return RegistrationOpening::query()
                                    ->with('unit')
                                    ->when(auth()->user()?->isTU(), fn (Builder $q) => $q->where('unit_id', auth()->user()->unit_id))
                                    ->when(! auth()->user()?->isTU() && filled($get('unit_id')), fn (Builder $q) => $q->where('unit_id', $get('unit_id')))
                                    ->latest()
                                    ->get()
                                    ->mapWithKeys(fn (RegistrationOpening $opening): array => [$opening->id => $opening->label()])
                                    ->all();
                            })
                            ->searchable(),
                        Forms\Components\Select::make('current_stage')
                            ->label('Tahap')
                            ->options(Registration::STAGES),
                        Forms\Components\Select::make('lifecycle_status')
                            ->label('Lifecycle')
                            ->options(Registration::LIFECYCLE_STATUSES),
                        Forms\Components\Select::make('payment_status')
                            ->label('Pembayaran')
                            ->options(['pending'=>'Menunggu','paid'=>'Bukti Diunggah','verified'=>'Terverifikasi','rejected'=>'Ditolak']),
                        Forms\Components\Select::make('decision')
                            ->label('Seleksi')
                            ->options(['pending'=>'Pending','accepted'=>'Diterima','rejected'=>'Ditolak','waiting_list'=>'Daftar Tunggu']),
                        Forms\Components\DatePicker::make('date_from')->label('Dari Tanggal')->native(false),
                        Forms\Components\DatePicker::make('date_until')->label('Sampai Tanggal')->native(false),
                    ]),
            ])
            ->statePath('filters');
    }

    public function applyFilters(): void
    {
        $this->form->getState();
    }

    public function resetFilters(): void
    {
        $this->form->fill([
            'unit_id' => auth()->user()?->isTU() ? auth()->user()?->unit_id : null,
        ]);
    }

    public function summary(): array
    {
        return app(OperationalReportService::class)->summary(auth()->user(), $this->filters ?? []);
    }

    public function rows()
    {
        return app(OperationalReportService::class)->preview(auth()->user(), $this->filters ?? [], 100);
    }

    public function getExportUrl(): string
    {
        return route('reports.operational.xlsx', array_filter(
            $this->filters ?? [],
            fn ($value): bool => filled($value),
        ));
    }
}
