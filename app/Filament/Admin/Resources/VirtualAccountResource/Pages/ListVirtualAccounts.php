<?php

namespace App\Filament\Admin\Resources\VirtualAccountResource\Pages;

use App\Filament\Admin\Resources\VirtualAccountResource;
use App\Services\VirtualAccountImportService;
use App\Services\VirtualAccountTemplateService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListVirtualAccounts extends ListRecords
{
    protected static string $resource = VirtualAccountResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'available' => Tab::make('Tersedia')->query(fn (Builder $query): Builder => $query->where('status', 'available')),
            'assigned' => Tab::make('Dialokasikan')->query(fn (Builder $query): Builder => $query->where('status', 'assigned')),
            'paid' => Tab::make('Lunas')->query(fn (Builder $query): Builder => $query->where('status', 'paid')),
            'expired' => Tab::make('Kedaluwarsa')->query(fn (Builder $query): Builder => $query->where('status', 'expired')),
            'cancelled' => Tab::make('Dibatalkan')->query(fn (Builder $query): Builder => $query->where('status', 'cancelled')),
        ];
    }

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
                            $user = auth()->user();

                            if ($user?->isTU()) {
                                $unit = $user->unit;
                                $unitLabel = $unit?->code ?? $unit?->name ?? '-';
                                $hasPrograms = $unit?->studyPrograms()->where('is_active', true)->exists() ?? false;

                                if ($hasPrograms) {
                                    return "Unit otomatis mengikuti akun ({$unitLabel}). XLSX: va_number, bank, prodi. Kolom prodi berisi kode prodi dan boleh dikosongkan untuk VA umum/fallback. TXT/CSV: nomor VA | BANK | KODE_PRODI.";
                                }

                                return "Unit otomatis mengikuti akun ({$unitLabel}). XLSX: va_number, bank. TXT/CSV: nomor VA | BANK.";
                            }

                            return 'Super admin wajib menyertakan unit. XLSX: va_number, bank, unit, prodi. Kolom prodi berisi kode prodi dan boleh kosong untuk VA umum. TXT/CSV: nomor VA | BANK | UNIT | KODE_PRODI.';
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
                        ->helperText('Import bersifat atomik: jika ada satu baris tidak valid, seluruh file dibatalkan dan tidak ada VA yang disimpan.'),
                ])
                ->action(function (array $data): void {
                    $result = app(VirtualAccountImportService::class)->importFile($data['file'], auth()->user());

                    if ($result['failed'] > 0) {
                        $preview = collect($result['errors'] ?? [])->take(5)->implode("\n");
                        $more = count($result['errors'] ?? []) > 5
                            ? "\n... dan kesalahan lain. Perbaiki file lalu upload ulang."
                            : '';

                        Notification::make()
                            ->title('Import Pool VA dibatalkan')
                            ->body("Ditemukan {$result['failed']} baris tidak valid. Tidak ada VA yang disimpan.\n{$preview}{$more}")
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Pool VA berhasil diimport')
                        ->body("{$result['imported']} VA masuk. {$result['assigned']} pendaftar otomatis mendapat VA.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
