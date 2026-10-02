<?php

namespace App\Filament\Admin\Pages;

use App\Models\AccountConsentPolicy;
use App\Services\AccountConsentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class AccountConsentSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Kebijakan & Persetujuan Akun';
    protected static ?string $title = 'Kebijakan & Persetujuan Akun';
    protected static ?string $navigationGroup = 'Sistem & Akses';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.admin.pages.account-consent-settings';

    #[Locked]
    public ?string $policyUuid = null;

    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadDraft();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ketentuan Penggunaan')
                    ->description('Berlaku global saat seseorang membuat akun SPMB. Ini berbeda dari persetujuan pemrosesan data pada formulir pendaftaran unit.')
                    ->schema([
                        Forms\Components\TextInput::make('terms_title')->label('Judul')->required()->maxLength(180),
                        Forms\Components\RichEditor::make('terms_content')
                            ->label('Isi Ketentuan Penggunaan')
                            ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Kebijakan Privasi Platform')
                    ->schema([
                        Forms\Components\TextInput::make('privacy_title')->label('Judul')->required()->maxLength(180),
                        Forms\Components\RichEditor::make('privacy_content')
                            ->label('Isi Kebijakan Privasi')
                            ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Konfirmasi Saat Membuat Akun')
                    ->schema([
                        Forms\Components\Textarea::make('required_confirmation_text')
                            ->label('Kalimat Persetujuan Wajib')
                            ->helperText('Checkbox ini wajib dicentang sebelum akun dapat dibuat.')
                            ->rows(3)
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('marketing_enabled')
                            ->label('Tampilkan pilihan informasi & promosi')
                            ->helperText('Pilihan ini selalu opsional dan tidak boleh menghalangi pembuatan akun.')
                            ->default(true)
                            ->live(),
                        Forms\Components\Textarea::make('marketing_text')
                            ->label('Kalimat Persetujuan Informasi & Promosi')
                            ->rows(3)
                            ->required(fn (Forms\Get $get): bool => (bool) $get('marketing_enabled'))
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('marketing_enabled'))
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        app(\App\Services\CertificationAccessService::class)
            ->assertSensitiveOperation(auth()->user(), 'mengubah kebijakan persetujuan akun');

        $policy = AccountConsentPolicy::query()->where('uuid', $this->policyUuid)->firstOrFail();

        app(AccountConsentService::class)->save($policy, auth()->user(), $this->form->getState());
        $this->loadDraft();

        Notification::make()->title('Draft kebijakan akun tersimpan')->success()->send();
    }

    public function publish(): void
    {
        app(\App\Services\CertificationAccessService::class)
            ->assertSensitiveOperation(auth()->user(), 'mempublikasikan kebijakan persetujuan akun');

        $service = app(AccountConsentService::class);
        $policy = AccountConsentPolicy::query()->where('uuid', $this->policyUuid)->firstOrFail();
        $published = $service->save($policy, auth()->user(), $this->form->getState(), true);

        $version = $published->version;
        $this->loadDraft();

        Notification::make()
            ->title('Kebijakan akun v'.$version.' dipublikasikan')
            ->body('Pendaftar baru wajib menyetujui versi ini. Snapshot persetujuan lama tetap tidak berubah.')
            ->success()
            ->send();
    }

    public function publishedVersion(): int
    {
        return (int) app(AccountConsentService::class)->current()->version;
    }

    private function loadDraft(): void
    {
        $draft = app(AccountConsentService::class)->draft(auth()->user());
        $this->policyUuid = $draft->uuid;
        $this->form->fill($draft->only([
            'terms_title',
            'terms_content',
            'privacy_title',
            'privacy_content',
            'required_confirmation_text',
            'marketing_enabled',
            'marketing_text',
        ]));
    }
}
