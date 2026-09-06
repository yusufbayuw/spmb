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
            'all' => Tab::make('Semua'),
            'pending' => Tab::make('Menunggu')->query(fn (Builder $query): Builder => $query->where('status', 'pending')),
            'paid' => Tab::make('Bukti Diunggah')->query(fn (Builder $query): Builder => $query->where('status', 'paid')),
            'verified' => Tab::make('Terverifikasi')->query(fn (Builder $query): Builder => $query->where('status', 'verified')),
            'rejected' => Tab::make('Ditolak')->query(fn (Builder $query): Builder => $query->where('status', 'rejected')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
