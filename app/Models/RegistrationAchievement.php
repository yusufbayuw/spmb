<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RegistrationAchievement extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'registration_id',
        'title',
        'level',
        'year',
        'organizer',
        'description',
        'certificate_path',
        'certificate_original_name',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function hasCertificate(): bool
    {
        return filled($this->certificate_path);
    }

    public function certificateDisplayName(): string
    {
        $extension = strtolower(pathinfo(
            (string) ($this->certificate_original_name ?: $this->certificate_path),
            PATHINFO_EXTENSION,
        ));

        $base = Str::of($this->title ?: 'prestasi')
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return 'sertifikat_'.($base ?: 'prestasi').($extension !== '' ? '.'.$extension : '');
    }
}
