<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionTestResult extends Model
{
    use HasFactory;

    protected $fillable = ['registration_id','admission_test_id','status','score','result','notes','assessed_by','assessed_at'];
    protected $casts = ['score' => 'decimal:2','assessed_at' => 'datetime'];

    public function registration() { return $this->belongsTo(Registration::class); }
    public function admissionTest() { return $this->belongsTo(AdmissionTest::class); }
    public function assessor() { return $this->belongsTo(User::class, 'assessed_by'); }
}
