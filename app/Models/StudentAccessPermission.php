<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAccessPermission extends Model
{
    public const SCOPE_COLLEGE = 'college';

    public const SCOPE_ALL = 'all';

    protected $fillable = [
        'employee_no',
        'teacher_name',
        'unit_name',
        'scope_name',
        'department_code',
        'scope_type',
        'is_active',
        'imported_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'imported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_no', 'cas_username');
    }

    public function allowsDepartment(?string $departmentCode): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->scope_type === self::SCOPE_ALL) {
            return true;
        }

        return filled($this->department_code)
            && filled($departmentCode)
            && (string) $this->department_code === (string) $departmentCode;
    }

    public static function normalizeScopeType(?string $scopeName, ?string $departmentCode): string
    {
        return trim((string) $departmentCode) === '1003' || trim((string) $scopeName) === '最高'
            ? self::SCOPE_ALL
            : self::SCOPE_COLLEGE;
    }
}
