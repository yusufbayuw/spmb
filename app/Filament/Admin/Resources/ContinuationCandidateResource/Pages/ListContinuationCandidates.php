<?php

namespace App\Filament\Admin\Resources\ContinuationCandidateResource\Pages;

use App\Filament\Admin\Resources\ContinuationCandidateResource;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Services\ContinuationCandidateExportService;
use App\Services\ContinuationCandidateImportService;
use App\Services\ContinuationCandidateTemplateService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListContinuationCandidates extends ListRecords
{
    protected static string $resource = ContinuationCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadCorrection')
                ->label('Download Data Koreksi')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->color('info')
                ->visible(fn (): bool => ContinuationCandidateResource::canViewAny())
                ->modalHeading('Download Data Terusan untuk Koreksi')
                ->modalDescription('File hasil download dapat diedit lalu diunggah kembali. Kolom ID Data Terusan jangan diubah karena dipakai untuk memperbarui record yang sama, termasuk saat NIK atau tanggal lahir diganti.')
                ->form([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit')
                        ->options(fn (): array => Unit::query()
                            ->whereIn('id', ContinuationCandidateResource::scopedQuery()->select('unit_id'))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (): ?int => auth()->user()?->unit_id)
                        ->disabled(fn (): bool => (auth()->user()?->isAdminUnit() ?? false) || (auth()->user()?->isTU() ?? false))
                        ->dehydrated()
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('academic_year')
                        ->label('Tahun Ajaran')
                        ->options(fn (Forms\Get $get): array => filled($get('unit_id'))
                            ? ContinuationCandidateResource::scopedQuery()
                                ->where('unit_id', (int) $get('unit_id'))
                                ->select('academic_year')
                                ->distinct()
                                ->orderByDesc('academic_year')
                                ->pluck('academic_year', 'academic_year')
                                ->all()
                            : [])
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): BinaryFileResponse {
                    $user = auth()->user();
                    $unitId = (int) ($data['unit_id'] ?? 0);
                    $academicYear = (string) ($data['academic_year'] ?? '');

                    if (! $user || ! $unitId || $academicYear === '') {
                        throw ValidationException::withMessages(['unit_id' => 'Unit dan tahun ajaran wajib dipilih.']);
                    }

                    if (($user->isAdminUnit() || $user->isTU()) && (int) $user->unit_id !== $unitId) {
                        throw ValidationException::withMessages(['unit_id' => 'Anda hanya dapat mengekspor data Terusan untuk unit sendiri.']);
                    }

                    if (! ContinuationCandidateResource::scopedQuery()
                        ->where('unit_id', $unitId)
                        ->where('academic_year', $academicYear)
                        ->exists()) {
                        throw ValidationException::withMessages(['academic_year' => 'Data Terusan untuk unit dan tahun ajaran tersebut tidak ditemukan.']);
                    }

                    return app(ContinuationCandidateExportService::class)->download(
                        $user,
                        $unitId,
                        $academicYear,
                    );
                }),

            Actions\Action::make('downloadTemplate')
                ->label('Download Template XLSX')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => ContinuationCandidateResource::canCreate())
                ->action(fn (): BinaryFileResponse => app(ContinuationCandidateTemplateService::class)->download(auth()->user())),

            Actions\Action::make('import')
                ->label('Import Data / Koreksi')
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
                        ->helperText('Untuk koreksi massal, unggah kembali file dari "Download Data Koreksi" dan jangan mengubah kolom ID Data Terusan.')
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
