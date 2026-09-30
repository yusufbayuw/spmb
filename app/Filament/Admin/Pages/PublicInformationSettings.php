<?php

namespace App\Filament\Admin\Pages;

use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PublicInformationSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Profil Penerimaan';
    protected static ?string $title = 'Profil Penerimaan';
    protected static ?string $navigationGroup = 'Informasi Publik';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.admin.pages.public-information-settings';

    public ?string $unitUuid = null;
    public array $data = [];

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

        if ($this->unitUuid) {
            $this->loadUnit();
        }
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

    public function loadUnit(): void
    {
        $unit = $this->accessibleUnit();

        $this->form->fill([
            'public_headline' => $unit->public_headline,
            'description' => $unit->description,
            'public_body' => $unit->public_body,
            'pre_registration_heading' => $unit->pre_registration_heading ?: 'Siapkan informasi utama',
            'pre_registration_body' => $unit->pre_registration_body,
            'pre_registration_items' => $unit->preRegistrationItems(),
            'public_contact_name' => $unit->public_contact_name,
            'public_email' => $unit->public_email,
            'public_phone' => $unit->public_phone,
            'public_whatsapp' => $unit->public_whatsapp,
            'public_service_hours' => $unit->public_service_hours,
            'public_website_url' => $unit->public_website_url,
            'public_address' => $unit->public_address,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Profil Penerimaan')
                    ->description('Konten ini berlaku untuk unit pada mode K12 maupun HIGHER_EDUCATION dan tampil langsung pada halaman publik unit.')
                    ->schema([
                        Forms\Components\TextInput::make('public_headline')
                            ->label('Judul Publik')
                            ->placeholder('Penerimaan Peserta Didik Baru')
                            ->maxLength(180),
                        Forms\Components\Textarea::make('description')
                            ->label('Ringkasan')
                            ->helperText('Gunakan untuk penjelasan singkat di bagian atas halaman unit.')
                            ->rows(3)
                            ->maxLength(2000),
                        Forms\Components\RichEditor::make('public_body')
                            ->label('Penjelasan Lengkap')
                            ->toolbarButtons([
                                'h2',
                                'h3',
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'link',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
                Forms\Components\Section::make('Sebelum Mendaftar')
                    ->description('Atur informasi persiapan yang tampil pada halaman pembukaan sebelum calon pendaftar menekan tombol Daftar Sekarang.')
                    ->schema([
                        Forms\Components\TextInput::make('pre_registration_heading')
                            ->label('Judul Bagian')
                            ->maxLength(180)
                            ->default('Siapkan informasi utama')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('pre_registration_body')
                            ->label('Pengantar')
                            ->helperText('Opsional. Format judul, tebal, daftar, dan tautan akan ditampilkan sama pada halaman publik.')
                            ->toolbarButtons([
                                'h2',
                                'h3',
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'link',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('pre_registration_items')
                            ->label('Daftar Persiapan')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Judul')
                                    ->required()
                                    ->maxLength(150),
                                Forms\Components\Textarea::make('description')
                                    ->label('Keterangan')
                                    ->rows(2)
                                    ->maxLength(1000),
                            ])
                            ->columns(['default' => 1, 'md' => 2])
                            ->addActionLabel('Tambah informasi')
                            ->reorderable()
                            ->maxItems(12)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),
                Forms\Components\Section::make('Kontak & Helpdesk')
                    ->description('Kontak resmi yang dapat dilihat calon pendaftar.')
                    ->schema([
                        Forms\Components\TextInput::make('public_contact_name')
                            ->label('Nama Helpdesk')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('public_email')
                            ->label('Email Publik')
                            ->email()
                            ->maxLength(150),
                        Forms\Components\TextInput::make('public_phone')
                            ->label('Telepon Publik')
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('public_whatsapp')
                            ->label('WhatsApp Publik')
                            ->helperText('Gunakan format internasional tanpa tanda +, contoh: 6281234567890.')
                            ->rule('regex:/^[0-9]{8,20}$/')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('public_service_hours')
                            ->label('Jam Layanan')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('public_website_url')
                            ->label('Website Unit')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('public_address')
                            ->label('Alamat Layanan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $unit = $this->accessibleUnit();
        $data = $this->form->getState();

        $unit->update([
            'public_headline' => $data['public_headline'] ?? null,
            'description' => $data['description'] ?? null,
            'public_body' => $data['public_body'] ?? null,
            'pre_registration_heading' => filled($data['pre_registration_heading'] ?? null)
                ? trim((string) $data['pre_registration_heading'])
                : null,
            'pre_registration_body' => $data['pre_registration_body'] ?? null,
            'pre_registration_items' => collect($data['pre_registration_items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['title'] ?? null))
                ->map(fn (array $item): array => [
                    'title' => trim((string) $item['title']),
                    'description' => trim((string) ($item['description'] ?? '')),
                ])
                ->values()
                ->all(),
            'public_contact_name' => $data['public_contact_name'] ?? null,
            'public_email' => $data['public_email'] ?? null,
            'public_phone' => $data['public_phone'] ?? null,
            'public_whatsapp' => $data['public_whatsapp'] ?? null,
            'public_service_hours' => $data['public_service_hours'] ?? null,
            'public_website_url' => $data['public_website_url'] ?? null,
            'public_address' => $data['public_address'] ?? null,
        ]);

        Notification::make()
            ->title('Informasi publik tersimpan')
            ->body('Perubahan profil penerimaan dan helpdesk langsung tersedia pada halaman publik unit.')
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
