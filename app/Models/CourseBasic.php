<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseBasic extends Model
{
    protected $table = 'course_basics';

    protected $fillable = [
        'kcbm',
        'kcmc',
        'raw',
        'synced_at',
    ];

    protected $casts = [
        'raw' => 'array',
        'synced_at' => 'datetime',
    ];
}

