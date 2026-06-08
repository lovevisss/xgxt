<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentComprehensiveAssessment extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'academic_year',
        'college',
        'class_name',
        'rank',
        'total_score',
        'moral_score',
        'intellectual_score',
        'physical_score',
        'aesthetic_score',
        'labor_score',
        'source_sheet',
        'imported_at',
    ];

    protected $casts = [
        'rank' => 'integer',
        'total_score' => 'float',
        'moral_score' => 'float',
        'intellectual_score' => 'float',
        'physical_score' => 'float',
        'aesthetic_score' => 'float',
        'labor_score' => 'float',
        'imported_at' => 'datetime',
    ];
}
