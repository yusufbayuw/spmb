<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\StudyProgramResource\Pages;
use App\Models\StudyProgram;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudyProgramResource extends Resource
{
    protected static ?string $model = StudyProgram::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Program Studi';
    protected static ?string $modelLabel = 'Program Studi';
    protected static ?string $pluralModelLabel = 'Program Studi';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identitas Program Studi')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->label('Perguruan Tinggi')
                        ->relationship(
                            'unit',
                            'name',
                            fn (Builder $query): Builder => $query
                                ->where('institution_type', 'university')
                                ->where('is_active', true)
                                ->when(
                                    auth()->user()?->isTU() && auth()->user()?->unit_id,
                                    fn (Builder $unitQuery): Builder => $unitQuery->whereKey(auth()->user()->unit_id),
                                ),
                        )
                        ->default(fn () => auth()->user()?->isTU() ? auth()->user()->unit_id : null)
                        ->disabled(fn (): bool => auth()->user()?->isTU() ?? false)
                        ->dehydrated()
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('degree_level')
                        ->label('Jenjang')
                        ->options(['D3' => 'Diploma 3 (D3)', 'D4' => 'Sarjana Terapan (D4)', 'S1' => 'Sarjana (S1)', 'S2' => 'Magister (S2)', 'S3' => 'Doktor (S3)'])
                        ->required(),
                    Forms\Components\TextInput::make('code')
                        ->label('Kode Program Studi')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Program Studi')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('faculty')
                        ->label('Fakultas')
                        ->maxLength(150)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('max_age')
                        ->label('Usia Maksimum Pendaftar')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(100)
                        ->suffix('tahun')
                        ->helperText('Kosongkan bila program studi tidak memiliki batas usia.'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('unit.name')->label('Perguruan Tinggi')->badge(),
                Tables\Columns\TextColumn::make('degree_level')->label('Jenjang')->badge()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Program Studi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Kode')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('faculty')->label('Fakultas')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('max_age')->label('Batas Usia')->formatStateUsing(fn ($state) => $state ? $state.' tahun' : 'Tidak dibatasi'),
                Tables\Columns\TextColumn::make('registration_openings_count')->counts('registrationOpenings')->label('Pembukaan'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('degree_level')
                    ->label('Jenjang')
                    ->options(['D3' => 'D3', 'D4' => 'D4', 'S1' => 'S1', 'S2' => 'S2', 'S3' => 'S3']),
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('unit')
            ->when(
                auth()->user()?->isTU() && auth()->user()?->unit_id,
                fn (Builder $query): Builder => $query->where('unit_id', auth()->user()->unit_id),
            );
    }

    public static function canCreate(): bool
    {
        if (auth()->user()?->isAdmin()) {
            return true;
        }

        if (! auth()->user()?->isTU() || ! auth()->user()?->unit_id) {
            return false;
        }

        return Unit::query()
            ->whereKey(auth()->user()->unit_id)
            ->where('institution_type', 'university')
            ->exists();
    }

    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudyPrograms::route('/'),
            'create' => Pages\CreateStudyProgram::route('/create'),
            'edit' => Pages\EditStudyProgram::route('/{record}/edit'),
        ];
    }
}
