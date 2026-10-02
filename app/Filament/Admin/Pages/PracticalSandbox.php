<?php

namespace App\Filament\Admin\Pages;

use App\Models\PracticalRun;
use App\Models\PracticalScenario;
use App\Models\PracticalScenarioAction;
use App\Models\UserCertification;
use App\Services\PracticalSandboxService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class PracticalSandbox extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Ujian Praktik';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Practical Sandbox SPMB';
    protected static string $view = 'filament.admin.pages.practical-sandbox';

    public ?string $runUuid = null;
    public array $lastResult = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active
            && $user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])
            && $user->hasOperationalUnitAccess();
    }

    public function mount(): void
    {
        $this->runUuid = PracticalRun::query()
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->latest('id')
            ->value('uuid');
    }

    public function scenarios(): Collection
    {
        return app(PracticalSandboxService::class)->availableScenarios(auth()->user());
    }

    public function canStart(PracticalScenario $scenario): bool
    {
        return app(PracticalSandboxService::class)->canStart(auth()->user(), $scenario);
    }

    public function startScenario(string $scenarioUuid): void
    {
        $scenario = PracticalScenario::query()
            ->where('uuid', $scenarioUuid)
            ->with(['program', 'records'])
            ->firstOrFail();

        $run = app(PracticalSandboxService::class)->start(auth()->user(), $scenario);

        $this->runUuid = $run->uuid;
        $this->lastResult = [];

        Notification::make()
            ->title('Practical sandbox dimulai')
            ->body('Semua tindakan pada halaman ini hanya mengubah data sandbox.')
            ->success()
            ->send();
    }

    public function activeRun(): ?PracticalRun
    {
        if (! $this->runUuid) {
            return null;
        }

        return PracticalRun::query()
            ->where('uuid', $this->runUuid)
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->with([
                'scenario.program',
                'scenario.actions' => fn ($query) => $query->where('is_active', true),
                'sandboxRecords',
                'events' => fn ($query) => $query->latest('id'),
            ])
            ->first();
    }

    public function performAction(string $actionUuid): void
    {
        $run = $this->activeRun();

        abort_unless($run, 404);

        $action = PracticalScenarioAction::query()
            ->where('uuid', $actionUuid)
            ->firstOrFail();

        app(PracticalSandboxService::class)->performAction(auth()->user(), $run, $action);

        Notification::make()
            ->title('Aksi sandbox diterapkan')
            ->body($action->label)
            ->success()
            ->send();
    }

    public function submitRun(): void
    {
        $run = $this->activeRun();

        abort_unless($run, 404);

        $result = app(PracticalSandboxService::class)->submit(auth()->user(), $run);
        $certificate = UserCertification::query()
            ->where('certification_attempt_id', $result->certification_attempt_id)
            ->first();

        $this->lastResult = [
            'passed' => (bool) $result->passed,
            'score' => (float) $result->score,
            'scenario' => $result->scenario->name,
            'program' => $result->scenario->program->name,
            'certificate_uuid' => $certificate?->uuid,
            'certificate_number' => $certificate?->certificate_number,
            'assertions' => $result->results
                ->map(fn ($item): array => [
                    'name' => $item->assertion->name,
                    'passed' => $item->passed,
                    'critical' => $item->assertion->is_critical,
                    'feedback' => $item->feedback,
                ])
                ->values()
                ->all(),
        ];

        $this->runUuid = null;

        Notification::make()
            ->title($result->passed ? 'Ujian praktik lulus' : 'Ujian praktik belum lulus')
            ->body('Nilai practical: '.number_format((float) $result->score, 2, ',', '.'))
            ->color($result->passed ? 'success' : 'danger')
            ->send();
    }

    public function passedRunFor(PracticalScenario $scenario): ?PracticalRun
    {
        $theory = app(\App\Services\CertificationService::class)
            ->latestPassedTheory(auth()->user(), $scenario->program);

        if (! $theory) {
            return null;
        }

        return PracticalRun::query()
            ->where('practical_scenario_id', $scenario->id)
            ->where('certification_attempt_id', $theory->id)
            ->where('user_id', auth()->id())
            ->where('status', 'passed')
            ->where('passed', true)
            ->latest('submitted_at')
            ->first();
    }

    public function safeColor(string $color): string
    {
        return in_array($color, ['primary', 'gray', 'success', 'warning', 'danger'], true)
            ? $color
            : 'gray';
    }
}
