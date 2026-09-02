<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name','email','password','phone','role','unit_id','is_active'];
    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(['super_admin', 'tu']),
            'pendaftar' => $this->hasRole('pendaftar'),
            default => false,
        };
    }

    public function unit() { return $this->belongsTo(Unit::class); }
    public function registrations() { return $this->hasMany(Registration::class); }

    public function isAdmin(): bool { return $this->hasRole('super_admin'); }
    public function isTU(): bool { return $this->hasRole('tu'); }
    public function isUser(): bool { return $this->hasRole('pendaftar'); }
}
