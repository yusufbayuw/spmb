<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Models\Registration;
use App\Models\UnitConfiguration;
use App\Services\UnitConfigurationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class UnitRegistrationFormPreview extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.admin.pages.unit-registration-form-preview';

    protected static ?string $title = 'Pratinjau Form Pendaftar';

    public array $previewData = [];

    public ?string $token = null;

    public array $snapshot = [];

    public static function canAccess(): bool
    {
        return UnitRegistrationSettings::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $token = request()->query('token');
        abort_unless(is_string($token) && preg_match('/^[a-f0-9-]{36}$/', $token), 404);
        $data = Cache::get('spmb:form-preview:'.$token);
        abort_unless(is_array($data) && ($data['user_id'] ?? null) === auth()->id(), 403);
        $configuration = UnitConfiguration::query()->where('uuid', $data['configuration_uuid'] ?? null)->firstOrFail();
        abort_unless((int) $configuration->unit_id === (int) ($data['unit_id'] ?? 0), 403);
        app(UnitConfigurationService::class)->authorize(auth()->user(), (int) $configuration->unit_id);

        $this->token = $token;
        $this->snapshot = $data;
        $unit = $configuration->unit;
        $this->previewForm->fill([
            'unit_uuid' => $unit->uuid,
            'unit_configuration_uuid' => $configuration->uuid,
            'registration_opening_uuid' => $unit->registrationOpenings()->value('uuid'),
            'registration_pathway_uuid' => $unit->registrationPathways()->where('is_active', true)->whereNull('archived_at')->value('uuid'),
            'registrant_type' => 'parent',
        ]);
    }

    public function previewConfiguration(): ?UnitConfiguration
    {
        if (! $this->token || ! is_array($this->snapshot['data'] ?? null)) {
            return null;
        }
        $stored = UnitConfiguration::query()->where('uuid', $this->snapshot['configuration_uuid'] ?? null)->first();
        if (! $stored || (int) $stored->unit_id !== (int) ($this->snapshot['unit_id'] ?? 0)
            || (int) ($this->snapshot['user_id'] ?? 0) !== (int) auth()->id()) {
            return null;
        }
        app(UnitConfigurationService::class)->authorize(auth()->user(), (int) $stored->unit_id);
        $preview = new UnitConfiguration();
        $preview->forceFill($this->snapshot['data']);
        $preview->status = 'draft';
        $preview->unit_id = $stored->unit_id;
        return $preview;
    }

    public function previewForm(Form $form): Form
    {
        return RegistrationResource::form($form)
            ->model(Registration::class)->statePath('previewData')->disabled();
    }
}
