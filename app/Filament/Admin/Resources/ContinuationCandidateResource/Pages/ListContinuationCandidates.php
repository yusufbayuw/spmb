<?php

namespace App\Filament\Admin\Resources\ContinuationCandidateResource\Pages;

use App\Filament\Admin\Resources\ContinuationCandidateResource;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Services\ContinuationCandidateImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ListContinuationCandidates extends ListRecords
{
    protected static string $resource = ContinuationCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Import Data')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => ContinuationCandidateResource::canCreate())
                ->form([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit Tujuan')
                        ->options(fn (): array => Unit::query()->operational()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?int => auth()->user()?->unit_id)
                        ->disabled(fn (): bool => auth()->user()?->isAdminUnit() ?? false)
                        ->dehydrated()
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('academic_year')
                        ->label('Tahun Ajaran')
                        ->options(fn (Forms\Get $get): array => filled($get('unit_id'))
                            ? RegistrationOpening::query()
                                ->where('unit_id', $get('unit_id'))
                                ->select('academic_year')
                                ->distinct()
                                ->orderByDesc('academic_year')
                                ->pluck('academic_year', 'academic_year')
                                ->all()
                            : [])
                        ->searchable()
                        ->required(),
                    Forms\Components\FileUpload::make('file')
                        ->label('File Excel / CSV')
                        ->disk('local')
                        ->directory('continuation-imports/'.auth()->id())
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                        ])
                        ->maxSize(20480)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();
                    $unitId = (int) ($data['unit_id'] ?? 0);

                    if (! $user || ! $unitId) {
                        throw ValidationException::withMessages(['unit_id' => 'Unit tujuan tidak valid.']);
                    }

                    if ($user->isAdminUnit() && (int) $user->unit_id !== $unitId) {
                        throw ValidationException::withMessages(['unit_id' => 'Admin Unit hanya dapat mengimpor data untuk unitnya sendiri.']);
                    }

                    if (! Unit::query()->operational()->whereKey($unitId)->exists()) {
                        throw ValidationException::withMessages(['unit_id' => 'Unit tujuan tidak aktif.']);
                    }

                    $path = (string) ($data['file'] ?? '');
                    if ($path === '' || ! Storage::disk('local')->exists($path)) {
                        throw ValidationException::withMessages(['file' => 'File import tidak ditemukan.']);
                    }

                    try {
                        $result = app(ContinuationCandidateImportService::class)->import(
                            Storage::disk('local')->path($path),
                            $unitId,
                            (string) $data['academic_year'],
                            (int) $user->id,
                            basename($path),
                        );
                    } finally {
                        Storage::disk('local')->delete($path);
                    }

                    $body = $result['created'].' data baru, '.$result['updated'].' diperbarui';
                    if ($result['skipped'] > 0) {
                        $body .= ', '.$result['skipped'].' dilewati';
                    }

                    $notification = Notification::make()
                        ->title('Import Terusan selesai')
                        ->body($body)
                        ->success();

                    if ($result['errors'] !== []) {
                        $notification
                            ->warning()
                            ->body($body.'. '.implode(' | ', array_slice($result['errors'], 0, 3)));
                    }

                    $notification->send();
                }),
        ];
    }
}
