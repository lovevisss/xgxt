<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAward extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'award_name',
        'annual_year',
        'level',
        'imported_at',
    ];

    protected $casts = [
        'annual_year' => 'integer',
        'imported_at' => 'datetime',
    ];
}
