<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSafetyInsurance extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'grade',
        'education_length',
        'college',
        'major',
        'class_name',
        'annual_year',
        'is_insured',
        'imported_at',
    ];

    protected $casts = [
        'annual_year' => 'integer',
        'is_insured' => 'boolean',
        'imported_at' => 'datetime',
    ];
}
