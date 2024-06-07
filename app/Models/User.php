<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'provider',
        'provider_id',
        'provider_token',
        'profile_picture',
        'status',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'updated_at',
        'deleted_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function following()
    {
        return $this->belongsToMany(User::class, 'followers', 'follower_user_id', 'following_user_id');
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'followers', 'following_user_id', 'follower_user_id');
    }


    public function posts()
    {
        return $this->hasMany(Post::class, 'post_id');
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class, 'user_id');
    }

    public function commentLikes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PostCommentLike::class, 'user_id');
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function website(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserWebsiteUrl::class);
    }

    public function reporters()
    {
        return $this->hasMany(UserReport::class, 'reporter_id');
    }

    public function reports()
    {
        return $this->hasMany(UserReport::class, 'reported_user_id');
    }
}
