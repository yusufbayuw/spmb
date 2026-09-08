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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

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
                ->modalDescription('Download satu file XLSX berisi seluruh peserta pada tes yang dipilih. Untuk hasil normal, cukup isi NILAI/HASIL; STATUS TERJADWAL akan otomatis diproses sebagai SELESAI saat hasil diisi.')
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
                        ->helperText('Gunakan file hasil download sistem. Isi NILAI/HASIL untuk peserta yang selesai; ubah STATUS hanya untuk TIDAK HADIR atau DIBEBASKAN.')
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(10240)
                        ->validationMessages([
                            'required' => 'File hasil tes wajib dipilih.',
                            'mimetypes' => 'File harus berformat XLSX hasil download sistem.',
                            'max' => 'Ukuran file hasil tes maksimal 10 MB.',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;

                    if (is_array($file)) {
                        $file = collect($file)
                            ->first(fn ($item): bool => $item instanceof TemporaryUploadedFile);
                    }

                    if (! $file instanceof TemporaryUploadedFile) {
                        $this->notifyUploadFailure('File XLSX tidak valid. Pilih ulang file hasil download sistem.');

                        return;
                    }

                    try {
                        $result = app(AdmissionTestResultSpreadsheetService::class)
                            ->import($file->getRealPath(), auth()->user());
                    } catch (ValidationException $exception) {
                        $this->notifyUploadFailure($this->validationErrorBody($exception));

                        return;
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->notifyUploadFailure('File XLSX tidak dapat diproses. Pastikan file tidak rusak dan merupakan file hasil download dari menu Hasil Tes.');

                        return;
                    }

                    if ($result['updated'] === 0) {
                        Notification::make()
                            ->title('Tidak ada hasil yang diperbarui')
                            ->body('Semua baris belum berisi hasil baru atau nilainya sama dengan data sistem. Isi NILAI/HASIL untuk peserta yang sudah selesai tes.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title("Hasil {$result['test']->name} berhasil diupload")
                        ->body("{$result['updated']} hasil diperbarui; {$result['skipped']} baris tidak berubah.")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function onValidationError(ValidationException $exception): void
    {
        $isUploadError = collect(array_keys($exception->errors()))
            ->contains(fn (string $key): bool => $key === 'file' || Str::endsWith($key, '.file'));

        Notification::make()
            ->title($isUploadError ? 'Upload Hasil Tes gagal' : 'Data belum valid')
            ->body($this->validationErrorBody($exception))
            ->danger()
            ->persistent()
            ->send();
    }

    private function notifyUploadFailure(string $message): void
    {
        Notification::make()
            ->title('Upload Hasil Tes gagal')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }

    private function validationErrorBody(ValidationException $exception): string
    {
        $messages = collect($exception->errors())
            ->flatten()
            ->filter(fn ($message): bool => is_string($message) && $message !== '')
            ->unique()
            ->take(20)
            ->values();

        if ($messages->isEmpty()) {
            return 'File hasil tes tidak valid. Periksa file lalu coba upload kembali.';
        }

        return $messages
            ->map(fn (string $message): string => '- '.$message)
            ->implode("\n");
    }

    /** @return array<int,string> */
    private function testOptions(): array
    {
        return AdmissionTest::query()
            ->with('unit')
            ->whereHas('results', fn (Builder $result): Builder => $result
                ->where('status', 'scheduled')
                ->whereHas('registration', fn (Builder $registration): Builder => $registration
                    ->where('lifecycle_status', 'active')
                    ->where('current_stage', 'tests'))
                ->whereExists(function ($booking): void {
                    $booking->selectRaw('1')
                        ->from('test_bookings')
                        ->whereColumn('test_bookings.registration_id', 'admission_test_results.registration_id')
                        ->whereColumn('test_bookings.admission_test_id', 'admission_test_results.admission_test_id')
                        ->whereNotNull('test_bookings.test_session_id');
                }))
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
            'scheduled' => Tab::make('Terjadwal')->query(fn (Builder $query): Builder => $query
                ->where('status', 'scheduled')
                ->whereExists(function ($booking): void {
                    $booking->selectRaw('1')
                        ->from('test_bookings')
                        ->whereColumn('test_bookings.registration_id', 'admission_test_results.registration_id')
                        ->whereColumn('test_bookings.admission_test_id', 'admission_test_results.admission_test_id')
                        ->whereNotNull('test_bookings.test_session_id');
                })),
            'completed' => Tab::make('Selesai')->query(fn (Builder $query): Builder => $query->where('status', 'completed')),
            'absent' => Tab::make('Tidak Hadir')->query(fn (Builder $query): Builder => $query->where('status', 'absent')),
            'exempted' => Tab::make('Dibebaskan')->query(fn (Builder $query): Builder => $query->where('status', 'exempted')),
        ];
    }
}
