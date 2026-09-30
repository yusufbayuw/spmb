<?php

namespace App\Filament\Admin\Pages;

use App\Models\Unit;
use App\Services\UnitConfigurationTransferService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitConfigurationTransfer extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Ekspor / Impor Konfigurasi';

    protected static ?string $title = 'Ekspor / Impor Konfigurasi Unit';

    protected static ?string $navigationGroup = 'Konfigurasi SPMB';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.admin.pages.unit-configuration-transfer';

    public ?string $unitUuid = null;

    public array $importData = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active
            && ($user->isAdmin() || ($user->isAdminUnit() && $user->hasOperationalUnitAccess()));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->unitUuid = auth()->user()->isAdminUnit()
            ? auth()->user()->unit?->uuid
            : Unit::query()->operational()->orderBy('name')->value('uuid');
    }

    protected function getForms(): array
    {
        return ['importForm'];
    }

    public function units(): array
    {
        return Unit::query()
            ->operational()
            ->when(
                auth()->user()?->isAdminUnit(),
                fn ($query) => $query->whereKey(auth()->user()->unit_id),
            )
            ->orderBy('name')
            ->pluck('name', 'uuid')
            ->all();
    }

    public function importForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('import_file')
                    ->label('Berkas konfigurasi JSON')
                    ->helperText('Gunakan berkas JSON yang dihasilkan dari menu ekspor ini. File media di storage seperti logo dan template dokumen perlu dipindahkan terpisah.')
                    ->disk('local')
                    ->directory('imports/unit-configuration')
                    ->acceptedFileTypes(['application/json', 'text/plain', 'application/octet-stream'])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->statePath('importData');
    }

    public function export(): StreamedResponse
    {
        $unit = $this->accessibleUnit();
        $payload = app(UnitConfigurationTransferService::class)->export($unit);
        $filename = 'spmb-konfigurasi-'.str($unit->code)->slug().'-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public function import(): void
    {
        $state = $this->importForm->getState();
        $path = $state['import_file'] ?? null;
        $path = is_array($path) ? (array_values($path)[0] ?? null) : $path;

        if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
            $this->addError('importData.import_file', 'Berkas impor tidak ditemukan.');

            return;
        }

        try {
            $payload = json_decode(
                Storage::disk('local')->get($path),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (! is_array($payload)) {
                throw new JsonException('Payload must be an object.');
            }

            $result = app(UnitConfigurationTransferService::class)
                ->import($this->accessibleUnit(), auth()->user(), $payload);
        } catch (JsonException $exception) {
            $this->addError('importData.import_file', 'Berkas JSON tidak valid: '.$exception->getMessage());

            Notification::make()
                ->title('Impor gagal')
                ->body('Berkas JSON tidak dapat dibaca.')
                ->danger()
                ->send();

            return;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('importData.import_file', $message);
                }
            }

            Notification::make()
                ->title('Impor konfigurasi gagal')
                ->body(collect($exception->errors())->flatten()->first() ?: 'Periksa kembali berkas konfigurasi.')
                ->danger()
                ->persistent()
                ->send();

            return;
        } finally {
            Storage::disk('local')->delete($path);
        }

        $this->importData = [];
        $this->importForm->fill();

        Notification::make()
            ->title('Konfigurasi unit berhasil diimpor')
            ->body(
                "{$result['faqs']} FAQ, {$result['pathways']} jalur, {$result['programs']} program studi, ".
                "{$result['openings']} pembukaan, {$result['quotas']} daya tampung, {$result['tests']} tes, ".
                "dan {$result['sessions']} sesi berhasil diproses. ".
                'Pengaturan pendaftaran dimasukkan sebagai draft agar dapat diperiksa sebelum dipublikasikan.',
            )
            ->success()
            ->send();
    }

    private function accessibleUnit(): Unit
    {
        return Unit::query()
            ->operational()
            ->when(
                auth()->user()?->isAdminUnit(),
                fn ($query) => $query->whereKey(auth()->user()->unit_id),
            )
            ->where('uuid', $this->unitUuid)
            ->firstOrFail();
    }
}
