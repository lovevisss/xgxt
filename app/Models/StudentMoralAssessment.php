<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentMoralAssessment extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'academic_year',
        'semester',
        'college',
        'class_name',
        'rank',
        'base_score',
        'bonus_score',
        'deduction_score',
        'total_score',
        'remark',
        'source_sheet',
        'imported_at',
    ];

    protected $casts = [
        'rank' => 'integer',
        'base_score' => 'float',
        'bonus_score' => 'float',
        'deduction_score' => 'float',
        'total_score' => 'float',
        'imported_at' => 'datetime',
    ];
}
