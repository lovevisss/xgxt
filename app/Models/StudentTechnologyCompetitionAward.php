<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTechnologyCompetitionAward extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'college',
        'class_name',
        'grade',
        'award_name',
        'awarded_at',
        'annual_year',
        'imported_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
        'annual_year' => 'integer',
        'imported_at' => 'datetime',
    ];
}
