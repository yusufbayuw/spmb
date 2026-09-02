<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['registration_id','virtual_account_id','va_number','amount','status','payment_date','payment_method','proof_path','proof_original_name','proof_uploaded_at','note','rejection_reason','verified_by','verified_at','va_sent_at','va_sent_by'];
    protected $casts = ['amount' => 'decimal:2','payment_date' => 'datetime','proof_uploaded_at' => 'datetime','verified_at' => 'datetime','va_sent_at' => 'datetime'];

    public function registration() { return $this->belongsTo(Registration::class); }
    public function virtualAccount() { return $this->belongsTo(VirtualAccount::class); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
    public function vaSender() { return $this->belongsTo(User::class, 'va_sent_by'); }
}
