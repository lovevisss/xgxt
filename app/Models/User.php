<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_COUNSELOR = 'counselor';

    public const ROLE_STUDENT_AFFAIRS = 'student_affairs';

    public const ROLE_STAFF = 'staff';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cas_username',
        'name',
        'email',
        'password',
        'role',
        'dwbm',
        'dwmc',
        'phone',
        'office_phone',
        'office_location',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    public function isCounselor(): bool
    {
        return $this->role === self::ROLE_COUNSELOR;
    }

    public function studentAccessPermissions(): HasMany
    {
        return $this->hasMany(StudentAccessPermission::class, 'employee_no', 'cas_username');
    }

    public function hasActiveStudentAccessPermission(): bool
    {
        return filled($this->cas_username)
            && $this->studentAccessPermissions()->where('is_active', true)->exists();
    }

    public function canManageStudentDepartment(?string $studentDepartmentCode): bool
    {
        return app(\App\Services\StudentAccess::class)->canEditFamily($studentDepartmentCode, $this);
    }

    public function classAssignments(): HasMany
    {
        return $this->hasMany(CounselorClassAssignment::class, 'counselor_cas_username', 'cas_username');
    }

    public function staffMember(): HasOne
    {
        return $this->hasOne(StaffMember::class, 'employee_no', 'cas_username');
    }

    public function isInactiveStaffMember(): bool
    {
        return filled($this->cas_username)
            && $this->staffMember()->where('is_active', false)->exists();
    }

    public function canViewStudent(?Student $student): bool
    {
        return app(\App\Services\StudentAccess::class)->canViewStudent($student, $this);
    }
}
