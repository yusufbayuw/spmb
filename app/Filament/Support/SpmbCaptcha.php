<?php

namespace App\Filament\Support;

use App\Filament\Forms\Components\ShieldCaptcha;

final class SpmbCaptcha
{
    public static function make(string $name = 'captcha'): ShieldCaptcha
    {
        return ShieldCaptcha::make($name)
            ->label('Kode Keamanan')
            ->helperText('Masukkan 5 karakter pada gambar. Huruf besar/kecil tidak dibedakan.')
            ->required()
            ->validationAttribute('kode keamanan')
            ->dehydrated(false)
            ->ltr();
    }
}
