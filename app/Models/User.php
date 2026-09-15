<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Notifications\ApplicantResetPassword;
use App\Notifications\ApplicantVerifyEmail;
use App\Services\ApplicantEmailVerificationUrl;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable;
    use HasPublicUuid;

    protected $fillable = ['name', 'username', 'email', 'password', 'phone', 'role', 'unit_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: function ($value): ?string {
                $username = trim((string) $value);

                return $username === '' ? null : Str::lower($username);
            },
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Email verification is enforced by Filament's emailVerification()
        // middleware on the applicant panel. Do not gate it here, otherwise
        // unverified applicants would receive a 403 before Filament can show
        // the verification prompt.
        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(['super_admin', 'admin_unit', 'tu']),
            'pendaftar' => $this->hasRole('pendaftar'),
            default => false,
        };
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdminUnit(): bool
    {
        return $this->hasRole('admin_unit');
    }

    /**
     * Determine whether the user is staff scoped to a single unit.
     *
     * Existing authorization and query scopes use isTU() as the unit-staff
     * check. Admin Unit deliberately shares that same unit boundary while
     * receiving a broader permission set than operational TU staff.
     */
    public function isTU(): bool
    {
        return $this->hasAnyRole(['admin_unit', 'tu']);
    }

    public function isUser(): bool
    {
        return $this->hasRole('pendaftar');
    }

    public function sendEmailVerificationNotification(): void
    {
        $notification = app(ApplicantVerifyEmail::class);
        $notification->url = app(ApplicantEmailVerificationUrl::class)->for($this);

        $this->notify($notification);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ApplicantResetPassword($token));
    }
}
