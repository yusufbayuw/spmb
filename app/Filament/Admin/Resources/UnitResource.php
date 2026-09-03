<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Unit / Institusi';
    protected static ?string $modelLabel = 'Unit / Institusi';
    protected static ?string $pluralModelLabel = 'Unit / Institusi';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identitas Unit Pendidikan')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Unit / Institusi')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->maxLength(10)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('institution_type')
                        ->label('Jenis Institusi')
                        ->options(Unit::INSTITUTION_TYPES)
                        ->default('school')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Unit / Institusi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Kode')->badge(),
                Tables\Columns\TextColumn::make('institution_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Unit::INSTITUTION_TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('study_programs_count')->counts('studyPrograms')->label('Program Studi'),
                Tables\Columns\TextColumn::make('registrations_count')->counts('registrations')->label('Pendaftar'),
                Tables\Columns\TextColumn::make('admission_tests_count')->counts('admissionTests')->label('Tes'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('institution_type')
                    ->label('Jenis Institusi')
                    ->options(Unit::INSTITUTION_TYPES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                auth()->user()?->isTU(),
                fn (Builder $query): Builder => $query->whereKey(auth()->user()->unit_id),
            );
    }

    public static function canDelete($record): bool { return false; }
}
