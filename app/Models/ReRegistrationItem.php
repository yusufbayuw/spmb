<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ReRegistrationItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReRegistrationItem extends Model
{
    /** @use HasFactory<ReRegistrationItemFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $fillable = [
        'registration_id', 'requirement_key', 'label', 'type', 'is_required', 'status', 'value',
        'file_path', 'original_name', 'mime_type', 'verified_by', 'verified_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'value' => 'array', 'verified_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
