<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['name','code','description','is_active'];

    public function registrations() { return $this->hasMany(Registration::class); }
    public function users() { return $this->hasMany(User::class); }
    public function admissionTests() { return $this->hasMany(AdmissionTest::class)->orderBy('sort_order'); }
}
