<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'data',
        'read',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function likedByUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getLikedByProfilePictureAttribute()
    {
        return $this->likedByUser ? $this->likedByUser->profile_picture : null;
    }
}
