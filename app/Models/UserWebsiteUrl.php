<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWebsiteUrl extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'url',
        'created_at',
        'updated_at'
    ];

    protected $hidden = ['updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
