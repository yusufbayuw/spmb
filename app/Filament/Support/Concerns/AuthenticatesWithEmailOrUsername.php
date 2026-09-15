<?php

namespace App\Filament\Support\Concerns;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;

trait AuthenticatesWithEmailOrUsername
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email atau Username')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = trim((string) ($data['email'] ?? ''));
        $attribute = str_contains($login, '@') ? 'email' : 'username';

        if ($attribute === 'username') {
            $login = mb_strtolower($login);
        }

        return [
            $attribute => $login,
            'password' => $data['password'],
        ];
    }
}
