<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAcademicYearAverage extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'academic_year',
        'class_code',
        'class_name',
        'major_code',
        'average_score',
        'total_credits',
        'course_count',
        'class_rank',
        'class_size',
        'major_rank',
        'major_size',
        'calculated_at',
    ];

    protected $casts = [
        'average_score' => 'float',
        'total_credits' => 'float',
        'calculated_at' => 'datetime',
    ];
}
