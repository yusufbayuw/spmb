<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSession extends Model
{
    use HasPublicUuid;

    protected $fillable = ['admission_test_id', 'starts_at', 'ends_at', 'booking_closes_at', 'location', 'instructions', 'capacity', 'status'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'booking_closes_at' => 'datetime', 'capacity' => 'integer'];

    public function admissionTest(): BelongsTo
    {
        return $this->belongsTo(AdmissionTest::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TestBooking::class);
    }

    public function label(): string
    {
        return $this->starts_at->format('d M Y H:i').' – '.$this->ends_at->format('H:i').' · '.$this->location;
    }
}
