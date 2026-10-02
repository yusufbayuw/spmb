<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ContinuationCandidateResource\Pages;
use App\Models\ContinuationCandidate;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContinuationCandidateResource extends Resource
{
    protected static ?string $model = ContinuationCandidate::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Terusan';

    protected static ?string $modelLabel = 'Data Terusan';

    protected static ?string $pluralModelLabel = 'Terusan';

    protected static ?string $navigationGroup = 'Pendaftaran';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nama')
                    ->searchable()
                    ->weight('medium')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('birth_date')
                    ->label('Tanggal Lahir')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('source_school_name')
                    ->label('Sekolah Asal')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('academic_year')
                    ->label('Tahun Ajaran')
                    ->sortable(),
                Tables\Columns\TextColumn::make('links_count')
                    ->counts('links')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? 'Sudah Mendaftar' : 'Belum Mendaftar')
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('imported_at')
                    ->label('Diimpor')
                    ->dateTime('d/m/Y H:i', timezone: config('app.timezone'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('academic_year')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => static::scopedQuery()
                        ->select('academic_year')
                        ->distinct()
                        ->orderByDesc('academic_year')
                        ->pluck('academic_year', 'academic_year')
                        ->all()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Data')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (ContinuationCandidate $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (ContinuationCandidate $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (ContinuationCandidate $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (ContinuationCandidate $record): bool => static::canEdit($record))
                    ->action(fn (ContinuationCandidate $record) => $record->update(['is_active' => ! $record->is_active])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopedQuery()->withCount('links');
    }

    public static function scopedQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isAdminUnit() && $user->unit_id) {
            $query->where('unit_id', $user->unit_id);
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
            'index' => Pages\ListContinuationCandidates::route('/'),
        ];
    }
}
