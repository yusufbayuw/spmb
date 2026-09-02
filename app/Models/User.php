<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'unit_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && in_array($this->role, ['admin', 'tu']);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isTU()
    {
        return $this->role === 'tu';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }
}