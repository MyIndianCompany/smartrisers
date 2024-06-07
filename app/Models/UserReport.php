<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserReport extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'report_description'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reported()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reportFiles()
    {
        return $this->hasMany(UserReportFile::class, 'user_report_id');
    }
}
