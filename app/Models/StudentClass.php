<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentClass extends Model
{
    protected $fillable = [
        'class_code',
        'class_name',
        'major_code',
        'grade',
        'built_at',
        'standard_student_number',
        'source_updated_at',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}
