<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AdmissionQuotaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AdmissionQuota extends Model
{
    /** @use HasFactory<AdmissionQuotaFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $fillable = [
        'registration_opening_id', 'registration_pathway_id', 'capacity',
        'offer_expires_in_hours', 're_registration_due_in_days', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $quota): void {
            $opening = RegistrationOpening::query()->findOrFail($quota->registration_opening_id);
            $pathway = $quota->registration_pathway_id
                ? RegistrationPathway::query()->findOrFail($quota->registration_pathway_id)
                : null;
            if ($pathway && $pathway->unit_id !== $opening->unit_id) {
                throw ValidationException::withMessages(['registration_pathway_id' => 'Jalur pendaftaran harus berada pada unit pembukaan yang sama.']);
            }
            if (auth()->user()?->isTU() && auth()->user()->unit_id !== $opening->unit_id) {
                throw ValidationException::withMessages(['registration_opening_id' => 'Daya tampung hanya dapat dikelola untuk unit Anda.']);
            }

            $usedSeats = Selection::query()
                ->where('decision', 'accepted')
                ->whereHas('registration', function ($query) use ($quota): void {
                    $query->where('registration_opening_id', $quota->registration_opening_id)
                        ->when($quota->registration_pathway_id, fn ($q) => $q->where('registration_pathway_id', $quota->registration_pathway_id), fn ($q) => $q->whereNull('registration_pathway_id'));
                })
                ->where(function ($query): void {
                    $query->whereDoesntHave('registration.admissionOffer')
                        ->orWhereHas('registration.admissionOffer', fn ($offer) => $offer->whereIn('status', AdmissionOffer::ACTIVE_STATUSES));
                })
                ->count();
            if ($quota->capacity < $usedSeats) {
                throw ValidationException::withMessages(['capacity' => 'Daya tampung tidak boleh lebih kecil dari jumlah kursi penerimaan aktif.']);
            }
        });
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(RegistrationOpening::class, 'registration_opening_id');
    }

    public function pathway(): BelongsTo
    {
        return $this->belongsTo(RegistrationPathway::class, 'registration_pathway_id');
    }
}
