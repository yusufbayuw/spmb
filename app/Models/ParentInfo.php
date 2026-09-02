<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'father_name',
        'father_nik',
        'father_birth_place',
        'father_birth_date',
        'father_education',
        'father_occupation',
        'father_phone',
        'father_email',
        'father_income',
        'mother_name',
        'mother_nik',
        'mother_birth_place',
        'mother_birth_date',
        'mother_education',
        'mother_occupation',
        'mother_phone',
        'mother_email',
        'mother_income',
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_address',
    ];

    protected $casts = [
        'father_birth_date' => 'date',
        'mother_birth_date' => 'date',
        'father_income' => 'decimal:2',
        'mother_income' => 'decimal:2',
    ];

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }
}