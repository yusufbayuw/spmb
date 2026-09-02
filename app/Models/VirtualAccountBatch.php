<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccountBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id', 'bank', 'filename', 'default_amount', 'total_rows', 'imported_rows',
        'failed_rows', 'errors', 'expires_at', 'imported_by', 'imported_at',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'errors' => 'array',
        'expires_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function unit() { return $this->belongsTo(Unit::class); }
    public function importer() { return $this->belongsTo(User::class, 'imported_by'); }
    public function virtualAccounts() { return $this->hasMany(VirtualAccount::class, 'batch_id'); }
}
