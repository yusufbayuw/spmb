<?php

namespace App\Filament\Forms;

use Filament\Forms;

final class ParentInfoFields
{
    public static function schema(): array
    {
        return [
            Forms\Components\Fieldset::make('Data Ayah')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('father_name')
                        ->label('Nama Ayah')
                        ->required()
                        ->maxLength(150)
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('father_nik')
                        ->label('NIK Ayah')
                        ->rule('digits:16'),
                    Forms\Components\TextInput::make('father_birth_place')
                        ->label('Tempat Lahir Ayah')
                        ->maxLength(100),
                    Forms\Components\DatePicker::make('father_birth_date')
                        ->label('Tanggal Lahir Ayah')
                        ->native(false)
                        ->maxDate(now()->subDay()),
                    Forms\Components\Select::make('father_education')
                        ->label('Pendidikan Terakhir Ayah')
                        ->options(self::educationOptions())
                        ->searchable(),
                    Forms\Components\TextInput::make('father_occupation')
                        ->label('Pekerjaan Ayah')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('father_phone')
                        ->label('Telepon Ayah')
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('father_email')
                        ->label('Email Ayah')
                        ->email()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('father_income')
                        ->label('Penghasilan Ayah')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp'),
                ]),

            Forms\Components\Fieldset::make('Data Ibu')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('mother_name')
                        ->label('Nama Ibu')
                        ->required()
                        ->maxLength(150)
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('mother_nik')
                        ->label('NIK Ibu')
                        ->rule('digits:16'),
                    Forms\Components\TextInput::make('mother_birth_place')
                        ->label('Tempat Lahir Ibu')
                        ->maxLength(100),
                    Forms\Components\DatePicker::make('mother_birth_date')
                        ->label('Tanggal Lahir Ibu')
                        ->native(false)
                        ->maxDate(now()->subDay()),
                    Forms\Components\Select::make('mother_education')
                        ->label('Pendidikan Terakhir Ibu')
                        ->options(self::educationOptions())
                        ->searchable(),
                    Forms\Components\TextInput::make('mother_occupation')
                        ->label('Pekerjaan Ibu')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('mother_phone')
                        ->label('Telepon Ibu')
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('mother_email')
                        ->label('Email Ibu')
                        ->email()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('mother_income')
                        ->label('Penghasilan Ibu')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp'),
                ]),
        ];
    }

    private static function educationOptions(): array
    {
        return [
            'SD' => 'SD / Sederajat',
            'SMP' => 'SMP / Sederajat',
            'SMA' => 'SMA / Sederajat',
            'D1' => 'Diploma 1',
            'D2' => 'Diploma 2',
            'D3' => 'Diploma 3',
            'D4' => 'Diploma 4',
            'S1' => 'Sarjana (S1)',
            'S2' => 'Magister (S2)',
            'S3' => 'Doktor (S3)',
            'Lainnya' => 'Lainnya',
        ];
    }
}
