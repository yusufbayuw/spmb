<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;
use App\Models\RegistrationOpening;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationOpeningResource extends Resource
{
    protected static ?string $model = RegistrationOpening::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Pembukaan Pendaftaran';
    protected static ?string $modelLabel = 'Pembukaan Pendaftaran';
    protected static ?string $pluralModelLabel = 'Pembukaan Pendaftaran';
    protected static ?string $navigationGroup = 'SPMB';
    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Periode dan Jalur')
                ->description('Satu pembukaan berlaku untuk satu unit, tahun ajaran, gelombang, dan jalur.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit Sekolah')
                        ->relationship(
                            'unit',
                            'name',
                            fn (Builder $query): Builder => auth()->user()?->isTU() && auth()->user()?->unit_id
                                ? $query->whereKey(auth()->user()->unit_id)
                                : $query->where('is_active', true),
                        )
                        ->default(fn () => auth()->user()?->isTU() ? auth()->user()?->unit_id : null)
                        ->disabled(fn (): bool => auth()->user()?->isTU() ?? false)
                        ->dehydrated()
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('academic_year')
                        ->label('Tahun Ajaran')
                        ->placeholder('2026/2027')
                        ->helperText('Gunakan format 2026/2027.')
                        ->rule('regex:/^\d{4}\/\d{4}$/')
                        ->required()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('wave')
                        ->label('Gelombang')
                        ->placeholder('Gelombang 1')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('pathway')
                        ->label('Jalur Pendaftaran')
                        ->placeholder('Reguler / Prestasi / lainnya')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\Textarea::make('description')
                        ->label('Keterangan')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(RegistrationOpening::STATUSES)
                        ->default('draft')
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->badge()->sortable(),
                Tables\Columns\TextColumn::make('academic_year')->label('Tahun Ajaran')->sortable(),
                Tables\Columns\TextColumn::make('wave')->label('Gelombang')->searchable(),
                Tables\Columns\TextColumn::make('pathway')->label('Jalur')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RegistrationOpening::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'warning',
                        'archived' => 'gray',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('Pendaftar'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(RegistrationOpening::STATUSES),
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name'),
                Tables\Filters\SelectFilter::make('academic_year')
                    ->options(fn (): array => RegistrationOpening::query()
                        ->orderByDesc('academic_year')
                        ->pluck('academic_year', 'academic_year')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (RegistrationOpening $record): bool => $record->status !== 'open')
                    ->action(function (RegistrationOpening $record): void {
                        $record->open();
                        Notification::make()->title('Pendaftaran dibuka')->success()->send();
                    }),
                Tables\Actions\Action::make('close')
                    ->label('Tutup')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (RegistrationOpening $record): bool => $record->status === 'open')
                    ->action(function (RegistrationOpening $record): void {
                        $record->close();
                        Notification::make()->title('Pendaftaran ditutup')->warning()->send();
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (RegistrationOpening $record): bool => $record->status !== 'archived')
                    ->action(function (RegistrationOpening $record): void {
                        $record->archive();
                        Notification::make()->title('Pembukaan diarsipkan')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('unit');

        if (auth()->user()?->isTU() && auth()->user()?->unit_id) {
            $query->where('unit_id', auth()->user()->unit_id);
        }

        return $query;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrationOpenings::route('/'),
            'create' => Pages\CreateRegistrationOpening::route('/create'),
            'edit' => Pages\EditRegistrationOpening::route('/{record}/edit'),
        ];
    }
}
