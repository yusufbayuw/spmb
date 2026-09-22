<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FaqResource\Pages;
use App\Models\Faq;
use App\Models\RegistrationPathway;
use App\Models\StudyProgram;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationLabel = 'FAQ';
    protected static ?string $modelLabel = 'FAQ';
    protected static ?string $pluralModelLabel = 'FAQ';
    protected static ?string $navigationGroup = 'Informasi Publik';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->is_active
            && (auth()->user()->isAdmin() || auth()->user()->isAdminUnit());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canViewAny() || ! $record instanceof Faq) {
            return false;
        }

        return auth()->user()->isAdmin()
            || (int) auth()->user()->unit_id === (int) $record->unit_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Konteks FAQ')
                ->description('FAQ dapat berlaku untuk seluruh unit, khusus program studi, khusus jalur, atau kombinasi program studi dan jalur.')
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit / Institusi')
                        ->options(fn (): array => Unit::query()
                            ->forOperationalMode()
                            ->when(
                                auth()->user()?->isAdminUnit(),
                                fn (Builder $query): Builder => $query->whereKey(auth()->user()->unit_id),
                            )
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn () => auth()->user()?->isAdminUnit() ? auth()->user()->unit_id : null)
                        ->disabled(fn (): bool => auth()->user()?->isAdminUnit() ?? false)
                        ->dehydrated()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set): void {
                            $set('study_program_id', null);
                            $set('registration_pathway_id', null);
                        })
                        ->required(),
                    Forms\Components\Select::make('study_program_id')
                        ->label('Program Studi')
                        ->helperText('Kosongkan jika FAQ berlaku untuk seluruh unit.')
                        ->options(fn (Forms\Get $get): array => StudyProgram::query()
                            ->where('unit_id', $get('unit_id'))
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (StudyProgram $program): array => [$program->id => $program->label()])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get): bool => (bool) Unit::query()->find($get('unit_id'))?->isHigherEducation()),
                    Forms\Components\Select::make('registration_pathway_id')
                        ->label('Jalur Pendaftaran')
                        ->helperText('Kosongkan jika FAQ berlaku untuk semua jalur.')
                        ->options(fn (Forms\Get $get): array => RegistrationPathway::query()
                            ->where('unit_id', $get('unit_id'))
                            ->whereNull('archived_at')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload(),
                ])
                ->columns(3),
            Forms\Components\Section::make('Isi FAQ')
                ->schema([
                    Forms\Components\TextInput::make('question')
                        ->label('Pertanyaan')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('answer')
                        ->label('Jawaban')
                        ->toolbarButtons([
                            'h2',
                            'h3',
                            'bold',
                            'italic',
                            'bulletList',
                            'orderedList',
                            'link',
                            'undo',
                            'redo',
                        ])
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Tampilkan')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('question')->label('Pertanyaan')->searchable()->limit(70)->wrap(),
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->badge(),
                Tables\Columns\TextColumn::make('studyProgram.name')->label('Program Studi')->placeholder('Semua'),
                Tables\Columns\TextColumn::make('registrationPathway.name')->label('Jalur')->placeholder('Semua'),
                Tables\Columns\TextColumn::make('sort_order')->label('Urutan')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Tampil')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()
                        ->forOperationalMode()
                        ->when(
                            auth()->user()?->isAdminUnit(),
                            fn (Builder $query): Builder => $query->whereKey(auth()->user()->unit_id),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['unit', 'studyProgram', 'registrationPathway'])
            ->when(
                auth()->user()?->isAdminUnit(),
                fn (Builder $query): Builder => $query->where('unit_id', auth()->user()->unit_id),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
