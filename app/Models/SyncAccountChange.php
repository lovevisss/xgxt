<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncAccountChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sync_task_id', 'student_xgh', 'student_name', 'college_name',
        'previous_expiry_date', 'new_expiry_date', 'previous_state', 'new_state', 'changed_at',
    ];
}
