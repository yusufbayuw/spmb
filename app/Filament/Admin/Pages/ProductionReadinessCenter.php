<?php

namespace App\Filament\Admin\Pages;

use App\Services\ProductionReadinessService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProductionReadinessCenter extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Sistem & Akses';
    protected static ?string $navigationLabel = 'Audit Siap Production';
    protected static ?string $title = 'Production Readiness Center';
    protected static ?int $navigationSort = 7;
    protected static string $view = 'filament.admin.pages.production-readiness-center';

    public array $auditForm = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active && (auth()->user()?->isAdmin() ?? false);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['status' => 'pending']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('check_id')->label('Pemeriksaan manual')->required()
                ->options(collect(ProductionReadinessService::MANUAL)
                    ->mapWithKeys(fn (array $item, string $key): array => [$key => $item[0]])->all())
                ->searchable(),
            Select::make('status')->label('Hasil pemeriksaan')->options([
                'pending' => 'Belum diverifikasi', 'pass' => 'Lulus (bukti wajib)', 'fail' => 'Gagal',
            ])->required(),
            Textarea::make('evidence')->label('Bukti audit / referensi tiket / hasil uji')
                ->rows(4)->maxLength(2000)
                ->helperText('Jangan masukkan password, token, NIK, kunci, atau data pribadi. Tulis lokasi bukti dan hasil pengujian.'),
        ])->statePath('auditForm');
    }

    public function saveAttestation(): void
    {
        abort_unless(static::canAccess(), 403);
        $input = $this->form->getState();
        app(ProductionReadinessService::class)->attest(
            auth()->user(), $input['check_id'], $input['status'], $input['evidence'] ?? ''
        );
        Notification::make()->title('Bukti audit dan penanggung jawab dicatat')->success()->send();
        $this->form->fill(['status' => 'pending']);
    }

    public function saveSnapshot(): void
    {
        abort_unless(static::canAccess(), 403);
        $report = app(ProductionReadinessService::class)->snapshot(auth()->user());
        Notification::make()
            ->title($report['status'] === 'ready_for_review' ? 'Seluruh pemeriksaan lulus; menunggu persetujuan rilis' : 'Snapshot tersimpan: belum siap production')
            ->body('Snapshot historis tidak mengubah konfigurasi, queue, maupun proses bisnis.')
            ->color($report['status'] === 'ready_for_review' ? 'success' : 'warning')
            ->send();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $service = app(ProductionReadinessService::class);
        $report = $service->report();
        $history = collect();

        try {
            $history = DB::table('production_readiness_snapshots')
                ->where('deployment_id', $service->deploymentId())
                ->latest('id')->limit(10)->get();
        } catch (\Throwable) {
            // Migration state is shown as a failed automatic check.
        }

        return ['report' => $report, 'history' => $history,
            'groups' => collect($report['checks'])->groupBy('category')];
    }
}
