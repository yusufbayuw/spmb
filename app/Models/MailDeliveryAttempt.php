<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailDeliveryAttempt extends Model
{
    public const TYPES = [
        'verification' => 'Verifikasi Email',
        'virtual_account' => 'Informasi Virtual Account',
        'announcement' => 'Pengumuman Penerimaan',
        'password_reset' => 'Pemulihan Kata Sandi',
        'revision_reminder' => 'Pengingat Revisi Berkas/Data',
        'payment_reminder' => 'Pengingat Unggah Bukti Pembayaran',
        'test_reminder' => 'Pengingat Jadwal atau Konfirmasi Tes',
        'offer_reminder' => 'Pengingat Konfirmasi Penerimaan',
        'email_change' => 'Konfirmasi Koreksi Email',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_attempted_at' => 'datetime',
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
