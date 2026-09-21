<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\StudyProgramResource\Pages;
use App\Models\EducationLevel;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Support\SpmbOperationalMode;
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

    protected static ?string $navigationGroup = 'Konfigurasi SPMB';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return SpmbOperationalMode::allowsHigherEducation()
            && parent::shouldRegisterNavigation();
    }

    public static function canViewAny(): bool
    {
        return SpmbOperationalMode::allowsHigherEducation()
            && parent::canViewAny();
    }

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
                    Forms\Components\Select::make('education_level_id')
                        ->label('Jenjang')
                        ->options(fn (): array => EducationLevel::query()
                            ->forOperationalMode()
                            ->active()
                            ->where('category', 'higher_education')
                            ->ordered()
                            ->get()
                            ->mapWithKeys(fn (EducationLevel $level): array => [
                                $level->id => $level->code.' — '.$level->name,
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Jenjang menjadi sumber urutan dan filter portal publik.'),
                    Forms\Components\TextInput::make('code')
                        ->label('Kode Program Studi')
                        ->required()
                        ->maxLength(30)
                        ->regex('/^[A-Za-z0-9][A-Za-z0-9_-]*$/')
                        ->dehydrateStateUsing(fn ($state): string => mb_strtoupper(trim((string) $state)))
                        ->helperText('Wajib dan unik per unit. Gunakan huruf, angka, tanda hubung (-), atau garis bawah (_). Kode ini dipakai pada template/import VA.'),
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
                        ->label('Urutan Program dalam Jenjang')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Digunakan untuk mengurutkan program studi di dalam jenjang yang sama, bukan urutan antarjenjang.'),
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
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('unit.name')->label('Perguruan Tinggi')->badge(),
                Tables\Columns\TextColumn::make('educationLevel.code')->label('Jenjang')->badge()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Program Studi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Kode')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('faculty')->label('Fakultas')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('max_age')->label('Batas Usia')->formatStateUsing(fn ($state) => $state ? $state.' tahun' : 'Tidak dibatasi'),
                Tables\Columns\TextColumn::make('registration_openings_count')->counts('registrationOpenings')->label('Pembukaan'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('education_level_id')
                    ->label('Jenjang')
                    ->options(fn (): array => EducationLevel::query()
                        ->forOperationalMode()
                        ->active()
                        ->where('category', 'higher_education')
                        ->ordered()
                        ->pluck('code', 'id')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->forOperationalMode()
            ->with(['unit', 'educationLevel'])
            ->when(
                auth()->user()?->isTU() && auth()->user()?->unit_id,
                fn (Builder $query): Builder => $query->where('unit_id', auth()->user()->unit_id),
            );
    }

    public static function canCreate(): bool
    {
        if (! SpmbOperationalMode::allowsHigherEducation()) {
            return false;
        }

        if (auth()->user()?->isAdmin()) {
            return true;
        }

        if (! auth()->user()?->isTU() || ! auth()->user()?->unit_id) {
            return false;
        }

        return Unit::query()
            ->forOperationalMode()
            ->whereKey(auth()->user()->unit_id)
            ->where('institution_type', 'university')
            ->exists();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudyPrograms::route('/'),
            'create' => Pages\CreateStudyProgram::route('/create'),
            'edit' => Pages\EditStudyProgram::route('/{record}/edit'),
        ];
    }
}
