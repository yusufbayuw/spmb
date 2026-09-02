<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionTest extends Model
{
    use HasFactory;

    protected $fillable = ['unit_id','name','code','description','sort_order','is_required','is_active','scheduled_at','location','passing_score','result_type'];
    protected $casts = ['is_required' => 'boolean','is_active' => 'boolean','scheduled_at' => 'datetime','passing_score' => 'decimal:2'];

    public function unit() { return $this->belongsTo(Unit::class); }
    public function results() { return $this->hasMany(AdmissionTestResult::class); }
}
