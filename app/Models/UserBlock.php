<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBlock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['blocker_id', 'blocked_id'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
