<?php

namespace Tests\Feature;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IndonesianValidationTest extends TestCase
{
    public function test_application_uses_indonesian_locale(): void
    {
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('id', config('app.locale'));
    }

    public function test_standard_validation_messages_are_in_indonesian(): void
    {
        $validator = Validator::make(
            ['nik' => '12345'],
            ['nik' => ['required', 'digits:16']],
        );

        $this->assertSame(
            'NIK harus terdiri dari 16 digit.',
            $validator->errors()->first('nik'),
        );

        $this->assertSame(
            'NIK sudah digunakan.',
            __('validation.unique', ['attribute' => 'NIK']),
        );

        $this->assertSame(
            'Email atau kata sandi yang Anda masukkan tidak benar.',
            __('auth.failed'),
        );
    }

    public function test_acronym_validation_attribute_keeps_nik_uppercase(): void
    {
        $field = TextInput::make('nik')
            ->label('NIK')
            ->validationAttribute('NIK');

        $this->assertSame('NIK', $field->getValidationAttribute());
    }

    public function test_captcha_failure_message_is_localized(): void
    {
        $this->assertSame(
            'Kode keamanan tidak sesuai. Silakan coba lagi.',
            __('filament-shield-captcha::messages.validation.failed'),
        );
    }
}
