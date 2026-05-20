<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPunishment extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'reason',
        'punished_at',
        'annual_year',
        'imported_at',
    ];

    protected $casts = [
        'punished_at' => 'date',
        'annual_year' => 'integer',
        'imported_at' => 'datetime',
    ];
}
