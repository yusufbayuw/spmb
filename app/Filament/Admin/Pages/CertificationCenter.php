<?php

namespace App\Filament\Admin\Pages;

use App\Models\CertificationAttempt;
use App\Models\CertificationProgram;
use App\Services\CertificationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class CertificationCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Sertifikasi Saya';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Pusat Sertifikasi SPMB';
    protected static string $view = 'filament.admin.pages.certification-center';

    public ?string $attemptUuid = null;
    public array $answers = [];
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
        $this->attemptUuid = CertificationAttempt::query()
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->latest('id')
            ->value('uuid');
    }

    public function programs(): Collection
    {
        return app(CertificationService::class)->availablePrograms(auth()->user());
    }

    public function eligible(CertificationProgram $program): bool
    {
        return app(CertificationService::class)->eligible(auth()->user(), $program);
    }

    public function startExam(string $programUuid): void
    {
        $program = CertificationProgram::query()->where('uuid', $programUuid)->firstOrFail();
        $attempt = app(CertificationService::class)->start(auth()->user(), $program);

        $this->attemptUuid = $attempt->uuid;
        $this->answers = [];
        $this->lastResult = [];
    }

    public function activeAttempt(): ?CertificationAttempt
    {
        if (! $this->attemptUuid) {
            return null;
        }

        return CertificationAttempt::query()
            ->where('uuid', $this->attemptUuid)
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->with([
                'program.questions' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->first();
    }

    public function submitExam(): void
    {
        $attempt = CertificationAttempt::query()
            ->where('uuid', $this->attemptUuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $result = app(CertificationService::class)->submit($attempt, auth()->user(), $this->answers);
        $certificate = $result->certification;

        $this->lastResult = [
            'status' => $result->status,
            'score' => (float) $result->score,
            'passing_score' => $result->program->passing_score,
            'certificate_uuid' => $certificate?->uuid,
            'certificate_number' => $certificate?->certificate_number,
        ];

        $this->attemptUuid = null;
        $this->answers = [];

        Notification::make()
            ->title($result->status === 'passed' ? 'Selamat, Anda lulus' : 'Ujian belum lulus')
            ->body('Nilai akhir: '.number_format((float) $result->score, 2, ',', '.'))
            ->color($result->status === 'passed' ? 'success' : 'danger')
            ->send();
    }
}
