<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdmissionTestResource\Pages;
use App\Models\AdmissionTest;
use App\Models\StudyProgram;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionTestResource extends Resource
{
    protected static ?string $model = AdmissionTest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Konfigurasi Tes';

    protected static ?string $navigationGroup = 'Konfigurasi SPMB';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('unit_id')
                ->relationship('unit', 'name')
                ->label('Unit / Institusi')
                ->default(fn () => auth()->user()?->isTU() ? auth()->user()->unit_id : null)
                ->disabled(fn () => auth()->user()?->isTU() ?? false)
                ->dehydrated()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('study_program_id', null))
                ->required(),
            Forms\Components\Select::make('study_program_id')
                ->label('Program Studi')
                ->placeholder('Semua program studi pada institusi')
                ->options(fn (Forms\Get $get): array => StudyProgram::query()
                    ->where('unit_id', $get('unit_id'))
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->mapWithKeys(fn (StudyProgram $program): array => [$program->id => $program->label()])
                    ->all())
                ->visible(fn (Forms\Get $get): bool => filled($get('unit_id')) && Unit::query()->whereKey($get('unit_id'))->where('institution_type', 'university')->exists())
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('name')->label('Nama Tes')->maxLength(150)->required(),
            Forms\Components\TextInput::make('code')->label('Kode')->maxLength(50),
            Forms\Components\Textarea::make('description')->label('Deskripsi'),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Select::make('result_type')->options(['score' => 'Nilai', 'pass_fail' => 'Lulus/Tidak'])->default('score')->required(),
            Forms\Components\TextInput::make('passing_score')->numeric()->label('Nilai Minimum'),
            Forms\Components\Placeholder::make('session_management')
                ->label('Jadwal dan Lokasi')
                ->content('Jadwal, lokasi, kuota, dan status pelaksanaan dikelola sebagai Sesi Tes. Gunakan menu Sesi Tes atau tombol Kelola Sesi pada Pengaturan Pendaftaran Unit.'),
            Forms\Components\Toggle::make('is_required')->default(true),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('unit.name')->label('Unit / Institusi')->badge(),
                Tables\Columns\TextColumn::make('studyProgram.name')
                    ->label('Program Studi')
                    ->formatStateUsing(fn ($state, AdmissionTest $record): string => $record->studyProgram?->label() ?? 'Semua program')
                    ->placeholder('Semua program'),
                Tables\Columns\TextColumn::make('name')->label('Tes')->searchable(),
                Tables\Columns\TextColumn::make('sessions_count')->label('Sesi')->badge(),
                Tables\Columns\IconColumn::make('is_required')->boolean(),
                Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmissionTests::route('/'),
            'create' => Pages\CreateAdmissionTest::route('/create'),
            'edit' => Pages\EditAdmissionTest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['unit', 'studyProgram'])
            ->withCount('sessions')
            ->when(auth()->user()?->isTU(), fn (Builder $query): Builder => $query->where('unit_id', auth()->user()->unit_id));
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
