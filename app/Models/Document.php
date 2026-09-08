<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function canBeReviewed(): bool
    {
        if ($this->superseded_at || ! $this->registration?->isOperational()) {
            return false;
        }

        if (in_array($this->registration->current_stage, ['documents', 'document_verification'], true)) {
            return true;
        }

        $definition = collect($this->registration->documentRequirements())
            ->firstWhere('key', $this->requirement_key ?: $this->type);

        return $definition !== null && ! $definition['required']
            && in_array($this->registration->current_stage, [
                'tests', 'selection', 'announcement', 'waiting_list', 'admission_offer',
                're_registration', 'enrollment', 'completed',
            ], true);
    }

    public function displayFileName(): string
    {
        $registration = $this->registration;
        $requirement = $registration
            ? collect($registration->documentRequirements())
                ->firstWhere('key', $this->requirement_key ?: $this->type)
            : null;

        $label = (string) ($requirement['label'] ?? Str::of($this->requirement_key ?: $this->type)
            ->replace('_', ' ')
            ->headline()
            ->toString());

        $documentName = Str::of($label)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        $registrationNumber = $registration?->registration_number ?: 'tanpa_nomor_registrasi';
        $attachmentSuffix = (int) $this->attachment_index > 0
            ? '_'.((int) $this->attachment_index + 1)
            : '';

        $extension = strtolower((string) ($this->file_type
            ?: pathinfo((string) ($this->original_name ?: $this->file_path), PATHINFO_EXTENSION)));

        return $documentName.'_'.$registrationNumber.$attachmentSuffix.($extension !== '' ? '.'.$extension : '');
    }

    public function assertCanBeReviewed(): void
    {
        if (! $this->canBeReviewed()) {
            throw ValidationException::withMessages([
                'document' => 'Berkas tidak dapat diperiksa pada tahap atau lifecycle pendaftaran saat ini.',
            ]);
        }
    }
}
