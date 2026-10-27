<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationManualEmail extends Model
{
    protected $fillable = ['mail_delivery_attempt_id', 'subject', 'body'];

    // Message content is private: it must not appear in audit-log payloads.
    protected $casts = ['body' => 'encrypted'];

    public function deliveryAttempt(): BelongsTo
    {
        return $this->belongsTo(MailDeliveryAttempt::class, 'mail_delivery_attempt_id');
    }
}
