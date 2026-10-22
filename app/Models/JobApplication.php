<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $table = 'job_applications';

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'experience',
        'cv_path',
        'photo_path',
        'linkedin',
        'facebook',
        'instagram',
        'twitter',
        'tiktok',
        'reason',
        'status',
    ];

    protected $casts = [
        'experience' => 'integer',
    ];
}
