<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OnlineExamAttemptResource\Pages;
use App\Models\OnlineExamAttempt;
use App\Services\OnlineExamAccess;
use App\Services\OnlineExamEngine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnlineExamAttemptResource extends Resource
{
    protected static ?string $model = OnlineExamAttempt::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Hasil Tes Online';
    protected static ?string $navigationGroup = 'Tes Online';
    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool { return OnlineExamResource::canViewAny(); }
    public static function canCreate(): bool { return false; }

    public static function canView(Model $record): bool
    {
        return $record->exam !== null
            && app(OnlineExamAccess::class)->canManage(auth()->user(), $record->exam);
    }

    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('exam', fn (Builder $q) =>
            app(OnlineExamAccess::class)->staffScope($q, auth()->user())
                ->whereHas('unit', fn (Builder $unit) => $unit->operational())
        )->with(['exam.unit', 'user', 'items']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('exam.unit.name')->label('Unit'),
            Tables\Columns\TextColumn::make('exam.title')->label('Tes')->searchable(),
            Tables\Columns\TextColumn::make('user.name')->label('Peserta'),
            Tables\Columns\TextColumn::make('attempt_no')->label('Ke-')->badge(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('percentage')->label('Skor %')
                ->formatStateUsing(fn ($state) => $state === null ? 'Menunggu Koreksi' : number_format((float) $state, 2, ',', '.')),
            Tables\Columns\IconColumn::make('passed')->label('Lulus')->boolean(),
            Tables\Columns\TextColumn::make('submitted_at')->label('Dikumpulkan')->dateTime('d/m/Y H:i'),
        ])->actions([
            Tables\Actions\Action::make('gradeEssay')
                ->label('Nilai Esai')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (OnlineExamAttempt $record): bool => $record->status === 'pending_review' && static::canView($record))
                ->modalHeading('Koreksi Esai')
                ->form(fn (OnlineExamAttempt $record): array => $record->items()
                    ->where('type', 'essay')->whereNull('earned_points')->get()
                    ->flatMap(fn ($item): array => [
                        Forms\Components\Placeholder::make('prompt_'.$item->id)
                            ->label('Pertanyaan')
                            ->content($item->display_snapshot['prompt'] ?? ''),
                        Forms\Components\Placeholder::make('answer_'.$item->id)
                            ->label('Jawaban Peserta')
                            ->content((string) ($item->answer['value'] ?? '')),
                        Forms\Components\TextInput::make('scores.'.$item->id)
                            ->label('Nilai Esai (maks. '.$item->points.')')
                            ->numeric()->minValue(0)->maxValue((float) $item->points)->required(),
                    ])->all())
                ->action(function (OnlineExamAttempt $record, array $data): void {
                    app(OnlineExamEngine::class)->reviewEssay(
                        $record, auth()->user(), $data['scores'] ?? [],
                    );
                    Notification::make()->title('Penilaian esai berhasil disimpan')->success()->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOnlineExamAttempts::route('/'),
            'monitor' => Pages\MonitorOnlineExams::route('/monitor')];
    }
}
