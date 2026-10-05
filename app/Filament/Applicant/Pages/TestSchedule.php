<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Registration;
use App\Models\TestBooking;
use App\Models\TestSession;
use App\Services\TestBookingService;
use App\Services\TestCardEligibilityService;
use App\Services\TestScheduleConfirmationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TestSchedule extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Pilih Jadwal Tes';

    protected static ?string $slug = 'jadwal-tes/{registration}';

    protected static string $view = 'filament.applicant.pages.test-schedule';

    public Registration $registrationRecord;

    public bool $scheduleConfirmed = false;

    public function mount(int|string $registration): void
    {
        abort_unless(Str::isUuid($registration), 404);

        $this->registrationRecord = Registration::query()
            ->where('user_id', auth()->id())
            ->whereHas('unit', fn ($unitQuery) => $unitQuery->operational())
            ->where('uuid', $registration)
            ->firstOrFail();

        abort_unless(
            $this->registrationRecord->isOperational()
            && $this->registrationRecord->current_stage === 'tests',
            403,
        );

        $this->syncScheduleConfirmationState();
    }

    public function sessions(int $testId): Collection
    {
        return TestSession::query()
            ->where('admission_test_id', $testId)
            ->where('status', 'active')
            ->where('booking_closes_at', '>', now())
            ->where('starts_at', '>', now())
            ->withCount('bookings')
            ->orderBy('starts_at')
            ->get();
    }

    public function booking(int $testId): ?TestBooking
    {
        return TestBooking::with('session')
            ->where('registration_id', $this->registrationRecord->id)
            ->where('admission_test_id', $testId)
            ->first();
    }

    /**
     * @return array{required:int,booked:int,missing:int,complete:bool,confirmed:bool}
     */
    public function scheduleProgress(): array
    {
        return app(TestScheduleConfirmationService::class)->progress($this->registrationRecord);
    }

    public function canConfirmSchedule(): bool
    {
        return $this->scheduleProgress()['complete'] && ! $this->scheduleConfirmed;
    }

    public function canPrintTestCard(): bool
    {
        return app(TestCardEligibilityService::class)->canPrint($this->registrationRecord);
    }

    public function choose(string $sessionId): void
    {
        app(TestBookingService::class)->book(
            $this->registrationRecord,
            TestSession::where('uuid', $sessionId)->firstOrFail(),
            auth()->user(),
        );

        $this->registrationRecord->refresh();
        $this->syncScheduleConfirmationState();

        Notification::make()
            ->title('Pilihan jadwal tersimpan')
            ->success()
            ->send();
    }

    public function confirmSchedule(): void
    {
        $this->registrationRecord = app(TestScheduleConfirmationService::class)
            ->confirm($this->registrationRecord);

        $this->syncScheduleConfirmationState();

        Notification::make()
            ->title('Jadwal tes dikonfirmasi')
            ->body('Seluruh tes wajib sudah memiliki jadwal. Simpan kartu tes dan ikuti seluruh rangkaian tes sesuai jadwal.')
            ->success()
            ->send();
    }

    private function syncScheduleConfirmationState(): void
    {
        $this->scheduleConfirmed = app(TestScheduleConfirmationService::class)
            ->isConfirmed($this->registrationRecord);
    }
}
