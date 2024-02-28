<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Follower extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'follower_user_id',
        'following_user_id',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function followerUser()
    {
        return $this->belongsTo(User::class, 'follower_user_id');
    }

    public function followingUser()
    {
        return $this->belongsTo(User::class, 'following_user_id');
    }
}
