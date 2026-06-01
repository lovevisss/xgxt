<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCourseGrade extends Model
{
    protected $table = 'student_course_grades';

    protected $fillable = [
        'xh',
        'xnxq',
        'kcbm',
        'kcmc',
        'cj',
        'jd',
        'xf',
        'ksxz',
        'raw',
        'synced_at',
    ];

    protected $casts = [
        'jd' => 'decimal:2',
        'xf' => 'decimal:2',
        'raw' => 'array',
        'synced_at' => 'datetime',
    ];
}

