<?php

namespace App\Services;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\ParentInfo;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Selection;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\VirtualAccountBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditTrail
{
    private const MASKED_KEYS = [
        'password',
        'remember_token',
        'token',
        'api_token',
    ];

    private const IGNORED_KEYS = [
        'updated_at',
    ];

    private const ACTOR_KEYS = [
        'verified_by',
        'decided_by',
        'published_by',
        'assessed_by',
        'assigned_by',
        'va_sent_by',
        'data_validated_by',
        'applicant_card_issued_by',
        'imported_by',
        'created_by',
    ];

    public function record(
        string $event,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?User $actor = null,
        ?int $unitId = null,
        ?int $registrationId = null,
        ?string $description = null,
    ): AuditLog {
        $actor ??= auth()->user() ?? $this->inferActor($subject);

        [$inferredUnitId, $inferredRegistrationId] = $this->inferContext($subject);
        $unitId ??= $inferredUnitId;
        $registrationId ??= $inferredRegistrationId;

        $request = app()->bound('request') ? request() : null;
        $requestId = null;

        if ($request) {
            $requestId = $request->attributes->get('audit_request_id');

            if (! $requestId) {
                $requestId = (string) Str::uuid();
                $request->attributes->set('audit_request_id', $requestId);
            }

            $metadata = array_merge([
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
            ], $metadata);
        }

        return AuditLog::query()->create([
            'request_id' => $requestId,
            'user_id' => $actor?->id,
            'unit_id' => $unitId,
            'registration_id' => $registrationId,
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'metadata' => $this->sanitize($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function recordModelChange(Model $model, string $action): ?AuditLog
    {
        $event = Str::snake(class_basename($model)).'.'.$action;

        if ($action === 'created') {
            $old = [];
            $new = Arr::except($model->getAttributes(), self::IGNORED_KEYS);
        } elseif ($action === 'updated') {
            $changes = Arr::except($model->getChanges(), self::IGNORED_KEYS);

            if ($changes === []) {
                return null;
            }

            $old = [];
            $new = [];

            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getRawOriginal($key);
                $new[$key] = $model->getAttribute($key);
            }
        } else {
            $old = Arr::except($model->getAttributes(), self::IGNORED_KEYS);
            $new = [];
        }

        return $this->record(
            $event,
            $model,
            $old,
            $new,
            description: $this->descriptionFor($model, $action),
        );
    }

    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array((string) $key, self::MASKED_KEYS, true)) {
                $values[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }

    private function inferActor(?Model $subject): ?User
    {
        if (! $subject) {
            return null;
        }

        foreach (self::ACTOR_KEYS as $key) {
            $actorId = $subject->getAttribute($key);

            if ($actorId) {
                return User::query()->find($actorId);
            }
        }

        return null;
    }

    private function inferContext(?Model $subject): array
    {
        if (! $subject) {
            return [auth()->user()?->unit_id, null];
        }

        if ($subject instanceof Registration) {
            return [$subject->unit_id, $subject->id];
        }

        if ($subject instanceof RegistrationOpening || $subject instanceof RegistrationPathway || $subject instanceof StudyProgram) {
            return [$subject->unit_id, null];
        }

        if ($subject instanceof VirtualAccount) {
            return [$subject->unit_id, $subject->registration_id];
        }

        if ($subject instanceof AdmissionTest) {
            return [$subject->unit_id, null];
        }

        if ($subject instanceof User) {
            return [$subject->unit_id, null];
        }

        if ($subject instanceof Unit) {
            return [$subject->id, null];
        }

        if ($subject instanceof ParentInfo) {
            $registration = Registration::query()->find($subject->registration_id);

            return [$registration?->unit_id, $subject->registration_id];
        }

        if ($subject instanceof Payment
            || $subject instanceof Document
            || $subject instanceof AdmissionTestResult
            || $subject instanceof Selection
            || $subject instanceof Announcement) {
            $registration = Registration::query()->find($subject->registration_id);

            return [$registration?->unit_id, $subject->registration_id];
        }

        if ($subject instanceof VirtualAccountBatch) {
            return [auth()->user()?->unit_id, null];
        }

        return [auth()->user()?->unit_id, null];
    }

    private function descriptionFor(Model $model, string $action): string
    {
        $label = Str::headline(class_basename($model));

        return match ($action) {
            'created' => "{$label} dibuat",
            'updated' => "{$label} diubah",
            'deleted' => "{$label} dihapus",
            default => "{$label}: {$action}",
        };
    }
}
