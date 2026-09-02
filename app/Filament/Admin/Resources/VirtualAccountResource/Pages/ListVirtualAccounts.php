<?php

namespace App\Filament\Admin\Resources\VirtualAccountResource\Pages;

use App\Filament\Admin\Resources\VirtualAccountResource;
use App\Models\Unit;
use App\Services\VirtualAccountImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListVirtualAccounts extends ListRecords
{
    protected static string $resource = VirtualAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importCsv')
                ->label('Upload Pool VA')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('create_virtualaccount') ?? false)
                ->form([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit Sekolah')
                        ->options(function (): array {
                            $query = Unit::query()->where('is_active', true)->orderBy('name');
                            if (auth()->user()?->isTU() && auth()->user()->unit_id) {
                                $query->whereKey(auth()->user()->unit_id);
                            }
                            return $query->pluck('name', 'id')->all();
                        })
                        ->default(fn () => auth()->user()?->isTU() ? auth()->user()->unit_id : null)
                        ->disabled(fn (): bool => auth()->user()?->isTU() ?? false)
                        ->dehydrated()
                        ->required(),
                    Forms\Components\TextInput::make('bank')
                        ->label('Bank')
                        ->placeholder('Contoh: BCA')
                        ->maxLength(50)
                        ->required(),
                    Forms\Components\TextInput::make('default_amount')
                        ->label('Nominal Default')
                        ->prefix('Rp')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Kedaluwarsa Default')
                        ->native(false)
                        ->minDate(now())
                        ->helperText('Opsional. Dapat ditimpa per baris CSV.'),
                    Forms\Components\FileUpload::make('file')
                        ->label('File CSV')
                        ->disk('local')
                        ->directory('imports/virtual-accounts')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->maxSize(10240)
                        ->required()
                        ->helperText('Kolom: va_number (wajib), amount (opsional), expired_at (opsional). Tanpa header juga didukung dengan urutan yang sama.'),
                ])
                ->action(function (array $data): void {
                    $result = app(VirtualAccountImportService::class)->importCsv($data['file'], $data, auth()->user());

                    Notification::make()
                        ->title('Pool VA berhasil diproses')
                        ->body("{$result['imported']} VA masuk, {$result['failed']} gagal, {$result['assigned']} pendaftar otomatis mendapat VA.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
