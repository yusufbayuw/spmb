<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = ['registration_id','status','title','message','published_by','published_at','email_sent_at'];
    protected $casts = ['published_at' => 'datetime','email_sent_at' => 'datetime'];

    public function registration() { return $this->belongsTo(Registration::class); }
    public function publisher() { return $this->belongsTo(User::class, 'published_by'); }
}
