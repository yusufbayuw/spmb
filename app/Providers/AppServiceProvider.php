<?php

namespace App\Providers;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\ParentInfo;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Selection;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\VirtualAccountBatch;
use App\Observers\SensitiveModelObserver;
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

        foreach ([
            Registration::class,
            RegistrationOpening::class,
            ParentInfo::class,
            Document::class,
            Payment::class,
            VirtualAccount::class,
            VirtualAccountBatch::class,
            Unit::class,
            User::class,
            AdmissionTest::class,
            AdmissionTestResult::class,
            Selection::class,
            Announcement::class,
        ] as $model) {
            $model::observe(SensitiveModelObserver::class);
        }
    }
}
