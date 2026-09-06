<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'superseded_at', 'requirement_key', 'attachment_index', 'registration_id',
        'type',
        'file_path',
        'original_name',
        'file_type',
        'mime_type',
        'file_size',
        'sha256',
        'malware_scan_status',
        'security_scanned_at',
        'is_verified',
        'rejection_reason',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'security_scanned_at' => 'datetime',
    ];

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
