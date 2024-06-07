<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserReportFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_report_id',
        'original_file_name',
        'files',
        'mime_type'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function report()
    {
        return $this->belongsTo(UserReport::class, 'user_report_id');
    }
}
