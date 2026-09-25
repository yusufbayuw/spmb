<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SelectionBatchResource\Pages;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\SelectionBatch;
use App\Models\UnitConfiguration;
use App\Services\AdmissionDecisionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SelectionBatchResource extends Resource
{
    protected static ?string $model = SelectionBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Batch Seleksi';

    protected static ?string $navigationGroup = 'Pendaftaran';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $eligibleRegistrations = Registration::query()
            ->where('lifecycle_status', 'active')
            ->where('current_stage', 'selection')
            ->where(function (Builder $mode): void {
                $mode->whereDoesntHave('configuration')
                    ->orWhereHas('configuration', fn (Builder $configuration) => $configuration
                        ->whereIn('selection_mode', ['batch', 'flexible']));
            })
            ->when($user->isTU(), fn (Builder $query) => $query->where('unit_id', $user->unit_id));

        if ($eligibleRegistrations->exists()) {
            return true;
        }

        if ($user->isTU()) {
            $mode = UnitConfiguration::query()
                ->where('unit_id', $user->unit_id)
                ->where('status', 'published')
                ->orderByDesc('version')
                ->value('selection_mode');

            return ($mode ?: 'flexible') !== 'manual';
        }

        $latestPublishedConfigurationIds = UnitConfiguration::query()
            ->selectRaw('MAX(id)')
            ->where('status', 'published')
            ->groupBy('unit_id');

        if (! UnitConfiguration::query()->where('status', 'published')->exists()) {
            return true;
        }

        return UnitConfiguration::query()
            ->whereIn('id', $latestPublishedConfigurationIds)
            ->whereIn('selection_mode', ['batch', 'flexible'])
            ->exists();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('registration_opening_id')
                ->label('Pembukaan Pendaftaran')
                ->options(fn (): array => RegistrationOpening::query()->when(auth()->user()?->isTU(), fn (Builder $q) => $q->where('unit_id', auth()->user()->unit_id))->get()->mapWithKeys(fn (RegistrationOpening $opening): array => [$opening->id => $opening->label()])->all())
                ->searchable()
                ->live()
                ->required(),
            Forms\Components\Select::make('registration_pathway_id')
                ->label('Jalur Pendaftaran')
                ->helperText('Satu batch seleksi mewakili satu jalur agar ranking dan daya tampung konsisten.')
                ->options(fn (Forms\Get $get): array => static::pathwayOptions(
                    filled($get('registration_opening_id')) ? (int) $get('registration_opening_id') : null,
                ))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('name')->label('Nama batch')->required()->maxLength(150),
            Forms\Components\TextInput::make('waitlist_limit')->label('Maksimum rekomendasi daftar tunggu')->integer()->minValue(0)->required(),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function pathwayOptions(?int $registrationOpeningId): array
    {
        if (! $registrationOpeningId) {
            return [];
        }

        $unitId = RegistrationOpening::query()
            ->whereKey($registrationOpeningId)
            ->value('unit_id');

        if (! $unitId) {
            return [];
        }

        return RegistrationPathway::query()
            ->availableForUnit((int) $unitId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('name')->label('Batch')->searchable(),
            Tables\Columns\TextColumn::make('opening.academic_year')->label('Tahun'),
            Tables\Columns\TextColumn::make('opening.wave')->label('Gelombang'),
            Tables\Columns\TextColumn::make('pathway.name')->label('Jalur')->placeholder('Jalur legacy'),
            Tables\Columns\TextColumn::make('selections_count')->counts('selections')->label('Kandidat'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('ranked_at')->label('Diranking')->dateTime('d/m/Y H:i', timezone: config('app.timezone'))->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('rank')
                ->label('Buat / Perbarui Ranking')
                ->icon('heroicon-o-bars-arrow-down')
                ->visible(fn (SelectionBatch $record): bool => $record->status !== 'finalized')
                ->requiresConfirmation()
                ->modalDescription('Sistem akan mengurutkan kandidat berdasarkan nilai akhir dan membuat rekomendasi. Review dan finalisasi dilakukan di menu Penetapan Hasil.')
                ->action(function (SelectionBatch $record): void {
                    app(AdmissionDecisionService::class)->rank($record, auth()->user());
                    Notification::make()->title('Ranking dan rekomendasi sistem telah dibuat')->success()->send();
                }),
            Tables\Actions\EditAction::make()->visible(fn (SelectionBatch $record): bool => $record->status !== 'finalized'),
        ])->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['opening.studyProgram', 'pathway'])
            ->when(auth()->user()?->isTU(), fn (Builder $q) => $q->whereHas('opening', fn (Builder $opening) => $opening->where('unit_id', auth()->user()->unit_id)));
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSelectionBatches::route('/'), 'create' => Pages\CreateSelectionBatch::route('/create'), 'edit' => Pages\EditSelectionBatch::route('/{record}/edit')];
    }
}
