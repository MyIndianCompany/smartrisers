<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'username',
        'email',
        'bio',
        'gender',
        'custom_gender',
        'profile_picture',
        'post_count',
        'follower_count',
        'following_count',
        'is_private'
    ];

    protected $hidden = ['updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFollowerCountAttribute()
    {
        return $this->user->followers()->count();
    }

    public function getFollowingCountAttribute()
    {
        return $this->user->following()->count();
    }
}
