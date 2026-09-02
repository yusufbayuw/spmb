<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\VirtualAccountResource\Pages;
use App\Models\VirtualAccount;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VirtualAccountResource extends Resource
{
    protected static ?string $model = VirtualAccount::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Pool Virtual Account';
    protected static ?string $modelLabel = 'Virtual Account';
    protected static ?string $pluralModelLabel = 'Pool Virtual Account';
    protected static ?string $navigationGroup = 'Pembayaran';
    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')->badge()->sortable(),
                Tables\Columns\TextColumn::make('bank')->label('Bank')->badge()->searchable(),
                Tables\Columns\TextColumn::make('va_number')->label('Nomor VA')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => VirtualAccount::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'assigned' => 'warning',
                        'paid' => 'info',
                        'expired', 'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('registration.registration_number')->label('No. Registrasi')->searchable()->default('-'),
                Tables\Columns\TextColumn::make('registration.full_name')->label('Calon Siswa')->searchable()->default('-'),
                Tables\Columns\TextColumn::make('assigned_at')->label('Assigned')->dateTime('d M Y H:i')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('expired_at')->label('Kedaluwarsa')->dateTime('d M Y H:i')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('batch.id')->label('Batch')->formatStateUsing(fn ($state) => $state ? '#'.$state : '-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit_id')->label('Unit')->relationship('unit', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(VirtualAccount::STATUSES),
                Tables\Filters\SelectFilter::make('bank')->options(fn () => VirtualAccount::query()->distinct()->orderBy('bank')->pluck('bank', 'bank')->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VirtualAccount $record): bool => $record->status === 'available' && auth()->user()?->can('update_virtualaccount'))
                    ->action(fn (VirtualAccount $record) => $record->update(['status' => 'cancelled'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVirtualAccounts::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['unit', 'registration', 'batch']);

        if (auth()->user()?->isTU() && auth()->user()->unit_id) {
            $query->where('unit_id', auth()->user()->unit_id);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->available()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
