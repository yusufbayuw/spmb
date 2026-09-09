<?php

namespace App\Filament\Support\Concerns;

use Illuminate\Validation\ValidationException;

trait ResetsCaptchaOnValidationError
{
    protected function onValidationError(ValidationException $exception): void
    {
        if (property_exists($this, 'data') && is_array($this->data ?? null)) {
            $this->data['captcha'] = null;
        }

        if (property_exists($this, 'captcha')) {
            $this->captcha = null;
        }

        parent::onValidationError($exception);
    }
}
