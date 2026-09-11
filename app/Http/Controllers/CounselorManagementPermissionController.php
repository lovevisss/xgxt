<?php

namespace App\Http\Controllers;

use App\Models\CounselorManagementPermission;
use App\Models\StaffMember;
use App\Services\CounselorManagement;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CounselorManagementPermissionController extends Controller
{
    public function index(Request $request, CounselorManagement $management)
    {
        abort_unless(CurrentUser::get()?->isSuperAdmin(), 403);

        return response()->json(['data' => CounselorManagementPermission::orderBy('employee_no')->get(), 'colleges' => $management->colleges()]);
    }

    public function save(Request $request, CounselorManagement $management)
    {
        abort_unless(CurrentUser::get()?->isSuperAdmin(), 403);
        $data = $request->validate(['employee_no' => ['required', 'string', 'max:255'], 'teacher_name' => ['nullable', 'string', 'max:255'], 'all_colleges' => ['required', 'boolean'], 'department_codes' => ['present', 'array'], 'department_codes.*' => ['string', Rule::in(array_column($management->colleges(), 'code'))], 'is_active' => ['required', 'boolean']]);
        $staff = StaffMember::query()->where('employee_no', $data['employee_no'])->where('is_active', true)->first();
        abort_unless($staff, 422, '请选择在职教职工目录中的人员。');
        $data['teacher_name'] = $staff->name;
        abort_if(! $data['all_colleges'] && ! $data['department_codes'], 422, '请选择授权学院');
        if ($data['all_colleges']) {
            $data['department_codes'] = [];
        }

        return DB::transaction(function () use ($data, $management) {
            $before = CounselorManagementPermission::where('employee_no', $data['employee_no'])->first()?->toArray();
            $permission = CounselorManagementPermission::updateOrCreate(['employee_no' => $data['employee_no']], $data);
            $management->log('permission.save', $data['employee_no'], $before, $permission->toArray());

            return response()->json(['data' => $permission]);
        });
    }

    public function destroy(CounselorManagementPermission $permission, CounselorManagement $management)
    {
        abort_unless(CurrentUser::get()?->isSuperAdmin(), 403);
        DB::transaction(function () use ($permission, $management) {
            $management->log('permission.delete', $permission->employee_no, $permission->toArray(), null);
            $permission->delete();
        });

        return response()->json(['message' => '授权已撤销']);
    }
}
