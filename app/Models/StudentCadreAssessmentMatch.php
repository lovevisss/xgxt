<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCadreAssessmentMatch extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
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
        'candidate_students',
        'status',
        'resolved_student_xgh',
        'resolved_at',
    ];

    protected $casts = [
        'self_score' => 'float',
        'peer_score' => 'float',
        'advisor_score' => 'float',
        'department_score' => 'float',
        'total_score' => 'float',
        'candidate_students' => 'array',
        'resolved_at' => 'datetime',
    ];
}
