<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentEducationHistory extends Model
{
    protected $fillable = [
        'source_id',
        'stu_no',
        'qualifications',
        'start_year',
        'end_year',
        'school_name',
        'sort',
        'source_created_at',
        'source_updated_at',
        'synced_at',
    ];

    protected $casts = [
        'sort' => 'integer',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
