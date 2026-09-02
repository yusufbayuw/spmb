<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccountBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename', 'total_rows', 'imported_rows', 'failed_rows', 'errors', 'imported_by', 'imported_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'imported_at' => 'datetime',
    ];

    public function importer() { return $this->belongsTo(User::class, 'imported_by'); }
    public function virtualAccounts() { return $this->hasMany(VirtualAccount::class, 'batch_id'); }
}
