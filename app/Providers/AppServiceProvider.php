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
use App\Services\AuditTrail;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
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

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(AuditTrail::class)->record(
                    'auth.login',
                    $event->user,
                    actor: $event->user,
                    metadata: ['guard' => $event->guard, 'remember' => $event->remember],
                    description: 'Login berhasil',
                );
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditTrail::class)->record(
                    'auth.logout',
                    $event->user,
                    actor: $event->user,
                    metadata: ['guard' => $event->guard],
                    description: 'Logout',
                );
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            app(AuditTrail::class)->record(
                'auth.login_failed',
                $event->user instanceof User ? $event->user : null,
                metadata: [
                    'guard' => $event->guard,
                    'email' => $event->credentials['email'] ?? null,
                ],
                description: 'Percobaan login gagal',
            );
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            if ($event->user instanceof User) {
                app(AuditTrail::class)->record(
                    'auth.password_reset',
                    $event->user,
                    actor: $event->user,
                    description: 'Password berhasil direset',
                );
            }
        });
    }
}
