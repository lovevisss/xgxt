<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCadreAssessment extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'academic_year',
        'semester',
        'organization',
        'department',
        'position',
        'self_score',
        'peer_score',
        'advisor_score',
        'department_score',
        'total_score',
        'grade',
        'source_file',
        'sync_key',
        'imported_at',
    ];

    protected $casts = [
        'self_score' => 'float',
        'peer_score' => 'float',
        'advisor_score' => 'float',
        'department_score' => 'float',
        'total_score' => 'float',
        'imported_at' => 'datetime',
    ];
}
