<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Selection extends Model
{
    use HasFactory;

    protected $fillable = ['registration_id','decision','final_score','notes','decided_by','decided_at'];
    protected $casts = ['final_score' => 'decimal:2','decided_at' => 'datetime'];

    public function registration() { return $this->belongsTo(Registration::class); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
