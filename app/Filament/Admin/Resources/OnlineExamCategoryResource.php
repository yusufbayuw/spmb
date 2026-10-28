<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OnlineExamCategoryResource\Pages;
use App\Models\OnlineExamCategory;
use App\Models\Unit;
use App\Services\OnlineExamAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnlineExamCategoryResource extends Resource
{
    protected static ?string $model = OnlineExamCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationLabel = 'Kategori Bank Soal';
    protected static ?string $navigationGroup = 'Tes Online';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool { return OnlineExamResource::canViewAny(); }
    public static function canCreate(): bool { return static::canViewAny(); }

    public static function canView(Model $record): bool
    {
        return app(OnlineExamAccess::class)->canManageUnit(auth()->user(), (int) $record->unit_id);
    }

    public static function canEdit(Model $record): bool { return static::canView($record); }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return app(OnlineExamAccess::class)
            ->staffScope(parent::getEloquentQuery(), auth()->user())
            ->whereHas('unit', fn (Builder $q) => $q->operational());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('unit_id')
                ->label('Unit')
                ->options(fn (): array => Unit::query()->operational()
                    ->when(auth()->user()?->isAdminUnit(), fn (Builder $q) => $q->whereKey(auth()->user()->unit_id))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn () => auth()->user()?->isAdminUnit() ? auth()->user()->unit_id : null)
                ->disabled(fn (): bool => auth()->user()?->isAdminUnit() ?? false)
                ->dehydrated()->required(),
            Forms\Components\TextInput::make('code')
                ->label('Kode Kategori')->maxLength(40)->required()
                ->helperText('Kode unik per unit, contoh: MAT, FIS, LOGIKA.'),
            Forms\Components\TextInput::make('name')
                ->label('Nama Kategori')->maxLength(150)->required(),
            Forms\Components\Toggle::make('is_active')
                ->label('Kategori Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('unit.name')->label('Unit'),
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable(),
            Tables\Columns\TextColumn::make('name')->label('Kategori')->searchable(),
            Tables\Columns\TextColumn::make('questions_count')->counts('questions')->label('Soal')->badge(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnlineExamCategories::route('/'),
            'create' => Pages\CreateOnlineExamCategory::route('/create'),
            'edit' => Pages\EditOnlineExamCategory::route('/{record}/edit'),
        ];
    }
}
