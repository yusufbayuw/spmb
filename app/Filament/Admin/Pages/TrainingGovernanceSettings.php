<?php

namespace App\Filament\Admin\Pages;

use App\Models\AppSetting;
use App\Services\AppBrandingService;
use App\Services\TrainingGovernanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TrainingGovernanceSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Governance Training';
    protected static ?string $title = 'Governance Training & Sertifikasi';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 24;
    protected static string $view = 'filament.admin.pages.training-governance-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()->isAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = AppSetting::query()->first();

        $this->form->fill([
            'training_enforce_sequence' => $settings?->training_enforce_sequence ?? true,
            'training_require_module_mastery' => $settings?->training_require_module_mastery ?? true,
            'certification_enforcement_mode' => $settings?->certification_enforcement_mode ?? 'off',
            'certification_expiry_reminder_days' => $settings?->certification_expiry_reminder_days ?? [30, 14, 7, 1],
            'certificate_artifact_enabled' => $settings?->certificate_artifact_enabled ?? true,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Training')
                ->schema([
                    Forms\Components\Toggle::make('training_enforce_sequence')
                        ->label('Wajib mengikuti urutan modul & lesson')
                        ->helperText('Jika aktif, peserta tidak dapat melewati lesson atau modul prasyarat.')
                        ->default(true),
                    Forms\Components\Toggle::make('training_require_module_mastery')
                        ->label('Checkpoint modul wajib untuk menyelesaikan training')
                        ->helperText('Training baru selesai setelah lesson wajib dan checkpoint modul wajib lulus.')
                        ->default(true),
                ]),
            Forms\Components\Section::make('Enforcement Sertifikasi')
                ->schema([
                    Forms\Components\Select::make('certification_enforcement_mode')
                        ->label('Mode Enforcement')
                        ->options(TrainingGovernanceService::ENFORCEMENT_MODES)
                        ->required()
                        ->default('off'),
                    Forms\Components\TagsInput::make('certification_expiry_reminder_days')
                        ->label('Reminder sebelum kedaluwarsa (hari)')
                        ->placeholder('30')
                        ->helperText('Contoh: 30, 14, 7, 1.')
                        ->suggestions(['30', '14', '7', '1']),
                ]),
            Forms\Components\Section::make('Artifact Sertifikat')
                ->schema([
                    Forms\Components\Toggle::make('certificate_artifact_enabled')
                        ->label('Buat artifact PDF immutable')
                        ->helperText('PDF disimpan pada storage private dengan SHA-256 dan integrity signature.')
                        ->default(true),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $branding = app(AppBrandingService::class)->effective();

        $settings = AppSetting::query()->first() ?? new AppSetting([
            'portal_name' => $branding['name'],
            'organization_name' => $branding['foundation_name'],
        ]);

        $days = collect($data['certification_expiry_reminder_days'] ?? [])
            ->map(fn (mixed $day): int => max(1, (int) $day))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $settings->fill([
            'training_enforce_sequence' => (bool) $data['training_enforce_sequence'],
            'training_require_module_mastery' => (bool) $data['training_require_module_mastery'],
            'certification_enforcement_mode' => (string) $data['certification_enforcement_mode'],
            'certification_expiry_reminder_days' => $days ?: [30, 14, 7, 1],
            'certificate_artifact_enabled' => (bool) $data['certificate_artifact_enabled'],
        ])->save();

        Notification::make()
            ->title('Governance training & sertifikasi tersimpan')
            ->success()
            ->send();

        $this->mount();
    }
}
