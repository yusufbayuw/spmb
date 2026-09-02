<?php

namespace App\Providers;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        FilamentShield::configurePermissionIdentifierUsing(
            fn (string $resource): string => str($resource::getModel())
                ->afterLast('\\')
                ->lower()
                ->toString()
        );
    }
}
