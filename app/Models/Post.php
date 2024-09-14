<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * @method static where(string $string, \Closure $param)
 */
class Post extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'caption',
        'original_file_name',
        'file_url',
        'public_id',
        'file_size',
        'file_type',
        'mime_type',
        'width',
        'height',
        'like_count',
        'comment_count',
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
        'updated_at',
        'deleted_at'
    ];

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class, 'post_id');
    }
}
