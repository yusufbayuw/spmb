<?php

namespace App\Services;

use App\Models\AdmissionOffer;
use App\Models\AdmissionQuota;
use App\Models\Registration;
use App\Models\Selection;
use App\Models\SelectionBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionDecisionService
{
    public function rank(SelectionBatch $batch, User $actor): SelectionBatch
    {
        return DB::transaction(function () use ($batch, $actor): SelectionBatch {
            $lockedBatch = SelectionBatch::query()->with('opening')->lockForUpdate()->findOrFail($batch->id);
            $this->assertUnitAccess($lockedBatch, $actor);
            if ($lockedBatch->status === 'finalized') {
                throw ValidationException::withMessages(['selection_batch' => 'Batch yang telah difinalkan tidak dapat diberi peringkat ulang.']);
            }

            $quota = $this->quotaForBatch($lockedBatch, true);
            $available = $quota ? max(0, $quota->capacity - $this->activeSeatCount($quota)) : PHP_INT_MAX;
            $selections = $this->candidates($lockedBatch)->lockForUpdate()->get();

            if ($selections->isEmpty()) {
                throw ValidationException::withMessages([
                    'selection_batch' => 'Tidak ada kandidat yang memenuhi syarat untuk batch ini.',
                ]);
            }

            $eligibleRank = 0;

            foreach ($selections as $index => $selection) {
                $rank = $index + 1;
                $failedRequiredTest = $this->hasFailedRequiredTest($selection->registration);

                if ($failedRequiredTest) {
                    $recommendation = 'rejected';
                    $waitlistRank = null;
                } else {
                    $eligibleRank++;
                    $recommendation = $eligibleRank <= $available
                        ? 'accepted'
                        : ($lockedBatch->waitlist_limit > 0 && $eligibleRank <= $available + $lockedBatch->waitlist_limit ? 'waiting_list' : 'rejected');
                    $waitlistRank = $recommendation === 'waiting_list' ? $eligibleRank - $available : null;
                }

                $selection->update([
                    'selection_batch_id' => $lockedBatch->id,
                    'rank' => $rank,
                    'waitlist_rank' => $waitlistRank,
                    'system_recommendation' => $recommendation,
                ]);
            }

            $lockedBatch->update(['status' => 'ranked', 'ranked_by' => $actor->id, 'ranked_at' => now()]);
            app(AuditTrail::class)->record(
                'selection.ranking_generated',
                $lockedBatch,
                newValues: ['candidates' => $selections->count(), 'available_seats' => $available],
                actor: $actor,
                unitId: $lockedBatch->opening->unit_id,
                description: 'Peringkat seleksi dibuat untuk batch '.$lockedBatch->name,
            );

            return $lockedBatch->fresh();
        }, 5);
    }

    /** @return Collection<int, Selection> */
    public function finalize(SelectionBatch $batch, User $actor): Collection
    {
        $decisions = DB::transaction(function () use ($batch, $actor): Collection {
            $lockedBatch = SelectionBatch::query()->with('opening')->lockForUpdate()->findOrFail($batch->id);
            $this->assertUnitAccess($lockedBatch, $actor);
            if ($lockedBatch->status !== 'ranked') {
                throw ValidationException::withMessages(['selection_batch' => 'Batch harus diberi peringkat sebelum hasil dapat difinalkan.']);
            }

            $quota = $this->quotaForBatch($lockedBatch, true);
            $usedSeats = $quota ? $this->activeSeatCount($quota) : 0;
            $selections = Selection::query()
                ->with('registration')
                ->where('selection_batch_id', $lockedBatch->id)
                ->orderBy('rank')
                ->lockForUpdate()
                ->get();

            foreach ($selections as $selection) {
                $registration = Registration::query()->lockForUpdate()->findOrFail($selection->registration_id);
                $registration->assertCurrentStage('selection');
                $decision = $selection->decision === 'pending' ? $selection->system_recommendation : $selection->decision;

                if (! in_array($decision, ['accepted', 'waiting_list', 'rejected'], true)) {
                    throw ValidationException::withMessages(['selection' => 'Seluruh kandidat harus memiliki rekomendasi atau keputusan final yang valid.']);
                }
                if ($decision === 'accepted' && $quota && $usedSeats >= $quota->capacity) {
                    throw ValidationException::withMessages(['capacity' => 'Keputusan diterima melebihi daya tampung penerimaan.']);
                }

                $selection->update(['decision' => $decision, 'decided_by' => $actor->id, 'decided_at' => now()]);
                if ($decision === 'accepted') {
                    $usedSeats++;
                }
                $registration->announcement()->firstOrCreate(
                    ['registration_id' => $registration->id],
                    ['status' => 'draft', 'title' => 'Pengumuman Hasil SPMB'],
                );
                $registration->transitionTo('announcement');
            }

            $lockedBatch->update(['status' => 'finalized', 'finalized_by' => $actor->id, 'finalized_at' => now()]);
            app(AuditTrail::class)->record(
                'selection.batch_finalized',
                $lockedBatch,
                newValues: ['candidates' => $selections->count(), 'accepted_seats' => $usedSeats],
                actor: $actor,
                unitId: $lockedBatch->opening->unit_id,
                description: 'Keputusan seleksi difinalkan untuk batch '.$lockedBatch->name,
            );

            return $selections;
        }, 5);

        foreach ($decisions as $selection) {
            app(SpmbNotificationService::class)->selectionDecided($selection->registration->fresh(), $selection->decision);
        }

        return $decisions;
    }

    public function assertCapacityForAcceptance(Registration $registration, ?Selection $selection = null): void
    {
        $quota = $this->quotaForRegistration($registration, true);
        if (! $quota || $selection?->decision === 'accepted') {
            return;
        }
        if ($this->activeSeatCount($quota) >= $quota->capacity) {
            throw ValidationException::withMessages(['capacity' => 'Daya tampung penerimaan telah penuh. Tetapkan peserta sebagai daftar tunggu atau tolak.']);
        }
    }

    public function publishAccepted(Registration $registration): ?AdmissionOffer
    {
        $created = false;
        $offer = DB::transaction(function () use ($registration, &$created): ?AdmissionOffer {
            $lockedRegistration = Registration::query()->with(['selection', 'configuration'])->lockForUpdate()->findOrFail($registration->id);
            if (! $lockedRegistration->postAnnouncementEnabled()) {
                return null;
            }
            if ($lockedRegistration->selection?->decision !== 'accepted') {
                return null;
            }
            $quota = $this->quotaForRegistration($lockedRegistration, true);
            $offer = AdmissionOffer::query()->where('registration_id', $lockedRegistration->id)->lockForUpdate()->first();
            if ($offer) {
                return $offer;
            }

            $offer = AdmissionOffer::create([
                'registration_id' => $lockedRegistration->id,
                'admission_quota_id' => $quota?->id,
                'status' => 'offered',
                'offered_at' => now(),
                'expires_at' => now()->addHours($quota?->offer_expires_in_hours ?? 72),
            ]);
            $created = true;
            $lockedRegistration->transitionTo('admission_offer', [
                'status' => 'accepted',
                'accepted_at' => $lockedRegistration->accepted_at ?: now(),
            ]);
            app(AuditTrail::class)->record('admission.offer_created', $offer, unitId: $lockedRegistration->unit_id, registrationId: $lockedRegistration->id, description: 'Penawaran penerimaan dibuat');

            return $offer->fresh('registration.user');
        }, 5);

        if ($offer && $created) {
            app(SpmbNotificationService::class)->admissionOffer($offer, 'created');
        }

        return $offer;
    }

    public function publishWaitingList(Registration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $lockedRegistration = Registration::query()->with('configuration')->lockForUpdate()->findOrFail($registration->id);

            if (! $lockedRegistration->postAnnouncementEnabled()) {
                return;
            }

            $lockedRegistration->transitionTo('waiting_list', ['status' => 'waiting_list']);
        }, 5);
    }

    public function acceptOffer(AdmissionOffer $offer, User $applicant): AdmissionOffer
    {
        $wasAccepted = false;
        $accepted = DB::transaction(function () use ($offer, $applicant, &$wasAccepted): AdmissionOffer {
            $lockedOffer = AdmissionOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $registration = Registration::query()->lockForUpdate()->findOrFail($lockedOffer->registration_id);
            abort_unless($registration->user_id === $applicant->id, 403);

            if (! $registration->postAnnouncementEnabled()) {
                throw ValidationException::withMessages([
                    'admission_offer' => 'Konfirmasi kursi sedang dinonaktifkan untuk versi pendaftaran ini.',
                ]);
            }

            if ($lockedOffer->status === 'accepted') {
                return $lockedOffer;
            }
            if ($lockedOffer->status !== 'offered') {
                throw ValidationException::withMessages(['admission_offer' => 'Penawaran ini tidak lagi dapat diterima.']);
            }
            if ($lockedOffer->expires_at->isPast()) {
                $this->expireLockedOffer($lockedOffer, $registration);
                throw ValidationException::withMessages(['admission_offer' => 'Batas waktu penawaran telah berakhir.']);
            }

            $lockedOffer->update(['status' => 'accepted', 'accepted_at' => now()]);
            $registration->transitionTo('re_registration', ['status' => 'confirmed']);
            app(ReRegistrationService::class)->initialize($registration);
            if ($registration->reRegistrationComplete()) {
                $registration->transitionTo('enrollment', ['re_registration_completed_at' => now()]);
            }
            app(AuditTrail::class)->record('admission.offer_accepted', $lockedOffer, actor: $applicant, unitId: $registration->unit_id, registrationId: $registration->id, description: 'Penawaran penerimaan diterima pendaftar');
            $wasAccepted = true;

            return $lockedOffer->fresh('registration.user');
        }, 5);

        if ($wasAccepted) {
            app(SpmbNotificationService::class)->admissionOffer($accepted, 'accepted');
            app(SpmbNotificationService::class)->reRegistrationStarted($accepted->registration);
        }

        return $accepted;
    }

    public function declineOffer(AdmissionOffer $offer, User $applicant, string $reason): AdmissionOffer
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan penawaran wajib diisi.']);
        }

        $wasDeclined = false;
        $declined = DB::transaction(function () use ($offer, $applicant, $reason, &$wasDeclined): AdmissionOffer {
            $lockedOffer = AdmissionOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $registration = Registration::query()->lockForUpdate()->findOrFail($lockedOffer->registration_id);
            abort_unless($registration->user_id === $applicant->id, 403);

            if (! $registration->postAnnouncementEnabled()) {
                throw ValidationException::withMessages([
                    'admission_offer' => 'Konfirmasi kursi sedang dinonaktifkan untuk versi pendaftaran ini.',
                ]);
            }

            if ($lockedOffer->status === 'declined') {
                return $lockedOffer;
            }
            if ($lockedOffer->status !== 'offered') {
                throw ValidationException::withMessages(['admission_offer' => 'Penawaran ini tidak lagi dapat ditolak.']);
            }

            $lockedOffer->update(['status' => 'declined', 'declined_at' => now(), 'decline_reason' => trim($reason)]);
            $registration->transitionTo('completed', ['status' => 'withdrawn']);
            app(AuditTrail::class)->record('admission.offer_declined', $lockedOffer, newValues: ['reason' => trim($reason)], actor: $applicant, unitId: $registration->unit_id, registrationId: $registration->id, description: 'Penawaran penerimaan ditolak pendaftar; kursi dilepas');
            $wasDeclined = true;

            return $lockedOffer->fresh('registration.user');
        }, 5);

        if ($wasDeclined) {
            app(SpmbNotificationService::class)->admissionOffer($declined, 'declined');
            $this->promoteForQuota($declined->quota);
        }

        return $declined;
    }

    public function expireOffer(AdmissionOffer $offer): bool
    {
        $expired = DB::transaction(function () use ($offer): ?AdmissionOffer {
            $lockedOffer = AdmissionOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($lockedOffer->status !== 'offered' || $lockedOffer->expires_at->isFuture()) {
                return null;
            }
            $registration = Registration::query()->lockForUpdate()->findOrFail($lockedOffer->registration_id);
            $this->expireLockedOffer($lockedOffer, $registration);

            return $lockedOffer->fresh('registration.user');
        }, 5);
        if (! $expired) {
            return false;
        }

        app(SpmbNotificationService::class)->admissionOffer($expired, 'expired');
        $this->promoteForQuota($expired->quota);

        return true;
    }

    public function expireDueOffers(): int
    {
        $expired = 0;
        foreach (AdmissionOffer::query()->where('status', 'offered')->where('expires_at', '<=', now())->pluck('id') as $id) {
            $expired += $this->expireOffer(AdmissionOffer::query()->findOrFail($id)) ? 1 : 0;
        }

        return $expired;
    }

    public function sendOfferReminders(): int
    {
        $sent = 0;
        $offers = AdmissionOffer::query()
            ->where('status', 'offered')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDay())
            ->where(fn (Builder $query) => $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDay()))
            ->pluck('id');

        foreach ($offers as $id) {
            $offer = DB::transaction(function () use ($id): ?AdmissionOffer {
                $lockedOffer = AdmissionOffer::query()->lockForUpdate()->findOrFail($id);
                if ($lockedOffer->status !== 'offered' || $lockedOffer->expires_at->isPast() || ($lockedOffer->last_reminded_at && $lockedOffer->last_reminded_at->greaterThan(now()->subDay()))) {
                    return null;
                }
                $lockedOffer->update(['last_reminded_at' => now()]);

                return $lockedOffer->fresh('registration.user');
            }, 5);
            if ($offer) {
                app(SpmbNotificationService::class)->admissionOffer($offer, 'reminder');
                $sent++;
            }
        }

        return $sent;
    }

    public function promoteForQuota(?AdmissionQuota $quota): ?AdmissionOffer
    {
        if (! $quota) {
            return null;
        }

        $offer = DB::transaction(function () use ($quota): ?AdmissionOffer {
            $lockedQuota = AdmissionQuota::query()->lockForUpdate()->findOrFail($quota->id);
            if (! $lockedQuota->is_active || $this->activeSeatCount($lockedQuota) >= $lockedQuota->capacity) {
                return null;
            }
            $selection = Selection::query()
                ->with('registration')
                ->where('decision', 'waiting_list')
                ->whereNotNull('waitlist_rank')
                ->whereHas('registration', function (Builder $query) use ($lockedQuota): void {
                    $query->where('registration_opening_id', $lockedQuota->registration_opening_id)
                        ->where('current_stage', 'waiting_list')
                        ->when($lockedQuota->registration_pathway_id, fn (Builder $q): Builder => $q->where('registration_pathway_id', $lockedQuota->registration_pathway_id), fn (Builder $q): Builder => $q->whereNull('registration_pathway_id'));
                })
                ->orderBy('waitlist_rank')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if (! $selection) {
                return null;
            }

            $registration = Registration::query()->lockForUpdate()->findOrFail($selection->registration_id);
            $selection->update(['decision' => 'accepted', 'decided_at' => now()]);
            $offer = AdmissionOffer::create([
                'registration_id' => $registration->id,
                'admission_quota_id' => $lockedQuota->id,
                'status' => 'offered',
                'offered_at' => now(),
                'expires_at' => now()->addHours($lockedQuota->offer_expires_in_hours),
            ]);
            $registration->transitionTo('admission_offer', ['status' => 'accepted']);
            app(AuditTrail::class)->record('waitlist.promoted', $offer, newValues: ['waitlist_rank' => $selection->waitlist_rank], unitId: $registration->unit_id, registrationId: $registration->id, description: 'Pendaftar dipromosikan dari daftar tunggu');

            return $offer->fresh('registration.user');
        }, 5);

        if ($offer) {
            app(SpmbNotificationService::class)->waitlistPromoted($offer);
        }

        return $offer;
    }

    private function expireLockedOffer(AdmissionOffer $offer, Registration $registration): void
    {
        $offer->update(['status' => 'expired', 'expired_at' => now()]);
        $registration->transitionTo('completed', ['status' => 'expired']);
        app(AuditTrail::class)->record('admission.offer_expired', $offer, unitId: $registration->unit_id, registrationId: $registration->id, description: 'Penawaran penerimaan berakhir; kursi dilepas');
    }

    private function quotaForRegistration(Registration $registration, bool $lock): ?AdmissionQuota
    {
        $query = AdmissionQuota::query()->where('registration_opening_id', $registration->registration_opening_id)->where('is_active', true);
        if ($lock) {
            $query->lockForUpdate();
        }
        if ($registration->registration_pathway_id) {
            return $query->where(function (Builder $scope) use ($registration): void {
                $scope->where('registration_pathway_id', $registration->registration_pathway_id)->orWhereNull('registration_pathway_id');
            })->orderByRaw('registration_pathway_id is null')->first();
        }

        return $query->whereNull('registration_pathway_id')->first();
    }

    private function quotaForBatch(SelectionBatch $batch, bool $lock): ?AdmissionQuota
    {
        return $this->quotaForRegistration(new Registration([
            'registration_opening_id' => $batch->registration_opening_id,
            'registration_pathway_id' => $batch->registration_pathway_id,
        ]), $lock);
    }

    private function activeSeatCount(AdmissionQuota $quota): int
    {
        return Selection::query()
            ->where('decision', 'accepted')
            ->whereHas('registration', function (Builder $query) use ($quota): void {
                $query->where('registration_opening_id', $quota->registration_opening_id)
                    ->where('current_stage', '!=', 'selection')
                    ->when($quota->registration_pathway_id, fn (Builder $q): Builder => $q->where('registration_pathway_id', $quota->registration_pathway_id), fn (Builder $q): Builder => $q->whereNull('registration_pathway_id'));
            })
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('registration.admissionOffer')
                    ->orWhereHas('registration.admissionOffer', fn (Builder $offer): Builder => $offer->whereIn('status', AdmissionOffer::ACTIVE_STATUSES));
            })
            ->count();
    }

    private function candidates(SelectionBatch $batch): Builder
    {
        return Selection::query()
            ->with(['registration.configuration', 'registration.opening', 'registration.testResults'])
            ->where(function (Builder $query) use ($batch): void {
                $query->whereNull('selection_batch_id')
                    ->orWhere('selection_batch_id', $batch->id);
            })
            ->whereHas('registration', function (Builder $query) use ($batch): void {
                $query->where('registration_opening_id', $batch->registration_opening_id)
                    ->where('current_stage', 'selection')
                    ->where(function (Builder $mode): void {
                        $mode->whereDoesntHave('configuration')
                            ->orWhereHas('configuration', fn (Builder $configuration) => $configuration
                                ->whereIn('selection_mode', ['batch', 'flexible']));
                    })
                    ->when($batch->registration_pathway_id, fn (Builder $q): Builder => $q->where('registration_pathway_id', $batch->registration_pathway_id), fn (Builder $q): Builder => $q->whereNull('registration_pathway_id'));
            })
            ->orderByRaw('case when final_score is null then 1 else 0 end')
            ->orderByDesc('final_score')
            ->orderBy('registration_id');
    }

    private function hasFailedRequiredTest(Registration $registration): bool
    {
        $requiredTestIds = collect($registration->configuredTests())
            ->where('is_required', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        if ($requiredTestIds->isEmpty()) {
            return false;
        }

        return $registration->testResults
            ->whereIn('admission_test_id', $requiredTestIds->all())
            ->contains(fn ($result): bool => $result->result === 'fail');
    }

    private function assertUnitAccess(SelectionBatch $batch, User $actor): void
    {
        abort_if($actor->isTU() && $actor->unit_id !== $batch->opening->unit_id, 403);
    }
}
