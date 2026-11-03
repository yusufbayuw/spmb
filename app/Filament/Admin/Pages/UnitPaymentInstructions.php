<?php

namespace App\Filament\Admin\Pages;

use App\Models\Unit;
use App\Models\UnitPaymentInstruction;
use App\Services\RegistrationConsentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class UnitPaymentInstructions extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Petunjuk Pembayaran';
    protected static ?string $title = 'Petunjuk Pembayaran';
    protected static ?string $navigationGroup = 'Pendaftaran';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.admin.pages.unit-payment-instructions';

    public ?string $unitUuid = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active
            && ($user->isAdmin() || ($user->isTU() && $user->hasOperationalUnitAccess()));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $user = auth()->user();
        $this->unitUuid = $user->isAdmin()
            ? Unit::query()->operational()->orderBy('name')->value('uuid')
            : $user->unit?->uuid;

        if ($this->unitUuid) {
            $this->loadUnit();
        }
    }

    /** @return array<string, string> */
    public function units(): array
    {
        $user = auth()->user();

        return Unit::query()->operational()
            ->when(! $user->isAdmin(), fn ($query) => $query->whereKey($user->unit_id))
            ->orderBy('name')->pluck('name', 'uuid')->all();
    }

    public function loadUnit(): void
    {
        $unit = $this->accessibleUnit();

        $this->form->fill([
            'content' => UnitPaymentInstruction::query()
                ->where('unit_id', $unit->id)->value('content'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Petunjuk untuk Unggah Bukti Pembayaran')
                ->description('Opsional. Isi tampil di atas formulir unggah bukti pembayaran milik unit ini. Tidak mengubah nomor VA, metode pembayaran, atau proses verifikasi.')
                ->schema([
                    Forms\Components\RichEditor::make('content')
                        ->label('Petunjuk Pembayaran')
                        ->helperText('Gunakan informasi yang sesuai dengan metode pembayaran unit. Format judul, tebal, miring, daftar, dan tautan akan dipertahankan.')
                        ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                        ->maxLength(20000)
                        ->columnSpanFull(),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $unit = $this->accessibleUnit();
        $data = $this->form->getState();
        $content = app(RegistrationConsentService::class)
            ->sanitizeHtml((string) ($data['content'] ?? ''));

        UnitPaymentInstruction::query()->updateOrCreate(
            ['unit_id' => $unit->id],
            ['content' => $content !== '' ? $content : null],
        );

        $this->form->fill(['content' => $content]);

        Notification::make()
            ->title('Petunjuk pembayaran tersimpan')
            ->body('Informasi langsung ditampilkan kepada pendaftar unit ini pada halaman unggah bukti pembayaran.')
            ->success()->send();
    }

    private function accessibleUnit(): Unit
    {
        abort_unless(static::canAccess(), 403);

        $user = auth()->user();

        return Unit::query()->operational()
            ->when(! $user->isAdmin(), fn ($query) => $query->whereKey($user->unit_id))
            ->where('uuid', $this->unitUuid)
            ->firstOrFail();
    }
}
