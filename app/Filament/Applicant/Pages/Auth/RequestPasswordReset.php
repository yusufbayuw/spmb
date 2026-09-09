<?php

namespace App\Filament\Applicant\Pages\Auth;

use App\Filament\Support\Concerns\ResetsCaptchaOnValidationError;
use App\Filament\Support\SpmbCaptcha;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    use ResetsCaptchaOnValidationError;

    public function form(Form $form): Form
    {
        $form = parent::form($form);

        return $form->schema([
            ...$form->getComponents(),
            SpmbCaptcha::make(),
        ]);
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        $this->data['captcha'] = null;

        return parent::getFailureNotification($status);
    }
}
