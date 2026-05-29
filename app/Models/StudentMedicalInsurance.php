<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentMedicalInsurance extends Model
{
    protected $fillable = [
        'student_xgh',
        'student_name',
        'insured_area',
        'enrolled_on',
        'insurance_type',
        'insurance_status',
        'identity_type',
        'annual_year',
        'has_paid',
        'payment_start_month',
        'payment_end_month',
        'payment_type',
        'imported_at',
    ];

    protected $casts = [
        'enrolled_on' => 'date',
        'annual_year' => 'integer',
        'has_paid' => 'boolean',
        'imported_at' => 'datetime',
    ];
}
