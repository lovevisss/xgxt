<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StaffMember extends Model
{
    protected $fillable = [
        'employee_no',
        'name',
        'department_code',
        'department_name',
        'email',
        'phone',
        'is_active',
        'last_seen_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'cas_username', 'employee_no');
    }

    public function counselorManagementPermission(): HasOne
    {
        return $this->hasOne(CounselorManagementPermission::class, 'employee_no', 'employee_no');
    }
}
