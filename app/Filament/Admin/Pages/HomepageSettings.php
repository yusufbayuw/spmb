<?php

namespace App\Filament\Admin\Pages;

use App\Models\AppSetting;
use App\Services\AppBrandingService;
use App\Services\HomepageSectionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class HomepageSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Pengaturan Halaman Utama';
    protected static ?string $title = 'Pengaturan Halaman Utama';
    protected static ?string $navigationGroup = 'Informasi Publik';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.admin.pages.homepage-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active
            && auth()->user()->isAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(['blocks' => app(HomepageSectionService::class)->visibility()]);
    }

    public function form(Form $form): Form
    {
        $toggles = [];

        foreach (HomepageSectionService::LABELS as $key => $label) {
            $toggles[] = Forms\Components\Toggle::make('blocks.'.$key)
                ->label($label)
                ->helperText(HomepageSectionService::DESCRIPTIONS[$key])
                ->default(true);
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Visibilitas Blok Homepage')
                    ->description('Hanya mengubah tampilan halaman /. Semua blok aktif secara default. Route dan proses pendaftaran tetap tersedia.')
                    ->schema($toggles)
                    ->columns(['default' => 1, 'lg' => 2]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $branding = app(AppBrandingService::class);
        $settings = AppSetting::query()->first();

        if (! $settings) {
            $effective = $branding->effective();
            $settings = new AppSetting([
                'portal_name' => app(\App\Services\AdmissionTerminology::class)->portalName(),
                'organization_name' => (string) ($effective['foundation_name'] ?: 'Institusi Pendidikan'),
                'theme_color' => (string) ($effective['theme_color'] ?: '#2563eb'),
            ]);
        }

        $settings->homepage_blocks = HomepageSectionService::normalize($data['blocks'] ?? null);
        $settings->save();

        // The first AppSetting row may also affect the branding singleton.
        $branding->refresh();
        $branding->applyToConfig();

        Notification::make()
            ->title('Pengaturan halaman utama tersimpan')
            ->body('Blok yang dinonaktifkan disembunyikan dari homepage, tanpa mengubah proses pendaftaran.')
            ->success()
            ->send();
    }
}
