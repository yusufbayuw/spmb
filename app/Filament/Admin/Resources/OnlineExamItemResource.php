<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OnlineExamItemResource\Pages;
use App\Models\OnlineExam;
use App\Models\OnlineExamItem;
use App\Models\OnlineExamQuestion;
use App\Services\OnlineExamAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnlineExamItemResource extends Resource
{
    protected static ?string $model = OnlineExamItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationLabel = 'Isi Paket Tes';
    protected static ?string $navigationGroup = 'Tes Online';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool { return OnlineExamResource::canViewAny(); }
    public static function canCreate(): bool { return static::canViewAny(); }

    public static function canView(Model $record): bool
    {
        return $record->exam !== null
            && app(OnlineExamAccess::class)->canManage(auth()->user(), $record->exam);
    }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool
    {
        return static::canView($record)
            && ! $record->exam->is_published
            && ! $record->exam->attempts()->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('exam', fn (Builder $q) => app(OnlineExamAccess::class)
                ->staffScope($q, auth()->user())
                ->whereHas('unit', fn (Builder $unit) => $unit->operational()))
            ->with(['exam', 'question', 'exam.unit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('online_exam_id')
                ->label('Paket Tes')
                ->options(fn (): array => OnlineExam::query()
                    ->whereHas('unit', fn (Builder $q) => $q->operational())
                    ->when(auth()->user()?->isAdminUnit(), fn (Builder $q) => $q->where('unit_id', auth()->user()->unit_id))
                    ->where('is_published', false)
                    ->orderBy('title')->pluck('title', 'id')->all())
                ->helperText('Batalkan publikasi paket sebelum menambah soal.')
                ->live()->searchable()->required(),
            Forms\Components\Select::make('online_exam_question_id')
                ->label('Soal dari Bank')
                ->options(function (Forms\Get $get): array {
                    $exam = OnlineExam::query()->find($get('online_exam_id'));
                    if (! $exam || ! app(OnlineExamAccess::class)->canManage(auth()->user(), $exam)) {
                        return [];
                    }

                    return OnlineExamQuestion::query()
                        ->where('unit_id', $exam->unit_id)->where('is_active', true)
                        ->orderBy('id')->get()
                        ->mapWithKeys(fn (OnlineExamQuestion $q): array => [
                            $q->id => '['.($q->type).'] '.str($q->prompt)->limit(85),
                        ])->all();
                })->searchable()->preload()->required(),
            Forms\Components\TextInput::make('sort_order')
                ->label('Urutan')->integer()->minValue(0)->default(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('online_exam_id')->columns([
            Tables\Columns\TextColumn::make('exam.unit.name')->label('Unit'),
            Tables\Columns\TextColumn::make('exam.title')->label('Paket Tes')->searchable(),
            Tables\Columns\TextColumn::make('question.prompt')->label('Pertanyaan')->limit(90),
            Tables\Columns\TextColumn::make('sort_order')->label('Urutan'),
        ])->actions([Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnlineExamItems::route('/'),
            'create' => Pages\CreateOnlineExamItem::route('/create'),
        ];
    }
}
