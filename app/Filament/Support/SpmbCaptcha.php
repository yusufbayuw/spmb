<?php

namespace App\Filament\Support;

use MortezaAshrafi\FilamentShieldCaptcha\Forms\Components\Captcha;

final class SpmbCaptcha
{
    public static function make(string $name = 'captcha'): Captcha
    {
        return Captcha::make($name)
            ->label('Kode Keamanan')
            ->helperText('Masukkan 5 karakter pada gambar. Huruf besar/kecil tidak dibedakan.')
            ->required()
            ->validationAttribute('kode keamanan')
            ->dehydrated(false)
            ->ltr();
    }
}
