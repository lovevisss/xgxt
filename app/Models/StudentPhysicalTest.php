<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPhysicalTest extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'gender',
        'college',
        'class_name',
        'academic_year',
        'score',
        'remark',
        'imported_at',
    ];

    protected $casts = [
        'score' => 'decimal:1',
        'imported_at' => 'datetime',
    ];
}
