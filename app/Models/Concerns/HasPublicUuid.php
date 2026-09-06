<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait HasPublicUuid
{
    protected static function bootHasPublicUuid(): void
    {
        static::creating(function (self $model): void {
            $model->uuid = (string) Str::uuid();
        });
        static::updating(function (self $model): void {
            if (! Str::isUuid((string) $model->uuid) || $model->isDirty('uuid')) {
                throw ValidationException::withMessages(['uuid' => 'UUID tidak dapat diubah.']);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
