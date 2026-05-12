<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFamily extends Model
{
    protected $table = 'student_families';

    protected $fillable = [
        'sync_key',
        'stu_no',
        'name',
        'relationship',
        'specific_relationship',
        'work_unit',
        'position',
        'phone',
        'is_emergency_contact',
        'synced_at',
        'is_local_modified',
        'local_modified_at',
    ];

    protected $casts = [
        'is_emergency_contact' => 'boolean',
        'is_local_modified' => 'boolean',
        'synced_at' => 'datetime',
        'local_modified_at' => 'datetime',
    ];
}

