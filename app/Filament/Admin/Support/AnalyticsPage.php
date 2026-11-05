<?php

namespace App\Filament\Admin\Support;

use App\Filament\Admin\Clusters\AnalyticsCenter;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Services\AnalyticsCenterService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

abstract class AnalyticsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = AnalyticsCenter::class;

    protected static string $view = 'filament.admin.pages.analytics-center';

    public ?array $filters = [];

    abstract public static function analyticsSection(): string;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active
            && $user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])
            && $user->hasOperationalUnitAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $saved = session()->get($this->sessionKey(), []);
        $saved = is_array($saved) ? $saved : [];

        $this->form->fill(array_merge($this->defaultFilters(), $saved));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Filter bersama')
                ->description('Disimpan selama sesi Anda dan berlaku ketika berpindah halaman analitik.')
                ->collapsible()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit / institusi')
                        ->options(fn (): array => Unit::query()->operational()->orderBy('name')
                            ->pluck('name', 'id')->all())
                        ->default(fn () => auth()->user()?->isTU() ? auth()->user()?->unit_id : null)
                        ->disabled(fn (): bool => (bool) auth()->user()?->isTU())
                        ->dehydrated()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set): void {
                            $set('registration_opening_id', null);
                            $set('registration_pathway_id', null);
                            $set('study_program_id', null);
                        }),
                    Forms\Components\Select::make('academic_year')
                        ->label('Tahun ajaran')
                        ->placeholder('Semua tahun ajaran')
                        ->options(fn (Forms\Get $get): array => $this->openings($get('unit_id'))
                            ->orderByDesc('academic_year')->distinct()
                            ->pluck('academic_year', 'academic_year')->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('registration_opening_id', null)),
                    Forms\Components\Select::make('registration_opening_id')
                        ->label('Pembukaan / gelombang')
                        ->placeholder('Semua pembukaan')
                        ->options(fn (Forms\Get $get): array => $this->openings($get('unit_id'))
                            ->when(filled($get('academic_year')), fn (Builder $q): Builder => $q
                                ->where('academic_year', $get('academic_year')))
                            ->latest('id')
                            ->get()
                            ->mapWithKeys(fn (RegistrationOpening $opening): array => [
                                $opening->id => $opening->label(),
                            ])->all())
                        ->searchable(),
                    Forms\Components\Select::make('registration_pathway_id')
                        ->label('Jalur pendaftaran')
                        ->placeholder('Semua jalur')
                        ->options(fn (Forms\Get $get): array => RegistrationPathway::query()
                            ->whereHas('unit', fn (Builder $q): Builder => $q->operational())
                            ->when(auth()->user()?->isTU(), fn (Builder $q): Builder => $q
                                ->where('unit_id', auth()->user()->unit_id))
                            ->when(! auth()->user()?->isTU() && filled($get('unit_id')),
                                fn (Builder $q): Builder => $q->where('unit_id', (int) $get('unit_id')))
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    Forms\Components\Select::make('study_program_id')
                        ->label('Program studi')
                        ->placeholder('Semua program studi')
                        ->options(fn (Forms\Get $get): array => $this->programs($get('unit_id'))
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (Forms\Get $get): bool => $this->programs($get('unit_id'))->exists())
                        ->searchable(),
                    Forms\Components\Select::make('lifecycle')
                        ->label('Lifecycle pendaftar')
                        ->options(['all' => 'Semua (termasuk arsip)'] + Registration::LIFECYCLE_STATUSES)
                        ->default('active'),
                    Forms\Components\DatePicker::make('date_from')
                        ->label('Tanggal daftar mulai')
                        ->native(false),
                    Forms\Components\DatePicker::make('date_until')
                        ->label('Tanggal daftar sampai')
                        ->native(false)
                        ->afterOrEqual('date_from'),
                    Forms\Components\Select::make('dimension')
                        ->label('Dimensi eksplorasi')
                        ->options([
                            'unit' => 'Unit', 'stage' => 'Tahap', 'status' => 'Status pendaftaran',
                            'gender' => 'Jenis kelamin', 'province' => 'Provinsi asal',
                            'school' => 'Sekolah asal', 'pathway' => 'Jalur pendaftaran',
                            'year' => 'Tahun ajaran', 'wave' => 'Gelombang',
                        ])
                        ->default('unit')
                        ->visible(fn (): bool => static::analyticsSection() === 'explorer'),
                ]),
        ])->statePath('filters');
    }

    private function openings(mixed $selectedUnit): Builder
    {
        $viewer = auth()->user();

        return RegistrationOpening::query()->operational()
            ->when($viewer?->isTU(), fn (Builder $q): Builder => $q
                ->where('unit_id', $viewer->unit_id))
            ->when(! $viewer?->isTU() && filled($selectedUnit),
                fn (Builder $q): Builder => $q->where('unit_id', (int) $selectedUnit));
    }

    private function programs(mixed $selectedUnit): Builder
    {
        $viewer = auth()->user();

        return StudyProgram::query()->where('is_active', true)
            ->whereHas('unit', fn (Builder $q): Builder => $q->operational())
            ->when($viewer?->isTU(), fn (Builder $q): Builder => $q
                ->where('unit_id', $viewer->unit_id))
            ->when(! $viewer?->isTU() && filled($selectedUnit),
                fn (Builder $q): Builder => $q->where('unit_id', (int) $selectedUnit));
    }

    public function applyFilters(): void
    {
        $this->filters = $this->form->getState();
        session()->put($this->sessionKey(), $this->filters);
    }

    public function resetFilters(): void
    {
        session()->forget($this->sessionKey());
        $this->form->fill($this->defaultFilters());
    }

    public function data(): array
    {
        abort_unless(static::canAccess(), 403);

        return app(AnalyticsCenterService::class)->report(
            auth()->user(), $this->filters ?? [], static::analyticsSection()
        );
    }

    private function defaultFilters(): array
    {
        return [
            'unit_id' => auth()->user()?->isTU() ? auth()->user()?->unit_id : null,
            'lifecycle' => 'active',
            'dimension' => 'unit',
        ];
    }

    private function sessionKey(): string
    {
        return 'spmb.analytics.filters.'.auth()->id();
    }
}
