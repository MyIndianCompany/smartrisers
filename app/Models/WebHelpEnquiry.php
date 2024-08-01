<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebHelpEnquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['email', 'message'];
    protected $hidden = ['updated_at', 'deleted_at'];
}
