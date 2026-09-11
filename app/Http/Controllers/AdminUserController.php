<?php

namespace App\Http\Controllers;

use App\Models\CounselorManagementPermission;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\CounselorManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function page()
    {
        return view('admin-users');
    }

    public function index(Request $request, CounselorManagement $management): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $unit = trim((string) $request->query('unit', ''));
        $status = trim((string) $request->query('status', 'active'));
        $role = trim((string) $request->query('role', ''));

        $staff = StaffMember::query()
            ->with(['user:id,cas_username,name,email,role,dwbm,dwmc,created_at', 'counselorManagementPermission'])
            ->when($keyword !== '', fn ($query) => $query->where(fn ($subQuery) => $subQuery
                ->where('employee_no', 'like', "%{$keyword}%")
                ->orWhere('name', 'like', "%{$keyword}%")))
            ->when($unit !== '', fn ($query) => $query->where('department_code', $unit))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($role === 'unassigned', fn ($query) => $query->whereDoesntHave('user'))
            ->when(in_array($role, $this->roles(), true), fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery->where('role', $role)))
            ->orderByDesc('is_active')
            ->orderBy('department_code')
            ->orderBy('name')
            ->paginate(max(10, min(100, (int) $request->query('per_page', 40))));

        $unmatchedUsers = User::query()
            ->whereNotNull('cas_username')
            ->whereDoesntHave('staffMember')
            ->orderByRaw("CASE role WHEN 'super_admin' THEN 0 WHEN 'admin' THEN 1 WHEN 'counselor' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get(['id', 'name', 'cas_username', 'email', 'role', 'dwbm', 'dwmc']);

        return response()->json([
            'data' => collect($staff->items())->map(fn (StaffMember $member) => $this->staffPayload($member))->values(),
            'meta' => [
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
            ],
            'roles' => $this->roles(),
            'units' => StaffMember::query()->where('is_active', true)->whereNotNull('department_code')
                ->selectRaw('department_code as code, MAX(department_name) as name, COUNT(*) as total')
                ->groupBy('department_code')->orderBy('department_code')->get(),
            'colleges' => $management->colleges(),
            'unmatched_users' => $unmatchedUsers,
            'stats' => [
                'active_staff' => StaffMember::query()->where('is_active', true)->count(),
                'provisioned' => User::query()->whereHas('staffMember', fn ($query) => $query->where('is_active', true))->count(),
                'privileged' => User::query()->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])->count(),
                'counselor_managers' => CounselorManagementPermission::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function updatePermissions(Request $request, StaffMember $staffMember, CounselorManagement $management): JsonResponse
    {
        abort_unless($staffMember->is_active, 422, '只能为在职教职工配置权限。');

        $data = $request->validate([
            'role' => ['required', Rule::in($this->roles())],
            'counselor_management' => ['required', 'boolean'],
            'all_colleges' => ['required', 'boolean'],
            'department_codes' => ['present', 'array'],
            'department_codes.*' => ['string', Rule::in(array_column($management->colleges(), 'code'))],
        ]);
        abort_if($data['counselor_management'] && ! $data['all_colleges'] && $data['department_codes'] === [], 422, '请至少选择一个管理学院。');

        return DB::transaction(function () use ($staffMember, $data, $management): JsonResponse {
            $user = User::query()->where('cas_username', $staffMember->employee_no)->lockForUpdate()->first();
            if ($user?->isSuperAdmin() && $data['role'] !== User::ROLE_SUPER_ADMIN) {
                abort_if(User::query()->where('role', User::ROLE_SUPER_ADMIN)->count() <= 1, 422, '系统至少需要保留一位总管理员。');
            }

            $permission = CounselorManagementPermission::query()->where('employee_no', $staffMember->employee_no)->lockForUpdate()->first();
            $before = [
                'role' => $user?->role,
                'account_exists' => (bool) $user,
                'counselor_management' => $permission?->toArray(),
            ];

            if (! $user) {
                $email = $this->availableEmail($staffMember);
                $user = User::query()->create([
                    'cas_username' => $staffMember->employee_no,
                    'name' => $staffMember->name,
                    'email' => $email,
                    'password' => Str::random(40),
                    'role' => $data['role'],
                    'dwbm' => $staffMember->department_code,
                    'dwmc' => $staffMember->department_name,
                    'phone' => $staffMember->phone,
                ]);
            } else {
                $user->forceFill([
                    'name' => $staffMember->name,
                    'role' => $data['role'],
                    'dwbm' => $staffMember->department_code,
                    'dwmc' => $staffMember->department_name,
                    'phone' => $staffMember->phone ?: $user->phone,
                ])->save();
            }

            $permission = CounselorManagementPermission::query()->updateOrCreate(
                ['employee_no' => $staffMember->employee_no],
                [
                    'teacher_name' => $staffMember->name,
                    'all_colleges' => $data['counselor_management'] && $data['all_colleges'],
                    'department_codes' => $data['counselor_management'] && ! $data['all_colleges'] ? array_values(array_unique($data['department_codes'])) : [],
                    'is_active' => $data['counselor_management'],
                ],
            );

            $after = ['role' => $user->role, 'account_exists' => true, 'counselor_management' => $permission->toArray()];
            $management->log('permission.bundle.save', $staffMember->employee_no, $before, $after);

            return response()->json(['message' => '人员权限已保存。', 'data' => $this->staffPayload($staffMember->fresh(['user', 'counselorManagementPermission']))]);
        });
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:super_admin,admin,counselor,staff'],
        ]);

        $targetRole = (string) $validated['role'];

        if ($user->isSuperAdmin() && $targetRole !== User::ROLE_SUPER_ADMIN) {
            $superAdminCount = User::query()->where('role', User::ROLE_SUPER_ADMIN)->count();
            abort_if($superAdminCount <= 1, 422, '系统至少需要保留一位超级管理员。');
        }

        $before = $user->role;
        $user->forceFill([
            'role' => $targetRole,
        ])->save();
        app(CounselorManagement::class)->log('role.update', (string) $user->cas_username, ['role' => $before], ['role' => $targetRole]);

        return response()->json([
            'message' => '角色更新成功。',
            'data' => $user->only(['id', 'role']),
        ]);
    }

    private function roles(): array
    {
        return [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_COUNSELOR, User::ROLE_STAFF];
    }

    private function staffPayload(StaffMember $staff): array
    {
        $user = $staff->user;
        $permission = $staff->counselorManagementPermission;

        return [
            'id' => $staff->id,
            'employee_no' => $staff->employee_no,
            'name' => $staff->name,
            'department_code' => $staff->department_code,
            'department_name' => $staff->department_name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'is_active' => $staff->is_active,
            'synced_at' => optional($staff->synced_at)->toDateTimeString(),
            'account_exists' => (bool) $user,
            'user_id' => $user?->id,
            'role' => $user?->role,
            'counselor_management' => (bool) $permission?->is_active,
            'all_colleges' => (bool) $permission?->all_colleges,
            'department_codes' => $permission?->department_codes ?? [],
        ];
    }

    private function availableEmail(StaffMember $staff): string
    {
        $email = trim((string) $staff->email);
        if ($email !== '' && ! User::query()->where('email', $email)->exists()) {
            return $email;
        }

        return $staff->employee_no.'@staff.local';
    }
}
