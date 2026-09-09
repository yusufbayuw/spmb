<?php

namespace App\Filament\Applicant\Pages\Auth;

use App\Filament\Support\Concerns\ResetsCaptchaOnValidationError;
use App\Filament\Support\SpmbCaptcha;
use Filament\Forms\Form;
use Filament\Pages\Auth\PasswordReset\ResetPassword as BaseResetPassword;

class ResetPassword extends BaseResetPassword
{
    use ResetsCaptchaOnValidationError;

    public ?string $captcha = null;

    public function form(Form $form): Form
    {
        $form = parent::form($form);

        return $form->schema([
            ...$form->getComponents(),
            SpmbCaptcha::make(),
        ]);
    }
}
