<?php

namespace App\Filament\Admin\Resources\AdmissionTestResultResource\Pages;

use App\Filament\Admin\Resources\AdmissionTestResultResource;
use App\Models\AdmissionTest;
use App\Services\AdmissionTestResultSpreadsheetService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListAdmissionTestResults extends ListRecords
{
    protected static string $resource = AdmissionTestResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadResults')
                ->label('Download Hasil Tes')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => (bool) auth()->user()?->can('record_result_admissiontestresult'))
                ->form([
                    Select::make('admission_test_id')
                        ->label('Tes')
                        ->options(fn (): array => $this->testOptions())
                        ->searchable()
                        ->required(),
                ])
                ->modalDescription('Download satu file XLSX berisi seluruh peserta pada tes yang dipilih. Edit nilai/status/hasil di file tersebut lalu upload kembali.')
                ->action(function (array $data) {
                    $test = AdmissionTest::query()->findOrFail((int) $data['admission_test_id']);

                    return app(AdmissionTestResultSpreadsheetService::class)
                        ->download($test, auth()->user());
                }),
            Actions\Action::make('uploadResults')
                ->label('Upload Hasil Tes')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn (): bool => (bool) auth()->user()?->can('record_result_admissiontestresult'))
                ->form([
                    FileUpload::make('file')
                        ->label('File hasil tes (.xlsx)')
                        ->helperText('Gunakan file yang sebelumnya diunduh dari sistem. Seluruh baris akan divalidasi sebelum disimpan.')
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;

                    if (is_array($file)) {
                        $file = collect($file)
                            ->first(fn ($item): bool => $item instanceof TemporaryUploadedFile);
                    }

                    if (! $file instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages([
                            'file' => 'File XLSX tidak valid. Pilih ulang file hasil download sistem.',
                        ]);
                    }

                    $result = app(AdmissionTestResultSpreadsheetService::class)
                        ->import($file->getRealPath(), auth()->user());

                    Notification::make()
                        ->title("Hasil {$result['test']->name} berhasil diupload")
                        ->body("{$result['updated']} hasil diperbarui; {$result['skipped']} baris tidak berubah.")
                        ->success()
                        ->send();
                }),
        ];
    }

    /** @return array<int,string> */
    private function testOptions(): array
    {
        return AdmissionTest::query()
            ->with('unit')
            ->whereHas('results')
            ->when(
                auth()->user()?->isTU(),
                fn (Builder $query): Builder => $query->where('unit_id', auth()->user()->unit_id),
            )
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (AdmissionTest $test): array => [
                $test->id => ($test->unit?->name ? $test->unit->name.' · ' : '').$test->name,
            ])
            ->all();
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'scheduled';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'unbooked' => Tab::make('Belum Terjadwal')->query(fn (Builder $query): Builder => $query->where('status', 'unbooked')),
            'scheduled' => Tab::make('Terjadwal')->query(fn (Builder $query): Builder => $query->where('status', 'scheduled')),
            'completed' => Tab::make('Selesai')->query(fn (Builder $query): Builder => $query->where('status', 'completed')),
            'absent' => Tab::make('Tidak Hadir')->query(fn (Builder $query): Builder => $query->where('status', 'absent')),
            'exempted' => Tab::make('Dibebaskan')->query(fn (Builder $query): Builder => $query->where('status', 'exempted')),
        ];
    }
}
