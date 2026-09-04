<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;
use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
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
            Forms\Components\Section::make('Periode, Program, Jadwal, dan Biaya')
                ->description('Sekolah cukup memilih unit. Perguruan tinggi wajib memilih program studi. Status buka dan tutup mengikuti jadwal secara otomatis.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit / Institusi')
                        ->relationship(
                            'unit',
                            'name',
                            fn (Builder $query): Builder => auth()->user()?->isTU() && auth()->user()?->unit_id
                                ? $query->whereKey(auth()->user()->unit_id)
                                : $query->where('is_active', true),
                        )
                        ->default(fn () => auth()->user()?->isTU() ? auth()->user()->unit_id : null)
                        ->disabled(fn (): bool => auth()->user()?->isTU() ?? false)
                        ->dehydrated()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('study_program_id', null))
                        ->required(),
                    Forms\Components\Select::make('study_program_id')
                        ->label('Program Studi')
                        ->options(fn (Forms\Get $get): array => StudyProgram::query()
                            ->where('unit_id', $get('unit_id'))
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn (StudyProgram $program): array => [$program->id => $program->label()])
                            ->all())
                        ->visible(fn (Forms\Get $get): bool => static::isUniversityUnit($get('unit_id')))
                        ->required(fn (Forms\Get $get): bool => static::isUniversityUnit($get('unit_id')))
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('academic_year')
                        ->label('Tahun Ajaran / Akademik')
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
                    Forms\Components\TextInput::make('registration_fee')
                        ->label('Biaya Pendaftaran')
                        ->prefix('Rp')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->helperText('Nominal ini otomatis disalin ke transaksi pembayaran saat VA di-assign.'),
                    Forms\Components\DateTimePicker::make('opened_at')
                        ->label('Dibuka pada')
                        ->native(false)
                        ->seconds(false)
                        ->required(),
                    Forms\Components\DateTimePicker::make('closed_at')
                        ->label('Ditutup pada')
                        ->native(false)
                        ->seconds(false)
                        ->after('opened_at')
                        ->required(),
                    Forms\Components\Textarea::make('description')
                        ->label('Keterangan')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('unit.name')->label('Unit / Institusi')->badge()->sortable(),
                Tables\Columns\TextColumn::make('studyProgram.name')
                    ->label('Program Studi')
                    ->formatStateUsing(fn ($state, RegistrationOpening $record): string => $record->studyProgram?->label() ?? '-')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('academic_year')->label('Tahun')->sortable(),
                Tables\Columns\TextColumn::make('wave')->label('Gelombang')->searchable(),
                Tables\Columns\TextColumn::make('registration_fee')->label('Biaya')->money('IDR', locale: 'id')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (RegistrationOpening $record): string => $record->operationalStatus())
                    ->formatStateUsing(fn (string $state): string => RegistrationOpening::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'warning',
                        'archived' => 'gray',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('opened_at')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('closed_at')->label('Selesai')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('registrations_count')->counts('registrations')->label('Pendaftar'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('operational_status')
                    ->label('Status')
                    ->options(RegistrationOpening::STATUSES)
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'scheduled' => $query
                                ->where('status', '!=', 'archived')
                                ->whereNotNull('opened_at')
                                ->where('opened_at', '>', now()),
                            'open' => $query->currentlyOpen(),
                            'closed' => $query
                                ->where('status', '!=', 'archived')
                                ->where(function (Builder $closed): void {
                                    $closed
                                        ->where('closed_at', '<=', now())
                                        ->orWhere(function (Builder $legacy): void {
                                            $legacy
                                                ->where('status', 'closed')
                                                ->where(function (Builder $incompleteSchedule): void {
                                                    $incompleteSchedule->whereNull('opened_at')->orWhereNull('closed_at');
                                                });
                                        });
                                }),
                            'archived' => $query->where(function (Builder $archived): void {
                                $archived->where('status', 'archived')->orWhereNotNull('archived_at');
                            }),
                            'draft' => $query
                                ->where('status', 'draft')
                                ->where(function (Builder $incompleteSchedule): void {
                                    $incompleteSchedule->whereNull('opened_at')->orWhereNull('closed_at');
                                }),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('unit_id')->label('Unit / Institusi')->relationship('unit', 'name'),
                Tables\Filters\SelectFilter::make('study_program_id')->label('Program Studi')->relationship('studyProgram', 'name'),
                Tables\Filters\SelectFilter::make('academic_year')
                    ->options(fn (): array => RegistrationOpening::query()->orderByDesc('academic_year')->pluck('academic_year', 'academic_year')->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('archive')
                    ->label('Arsipkan')->icon('heroicon-o-archive-box')->color('gray')->requiresConfirmation()
                    ->visible(fn (RegistrationOpening $record): bool => $record->operationalStatus() !== 'archived')
                    ->action(function (RegistrationOpening $record): void {
                        $record->archive();
                        Notification::make()->title('Pembukaan diarsipkan')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['unit', 'studyProgram']);

        if (auth()->user()?->isTU() && auth()->user()?->unit_id) {
            $query->where('unit_id', auth()->user()->unit_id);
        }

        return $query;
    }

    public static function isUniversityUnit($unitId): bool
    {
        return filled($unitId) && Unit::query()->whereKey($unitId)->where('institution_type', 'university')->exists();
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
