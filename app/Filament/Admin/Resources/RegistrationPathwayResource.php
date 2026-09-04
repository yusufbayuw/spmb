<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RegistrationPathwayResource\Pages;
use App\Models\RegistrationPathway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class RegistrationPathwayResource extends Resource
{
    protected static ?string $model = RegistrationPathway::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Jalur Pendaftaran';

    protected static ?string $modelLabel = 'Jalur Pendaftaran';

    protected static ?string $pluralModelLabel = 'Jalur Pendaftaran';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Jalur Pendaftaran per Unit')
                ->description('Jalur aktif akan tersedia untuk dipilih pendaftar pada pembukaan milik unit ini.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit / Institusi')
                        ->relationship(
                            'unit',
                            'name',
                            fn (Builder $query): Builder => $query
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
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Jalur')
                        ->placeholder('Reguler / Prestasi / Afirmasi')
                        ->required()
                        ->maxLength(100)
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Forms\Get $get): Unique => $rule
                                ->where('unit_id', $get('unit_id')),
                        ),
                    Forms\Components\Textarea::make('description')
                        ->label('Keterangan')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->helperText('Hanya jalur aktif dan belum diarsipkan yang dapat dipilih pendaftar.')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('unit.name')->label('Unit / Institusi')->badge()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nama Jalur')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Keterangan')->limit(60)->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (RegistrationPathway $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Nonaktif' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('registrations_count')->counts('registrations')->label('Pendaftar'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit_id')->label('Unit / Institusi')->relationship('unit', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
                Tables\Filters\TernaryFilter::make('archived_at')
                    ->label('Arsip')
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (RegistrationPathway $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (RegistrationPathway $record): string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (RegistrationPathway $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (RegistrationPathway $record): bool => ! $record->isArchived())
                    ->action(function (RegistrationPathway $record): void {
                        $record->setActive(! $record->is_active);
                        Notification::make()->title('Status jalur diperbarui')->success()->send();
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Arsipkan')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (RegistrationPathway $record): bool => ! $record->isArchived())
                    ->action(function (RegistrationPathway $record): void {
                        $record->archive();
                        Notification::make()->title('Jalur pendaftaran diarsipkan')->success()->send();
                    }),
                Tables\Actions\Action::make('restore')
                    ->label('Pulihkan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (RegistrationPathway $record): bool => $record->isArchived())
                    ->action(function (RegistrationPathway $record): void {
                        $record->restoreFromArchive();
                        Notification::make()->title('Jalur dipulihkan dalam keadaan nonaktif')->success()->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (RegistrationPathway $record): bool => ! $record->isArchived()),
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

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrationPathways::route('/'),
            'create' => Pages\CreateRegistrationPathway::route('/create'),
            'edit' => Pages\EditRegistrationPathway::route('/{record}/edit'),
        ];
    }
}
