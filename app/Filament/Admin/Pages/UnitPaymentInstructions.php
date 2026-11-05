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
        $instruction = UnitPaymentInstruction::query()->where('unit_id', $unit->id)->first();

        $this->form->fill([
            'content' => $instruction?->content,
            'show_on_payment_page' => $instruction?->show_on_payment_page ?? true,
            'show_on_next_action' => $instruction?->show_on_next_action ?? false,
            'show_on_payment_summary' => $instruction?->show_on_payment_summary ?? false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Isi Petunjuk Pembayaran')
                ->description('Satu isi petunjuk per unit. Lokasi tampil dapat diatur di bawah; tidak mengubah VA, nominal, atau verifikasi.')
                ->schema([
                    Forms\Components\RichEditor::make('content')
                        ->label('Petunjuk Pembayaran')
                        ->helperText('Gunakan informasi yang sesuai dengan metode pembayaran unit. Format judul, tebal, miring, daftar, dan tautan akan dipertahankan.')
                        ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                        ->maxLength(20000)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Lokasi Kemunculan')
                ->description('Toggle hanya mengatur petunjuk, bukan menyembunyikan VA, status, atau tombol unggah. Petunjuk tidak tampil setelah pembayaran terverifikasi.')
                ->schema([
                    Forms\Components\Toggle::make('show_on_payment_page')
                        ->label('Halaman Pembayaran')
                        ->helperText('Tampil di atas informasi Virtual Account pada halaman unggah bukti pembayaran.')
                        ->default(true),
                    Forms\Components\Toggle::make('show_on_next_action')
                        ->label('Aksi Selanjutnya')
                        ->helperText('Tampil saat tahap pembayaran di halaman progres.')
                        ->default(false),
                    Forms\Components\Toggle::make('show_on_payment_summary')
                        ->label('Ringkasan Pembayaran')
                        ->helperText('Tampil dalam kartu Pembayaran di halaman progres. Mengaktifkan juga Aksi Selanjutnya menyebabkan konten muncul di dua lokasi.')
                        ->default(false),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $unit = $this->accessibleUnit();
        $data = $this->form->getState();
        $content = app(RegistrationConsentService::class)
            ->sanitizeHtml((string) ($data['content'] ?? ''));

        $attributes = [
            'content' => $content !== '' ? $content : null,
            'show_on_payment_page' => (bool) ($data['show_on_payment_page'] ?? true),
            'show_on_next_action' => (bool) ($data['show_on_next_action'] ?? false),
            'show_on_payment_summary' => (bool) ($data['show_on_payment_summary'] ?? false),
        ];

        UnitPaymentInstruction::query()->updateOrCreate(['unit_id' => $unit->id], $attributes);
        $this->form->fill($attributes);

        Notification::make()
            ->title('Petunjuk pembayaran tersimpan')
            ->body('Petunjuk dan pengaturan lokasinya tersimpan untuk unit ini.')
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
