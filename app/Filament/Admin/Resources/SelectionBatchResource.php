<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SelectionBatchResource\Pages;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\SelectionBatch;
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

    protected static ?string $navigationGroup = 'Seleksi';

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
                ->options(fn (Forms\Get $get): array => static::pathwayOptions(
                    filled($get('registration_opening_id')) ? (int) $get('registration_opening_id') : null,
                ))
                ->searchable(),
            Forms\Components\TextInput::make('name')->label('Nama batch')->required()->maxLength(150),
            Forms\Components\TextInput::make('waitlist_limit')->label('Maksimum rekomendasi daftar tunggu')->integer()->minValue(0)->required(),
        ])->columns(2);
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
            Tables\Columns\TextColumn::make('pathway.name')->label('Jalur')->placeholder('Semua jalur'),
            Tables\Columns\TextColumn::make('selections_count')->counts('selections')->label('Kandidat'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('ranked_at')->label('Diranking')->dateTime('d M Y H:i')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('rank')
                ->label('Buat Ranking')
                ->icon('heroicon-o-bars-arrow-down')
                ->visible(fn (SelectionBatch $record): bool => $record->status !== 'finalized')
                ->requiresConfirmation()
                ->action(function (SelectionBatch $record): void {
                    app(AdmissionDecisionService::class)->rank($record, auth()->user());
                    Notification::make()->title('Ranking dan rekomendasi sistem telah dibuat')->success()->send();
                }),
            Tables\Actions\Action::make('finalize')
                ->label('Finalkan Hasil')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (SelectionBatch $record): bool => $record->status === 'ranked' && (bool) auth()->user()?->can('finalize_selectionbatch'))
                ->requiresConfirmation()
                ->modalHeading('Finalkan keputusan batch?')
                ->modalDescription('Tindakan ini menerapkan rekomendasi yang telah direview sebagai keputusan final dan membuat draft pengumuman.')
                ->action(function (SelectionBatch $record): void {
                    app(AdmissionDecisionService::class)->finalize($record, auth()->user());
                    Notification::make()->title('Keputusan batch difinalkan')->success()->send();
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
