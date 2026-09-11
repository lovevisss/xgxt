<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounselorManagementPermission extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['department_codes' => 'array', 'all_colleges' => 'boolean', 'is_active' => 'boolean'];
}
