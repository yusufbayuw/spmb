<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestBooking extends Model
{
    use HasPublicUuid;

    protected $fillable = ['registration_id', 'admission_test_id', 'test_session_id', 'revision'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TestSession::class, 'test_session_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function admissionTest(): BelongsTo
    {
        return $this->belongsTo(AdmissionTest::class);
    }
}
