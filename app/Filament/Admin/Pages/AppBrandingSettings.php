<?php

namespace App\Filament\Admin\Pages;

use App\Models\AppSetting;
use App\Services\AppBrandingService;
use App\Services\BrandMediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AppBrandingSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'White-label Aplikasi';

    protected static ?string $title = 'White-label Aplikasi';

    protected static ?string $navigationGroup = 'Sistem & Akses';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.admin.pages.app-branding-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active
            && auth()->user()->isAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $service = app(AppBrandingService::class);
        $settings = $service->settings();
        $effective = $service->effective();

        $this->form->fill([
            'portal_name' => $settings?->portal_name ?? $effective['name'],
            'organization_name' => $settings?->organization_name ?? $effective['foundation_name'],
            'website_url' => $settings?->website_url ?? $effective['foundation_website'],
            'email' => $settings?->email ?? $effective['foundation_email'],
            'phone' => $settings?->phone ?? $effective['foundation_phone'],
            'whatsapp' => $settings?->whatsapp ?? $effective['foundation_whatsapp'],
            'address' => $settings?->address ?? $effective['foundation_address'],
            'service_hours' => $settings?->service_hours ?? $effective['service_hours'],
            'logo_path' => $settings?->logo_path,
            'theme_color' => $settings?->theme_color ?? $effective['theme_color'] ?? '#2563eb',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Aplikasi')
                    ->description('Pengaturan ini menjadi identitas global aplikasi dan mengoverride nilai .env setelah disimpan.')
                    ->schema([
                        Forms\Components\TextInput::make('portal_name')
                            ->label('Nama Portal / Aplikasi')
                            ->required()
                            ->maxLength(180),
                        Forms\Components\TextInput::make('organization_name')
                            ->label('Nama Institusi / Organisasi')
                            ->required()
                            ->maxLength(180),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo Aplikasi')
                            ->helperText('PNG/JPG, maksimum 2 MB. Logo digunakan pada branding portal dan panel.')
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(2048)
                            ->image()
                            ->imagePreviewHeight('140')
                            ->getUploadedFileUsing(
                                fn (string $file): ?array => app(BrandMediaService::class)->fileMetadata($file),
                            )
                            ->openable()
                            ->columnSpanFull(),
                        Forms\Components\ColorPicker::make('theme_color')
                            ->label('Warna Tema PWA')
                            ->required()
                            ->default('#2563eb')
                            ->rules(['regex:/^#[0-9A-Fa-f]{6}$/']),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
                Forms\Components\Section::make('Kontak & Informasi Organisasi')
                    ->schema([
                        Forms\Components\TextInput::make('website_url')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(180),
                        Forms\Components\TextInput::make('phone')
                            ->label('Telepon')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->helperText('Format internasional tanpa tanda +, contoh 6281234567890.')
                            ->rule('regex:/^[0-9]{8,20}$/')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('service_hours')
                            ->label('Jam Layanan')
                            ->maxLength(180),
                        Forms\Components\Textarea::make('address')
                            ->label('Alamat')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = AppSetting::query()->first() ?? new AppSetting();

        $settings->fill([
            'portal_name' => trim((string) $data['portal_name']),
            'organization_name' => trim((string) $data['organization_name']),
            'website_url' => filled($data['website_url'] ?? null) ? trim((string) $data['website_url']) : null,
            'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'whatsapp' => filled($data['whatsapp'] ?? null) ? trim((string) $data['whatsapp']) : null,
            'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
            'service_hours' => filled($data['service_hours'] ?? null) ? trim((string) $data['service_hours']) : null,
            'logo_path' => filled($data['logo_path'] ?? null) ? (string) $data['logo_path'] : null,
            'theme_color' => (string) ($data['theme_color'] ?? '#2563eb'),
        ])->save();

        $branding = app(AppBrandingService::class);
        $branding->refresh();
        $branding->applyToConfig();

        Notification::make()
            ->title('White-label aplikasi tersimpan')
            ->body('Branding global sekarang menggunakan konfigurasi dari panel admin pusat.')
            ->success()
            ->send();

        $this->mount();
    }
}
