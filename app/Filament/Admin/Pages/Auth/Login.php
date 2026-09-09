<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Filament\Support\SpmbCaptcha;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        $form = parent::form($form);

        return $form->schema([
            ...$form->getComponents(),
            SpmbCaptcha::make(),
        ]);
    }

    protected function throwFailureValidationException(): never
    {
        $this->data['captcha'] = null;

        parent::throwFailureValidationException();
    }
}
