<?php

namespace App\Filament\Forms;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use Filament\Forms;
use Filament\Forms\Components\Select;

class RegionFields
{
    /**
     * @return array<int, Select>
     */
    public static function schema(bool $visibleByDefault = false): array
    {
        return [
            Select::make('province_code')
                ->label('Provinsi')
                ->options(fn (): array => Province::query()->orderBy('name')->pluck('name', 'code')->all())
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set): void {
                    $set('city_code', null);
                    $set('district_code', null);
                    $set('village_code', null);
                })
                ->visible($visibleByDefault),
            Select::make('city_code')
                ->label('Kabupaten/Kota')
                ->options(fn (Forms\Get $get): array => filled($get('province_code'))
                    ? Regency::query()
                        ->where('province_code', $get('province_code'))
                        ->orderBy('name')
                        ->pluck('name', 'code')
                        ->all()
                    : [])
                ->searchable()
                ->preload()
                ->live()
                ->disabled(fn (Forms\Get $get): bool => blank($get('province_code')))
                ->afterStateUpdated(function (Forms\Set $set): void {
                    $set('district_code', null);
                    $set('village_code', null);
                })
                ->visible($visibleByDefault),
            Select::make('district_code')
                ->label('Kecamatan')
                ->options(fn (Forms\Get $get): array => filled($get('city_code'))
                    ? District::query()
                        ->where('regency_code', $get('city_code'))
                        ->orderBy('name')
                        ->pluck('name', 'code')
                        ->all()
                    : [])
                ->searchable()
                ->preload()
                ->live()
                ->disabled(fn (Forms\Get $get): bool => blank($get('city_code')))
                ->afterStateUpdated(fn (Forms\Set $set): mixed => $set('village_code', null))
                ->visible($visibleByDefault),
            Select::make('village_code')
                ->label('Desa/Kelurahan')
                ->options(fn (Forms\Get $get): array => filled($get('district_code'))
                    ? Village::query()
                        ->where('district_code', $get('district_code'))
                        ->orderBy('name')
                        ->pluck('name', 'code')
                        ->all()
                    : [])
                ->searchable()
                ->preload()
                ->disabled(fn (Forms\Get $get): bool => blank($get('district_code')))
                ->visible($visibleByDefault),
        ];
    }
}
