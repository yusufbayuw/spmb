<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailDeliveryAttempt extends Model
{
    public const TYPES = [
        'verification' => 'Verifikasi Email',
        'virtual_account' => 'Informasi Virtual Account',
        'announcement' => 'Pengumuman Penerimaan',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
