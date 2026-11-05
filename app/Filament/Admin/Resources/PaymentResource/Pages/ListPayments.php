<?php

namespace App\Filament\Admin\Resources\PaymentResource\Pages;

use App\Filament\Admin\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(fn (): int => PaymentResource::getEloquentQuery()->count()),
            'pending' => Tab::make('Menunggu')
                ->query(fn (Builder $query): Builder => $query->where('status', 'pending'))
                ->badge(fn (): int => PaymentResource::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'paid' => Tab::make('Bukti Diunggah')
                ->query(fn (Builder $query): Builder => $query->where('status', 'paid'))
                ->badge(fn (): int => PaymentResource::getEloquentQuery()->where('status', 'paid')->count())
                ->badgeColor('info'),
            'verified' => Tab::make('Terverifikasi')
                ->query(fn (Builder $query): Builder => $query->where('status', 'verified'))
                ->badge(fn (): int => PaymentResource::getEloquentQuery()->where('status', 'verified')->count())
                ->badgeColor('success'),
            'rejected' => Tab::make('Ditolak')
                ->query(fn (Builder $query): Builder => $query->where('status', 'rejected'))
                ->badge(fn (): int => PaymentResource::getEloquentQuery()->where('status', 'rejected')->count())
                ->badgeColor('danger'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
