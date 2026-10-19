<?php

namespace App\Services;

use App\Filament\Applicant\Pages\RegistrationStatus;
use App\Filament\Applicant\Resources\RegistrationResource;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PausedOpeningApplicantRedirect
{
    /**
     * Only an authenticated applicant who already owns a registration in this
     * opening may follow an old application link while new intake is paused.
     */
    public function url(RegistrationOpening $opening, ?User $user): ?string
    {
        if (! $opening->isPaused()
            || ! $user?->is_active
            || ! $user->isUser()
            || ! $user->hasVerifiedEmail()
            || ! ($opening->unit?->isOperational() ?? false)) {
            return null;
        }

        $registrations = Registration::query()
            ->where('user_id', $user->id)
            ->where('registration_opening_id', $opening->id)
            ->whereHas('unit', fn (Builder $unitQuery): Builder => $unitQuery->operational())
            ->latest('id')
            ->limit(2)
            ->get(['uuid']);

        if ($registrations->isEmpty()) {
            return null;
        }

        if ($registrations->count() > 1) {
            return RegistrationResource::getUrl('index', panel: 'pendaftar');
        }

        return RegistrationStatus::getUrl(
            ['registration' => $registrations->first()->uuid],
            panel: 'pendaftar',
        );
    }
}
