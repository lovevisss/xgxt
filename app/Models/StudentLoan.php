<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLoan extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'id_card',
        'college',
        'class_name',
        'amount',
        'annual_year',
        'source',
        'remark',
        'imported_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'annual_year' => 'integer',
        'imported_at' => 'datetime',
    ];
}
