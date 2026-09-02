<?php

namespace App\Observers;

use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Model;

class SensitiveModelObserver
{
    public function created(Model $model): void
    {
        app(AuditTrail::class)->recordModelChange($model, 'created');
    }

    public function updated(Model $model): void
    {
        app(AuditTrail::class)->recordModelChange($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        app(AuditTrail::class)->recordModelChange($model, 'deleted');
    }
}
