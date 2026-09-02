<?php

namespace App\Filament\Admin\Resources\VirtualAccountResource\Pages;

use App\Filament\Admin\Resources\VirtualAccountResource;
use App\Services\VirtualAccountImportService;
use App\Services\VirtualAccountTemplateService;
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
            Actions\Action::make('downloadTemplate')
                ->label('Download Template XLSX')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('create_virtualaccount') ?? false)
                ->action(fn () => app(VirtualAccountTemplateService::class)->download(auth()->user())),

            Actions\Action::make('importPool')
                ->label('Upload Pool VA')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('create_virtualaccount') ?? false)
                ->form([
                    Forms\Components\Placeholder::make('format_info')
                        ->label('Format File')
                        ->content(function (): string {
                            if (auth()->user()?->isTU()) {
                                $unit = auth()->user()?->unit?->code ?? auth()->user()?->unit?->name ?? '-';

                                return "Unit otomatis mengikuti akun TU ({$unit}). XLSX: kolom va_number, bank. TXT/CSV: nomor VA | BANK.";
                            }

                            return 'Super admin wajib menyertakan unit. XLSX: kolom va_number, bank, unit. TXT/CSV: nomor VA | BANK | UNIT.';
                        }),
                    Forms\Components\FileUpload::make('file')
                        ->label('File Pool VA')
                        ->disk('local')
                        ->directory('imports/virtual-accounts')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(10240)
                        ->required()
                        ->helperText(fn (): string => auth()->user()?->isTU()
                            ? 'Mendukung XLSX, CSV, dan TXT. Untuk TU, unit otomatis memakai unit akun dan kolom unit tidak diperlukan.'
                            : 'Mendukung XLSX, CSV, dan TXT. Untuk super admin, setiap baris wajib memiliki unit.'),
                ])
                ->action(function (array $data): void {
                    $result = app(VirtualAccountImportService::class)->importFile($data['file'], auth()->user());

                    $notification = Notification::make()
                        ->title('Pool VA berhasil diproses')
                        ->body("{$result['imported']} VA masuk, {$result['failed']} gagal, {$result['assigned']} pendaftar otomatis mendapat VA.");

                    if ($result['failed'] > 0) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
        ];
    }
}
