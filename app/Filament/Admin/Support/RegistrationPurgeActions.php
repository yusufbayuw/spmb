<?php

namespace App\Filament\Admin\Support;

use App\Models\Registration;
use App\Services\RegistrationPurgeService;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;

class RegistrationPurgeActions
{
    public static function table(): TableAction
    {
        return self::configure(TableAction::make('permanentlyPurge'));
    }

    public static function page(): PageAction
    {
        return self::configure(PageAction::make('permanentlyPurge'));
    }

    private static function configure(TableAction|PageAction $action): TableAction|PageAction
    {
        return $action
            ->label('Hapus Permanen')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (Registration $record): bool => app(RegistrationPurgeService::class)
                ->canPurge(auth()->user(), $record))
            ->requiresConfirmation()
            ->modalHeading('Hapus total data pendaftaran?')
            ->modalDescription('Baca seluruh dampak sebelum mengetik kode konfirmasi. Ini bukan arsip atau pembatalan.')
            ->modalContent(fn (Registration $record) => view('filament.admin.components.registration-purge-preview', [
                'registration' => $record,
                'preview' => app(RegistrationPurgeService::class)->preview($record, auth()->user()),
            ]))
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Hapus Permanen')
            ->form([
                Forms\Components\Hidden::make('fingerprint')
                    ->default(fn (Registration $record): string => app(RegistrationPurgeService::class)
                        ->preview($record, auth()->user())['fingerprint'])
                    ->required(),
                Forms\Components\TextInput::make('confirmation')
                    ->label(fn (Registration $record): string => 'Ketik persis: HAPUS-'.$record->uuid)
                    ->helperText('Termasuk UUID. Salin teks di atas untuk menyetujui penghapusan permanen.')
                    ->required(),
                Forms\Components\Textarea::make('reason')
                    ->label('Alasan administratif')
                    ->helperText('Minimal 10 karakter. Alasan tidak direkam sebagai data pribadi dalam audit.')
                    ->required()
                    ->minLength(10),
            ])
            ->action(function (Registration $record, array $data): void {
                $result = app(RegistrationPurgeService::class)->purge(
                    $record,
                    auth()->user(),
                    $data['fingerprint'],
                    $data['confirmation'],
                    $data['reason'],
                );

                if ($result['cleanup_pending']) {
                    Notification::make()
                        ->title('Data database terhapus, pembersihan berkas belum lengkap')
                        ->body('Petugas teknis perlu mengulangi pembersihan melalui manifest privat '.$result['manifest'].'.')
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Pendaftaran dan seluruh data khususnya terhapus permanen')
                    ->success()
                    ->send();
            });
    }
}
