<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Registration;
use App\Models\TestBooking;
use App\Models\TestSession;
use App\Services\TestBookingService;
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

    public function mount(int|string $registration): void
    {
        abort_unless(Str::isUuid($registration), 404);
        $this->registrationRecord = Registration::where('user_id', auth()->id())->where('uuid', $registration)->firstOrFail();
        abort_unless($this->registrationRecord->isOperational() && $this->registrationRecord->current_stage === 'tests', 403);
    }

    public function sessions(int $testId): Collection
    {
        return TestSession::query()->where('admission_test_id', $testId)->where('status', 'active')->where('booking_closes_at', '>', now())->where('starts_at', '>', now())->withCount('bookings')->orderBy('starts_at')->get();
    }

    public function booking(int $testId): ?TestBooking
    {
        return TestBooking::with('session')->where('registration_id', $this->registrationRecord->id)->where('admission_test_id', $testId)->first();
    }

    public function hasSelectedSession(): bool
    {
        return TestBooking::query()
            ->where('registration_id', $this->registrationRecord->id)
            ->whereNotNull('test_session_id')
            ->exists();
    }

    public function choose(string $sessionId): void
    {
        app(TestBookingService::class)->book($this->registrationRecord, TestSession::where('uuid', $sessionId)->firstOrFail(), auth()->user());
        Notification::make()->title('Pilihan jadwal tersimpan')->success()->send();
    }
}
