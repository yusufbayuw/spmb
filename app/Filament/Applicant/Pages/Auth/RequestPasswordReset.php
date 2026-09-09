<?php

namespace App\Filament\Applicant\Pages\Auth;

use App\Filament\Support\SpmbCaptcha;
use Filament\Forms\Form;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function form(Form $form): Form
    {
        $form = parent::form($form);

        return $form->schema([
            ...$form->getComponents(),
            SpmbCaptcha::make(),
        ]);
    }
}
