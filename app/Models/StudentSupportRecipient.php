<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSupportRecipient extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'gender',
        'college',
        'major',
        'support_level',
        'academic_year',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];
}
