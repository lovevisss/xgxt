<?php

namespace App\Http\Controllers;

use App\Models\CounselorClassAssignment;
use App\Models\CounselorCollegeDelegation;
use App\Models\Student;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\CounselorClassCatalog;
use App\Services\CounselorClassOwnership;
use App\Services\CounselorCollegeEligibility;
use App\Services\CounselorManagement;
use App\Services\CounselorWorkbookImport;
use App\Services\UnassignedCounselorClasses;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CounselorAssignmentController extends Controller
{
    public function __construct(private CounselorManagement $management)
    {
    }

    public function page()
    {
        abort_unless($this->management->admin() || $this->management->permission() || CurrentUser::get()?->isCounselor(), 403);

        return view('counselor-assignments');
    }

    private function viewable(User $user): void
    {
        abort_unless($this->canView($user), 403);
        abort_unless(($user->isCounselor() || $user->classAssignments()->exists()) && $user->staffMember()->where('is_active', true)->exists(), 404);
    }

    private function canView(User $user): bool
    {
        return $this->management->allowed($user->dwbm)
            || collect(app(CounselorCollegeEligibility::class)->codes($user))->contains(fn ($code) => $this->management->allowed($code))
            || (CurrentUser::get()?->isCounselor() && $user->id === CurrentUser::get()?->id);
    }

    public function index()
    {
        $users = User::where(fn ($q) => $q->where('role', User::ROLE_COUNSELOR)->orWhereHas('classAssignments'))->whereHas('staffMember', fn ($q) => $q->where('is_active', true))->withCount('classAssignments')->orderBy('name')->get()
            ->filter(fn ($u) => $this->canView($u));

        return response()->json(['data' => $users->groupBy(fn ($u) => $u->dwmc ?: '未设置分院')->map(fn ($users, $college) => [
            'college' => $college, 'count' => $users->count(), 'counselors' => $users->map(fn ($u) => $this->payload($u))->values(),
        ])->values(), 'admin' => $this->management->admin(), 'can_delegate' => (bool) CurrentUser::get()?->isSuperAdmin(), 'can_manage' => $this->management->admin() || (bool) $this->management->permission(),
            'colleges' => collect($this->management->colleges())->filter(fn ($c) => $this->management->allowed($c['code']))->values()]);
    }

    public function show(User $user)
    {
        $this->viewable($user);

        $assignments = $user->classAssignments()->orderBy('class_name')->get()
            ->filter(fn ($row) => $this->management->allowed($row->college_code) || CurrentUser::get()?->id === $user->id)->values();

        return response()->json(['data' => $this->payload($user) + ['assignments' => $assignments]]);
    }

    public function staffOptions(Request $request)
    {
        abort_unless($this->management->admin() || $this->management->permission(), 403);
        $q = trim((string) $request->query('q', ''));
        $permission = $this->management->permission();
        $staff = StaffMember::query()->where('is_active', true)->whereNotNull('department_code')
            ->whereDoesntHave('user', fn ($query) => $query->where('role', '!=', User::ROLE_STAFF)->orWhereHas('classAssignments'))
            ->when(! $this->management->admin() && ! $permission?->all_colleges,
                fn ($query) => $query->whereIn('department_code', $permission?->department_codes ?? []))
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$q.'%')->orWhere('employee_no', 'like', '%'.$q.'%')))
            ->orderBy('name')->limit(200)->get();

        return response()->json(['data' => $staff->values()->map(fn ($row) => ['employee_no' => $row->employee_no, 'name' => $row->name,
            'department_code' => $row->department_code, 'department_name' => $row->department_name])]);
    }

    public function delegations()
    {
        abort_unless($this->management->admin(), 403);

        return response()->json(['data' => CounselorCollegeDelegation::with('user:id,cas_username,name')
            ->orderByDesc('is_active')->orderBy('college_name')->get()]);
    }

    public function grantDelegation(Request $request)
    {
        abort_unless($this->management->admin(), 403);
        $data = $request->validate(['employee_no' => ['required', 'string'], 'college_code' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500']]);
        $staff = StaffMember::where('employee_no', $data['employee_no'])->where('is_active', true)->first();
        abort_unless($staff, 422, '工号不在在职目录中');
        $college = collect($this->management->colleges())->firstWhere('code', $data['college_code']);
        abort_unless($college && $staff->department_code !== $data['college_code'], 422, '请选择与目录所属部门不同的有效学院');

        return DB::transaction(function () use ($data, $college) {
            $existing = CounselorCollegeDelegation::where('employee_no', $data['employee_no'])
                ->where('college_code', $data['college_code'])->lockForUpdate()->first();
            $before = $existing?->toArray();
            $delegation = CounselorCollegeDelegation::updateOrCreate(
                ['employee_no' => $data['employee_no'], 'college_code' => $data['college_code']],
                ['college_name' => $college['name'], 'reason' => $data['reason'] ?? null, 'is_active' => true, 'ended_at' => null]
            );
            $this->management->log('delegation.grant', $data['employee_no'], $before, $delegation->toArray());

            return response()->json(['data' => $delegation], $before ? 200 : 201);
        });
    }

    public function endDelegation(CounselorCollegeDelegation $delegation)
    {
        abort_unless($this->management->admin(), 403);

        return DB::transaction(function () use ($delegation) {
            $delegation = CounselorCollegeDelegation::whereKey($delegation->id)->lockForUpdate()->firstOrFail();
            abort_unless($delegation->is_active, 409, '委派已结束');
            abort_if(CounselorClassAssignment::where('counselor_cas_username', $delegation->employee_no)
                ->where('college_code', $delegation->college_code)->exists(), 409, '请先转交或移除该学院的带班');
            $before = $delegation->toArray();
            $delegation->update(['is_active' => false, 'ended_at' => now()]);
            $this->management->log('delegation.end', $delegation->employee_no, $before, $delegation->toArray());

            return response()->json(['data' => $delegation]);
        });
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'cas_username' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'], 'dwbm' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'], 'office_phone' => ['nullable', 'string', 'max:255'], 'office_location' => ['nullable', 'string', 'max:255'],
        ]);
        if (filled($data['dwbm'] ?? null)) $this->management->authorize($data['dwbm']);
        $staff = StaffMember::where('employee_no', $data['cas_username'])->where('is_active', true)->first();
        abort_unless($staff && filled($staff->department_code), 422, '工号不在当前在职教职工目录中');
        $this->management->authorize($staff->department_code);
        abort_if(filled($data['dwbm'] ?? null) && $data['dwbm'] !== $staff->department_code, 422, '学院与在职目录不符');
        if ($user) {
            $this->management->authorize($user->dwbm);
            abort_unless($data['cas_username'] === $user->cas_username, 422, '工号由在职目录管理，不能在此修改');
        }
        $data['name'] = $staff->name;
        $data['dwbm'] = $staff->department_code;
        $data['dwmc'] = $staff->department_name;

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return DB::transaction(function () use ($data) {
            $user = User::where('cas_username', $data['cas_username'])->lockForUpdate()->first();
            abort_if($user && ($user->role !== User::ROLE_STAFF || $user->classAssignments()->exists()), 422, '该工号已有关联账号或带班关系');
            if ($user) {
                $user->fill($data)->forceFill(['role' => User::ROLE_COUNSELOR])->save();
            } else {
                $user = User::create($data + ['role' => User::ROLE_COUNSELOR, 'email' => $data['cas_username'].'@counselor.local', 'password' => Str::random(40)]);
            }
            $this->management->log('user.create', $user->cas_username, null, $data);

            return response()->json(['data' => $this->payload($user)], 201);
        });
    }

    public function update(Request $request, User $user)
    {
        $this->viewable($user);
        $data = $this->validated($request, $user);

        return DB::transaction(function () use ($user, $data) {
            $before = $user->only(array_keys($data));
            $old = $user->cas_username;
            $user->fill($data)->save();
            if ($old !== $user->cas_username) {
                CounselorClassAssignment::where('counselor_cas_username', $old)->update(['counselor_cas_username' => $user->cas_username]);
            }
            $this->management->log('user.update', $user->cas_username, $before, $data);

            return response()->json(['data' => $this->payload($user)]);
        });
    }

    public function destroy(User $user, CounselorClassCatalog $catalog)
    {
        abort_unless($this->management->admin() && $user->isCounselor(), 403);
        DB::transaction(function () use ($user, $catalog) {
            $this->management->log('user.delete', $user->cas_username, ['user' => $this->payload($user), 'assignments' => $user->classAssignments()->get()->toArray()], null);
            $user->classAssignments()->delete();
            foreach ($user->counselorDelegations()->where('is_active', true)->get() as $delegation) {
                $before = $delegation->toArray();
                $delegation->update(['is_active' => false, 'ended_at' => now()]);
                $this->management->log('delegation.end.counselor_removed', $user->cas_username, $before, $delegation->toArray());
            }
            $user->forceFill(['role' => User::ROLE_STAFF])->save();
            $catalog->forget();
        });

        return response()->json(['message' => '辅导员身份已移除']);
    }

    public function classes(Request $request, CounselorClassCatalog $catalog, CounselorClassOwnership $ownership)
    {
        $target = $request->filled('counselor_id') ? User::findOrFail($request->integer('counselor_id')) : null;
        if ($target) {
            $this->viewable($target);
        }
        $start = now()->year - (now()->month >= 9 ? 3 : 4);
        $grades = collect(range($start, $start + 3))->map(fn ($y) => substr((string) $y, -2))->all();
        $q = mb_strtolower(trim((string) $request->query('q')));
        $data = $catalog->all()->filter(function ($row) use ($target, $request, $grades, $q) {
            if ($target && (blank($row['college_code']) || ! app(CounselorCollegeEligibility::class)->allows($target, $row['college_code'])
                || ! $this->management->allowed($row['college_code'])
                || ($target->dwbm !== $row['college_code'] && blank($row['class_code'])))) {
                return false;
            }
            if (! $target && ! $this->management->allowed($row['college_code'])) {
                return false;
            }
            $grade = $request->query('grade', 'recent');
            if ($grade === 'recent' && ! in_array($row['grade'], $grades, true)) {
                return false;
            }
            if (! in_array($grade, ['recent', 'all'], true) && $row['grade'] !== substr($grade, -2)) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($row['class_name'].' '.$row['class_code']), $q);
        })->sortByDesc('grade')->values();

        if ($this->management->admin() || $this->management->permission()) {
            $assignments = CounselorClassAssignment::query()->get(['college_code', 'class_code', 'class_name', 'normalized_class_name', 'counselor_cas_username']);
            $names = User::whereIn('cas_username', $assignments->pluck('counselor_cas_username'))->pluck('name', 'cas_username');
            $data = $data->map(function ($row) use ($assignments, $names, $ownership) {
                $row['owner_names'] = $assignments->filter(fn ($assignment) => $ownership->matches($row, $assignment))
                    ->map(fn ($assignment) => $names[$assignment->counselor_cas_username] ?? $assignment->counselor_cas_username)->unique()->values()->all();

                return $row;
            });
        }

        return response()->json(['data' => $data]);
    }

    public function unassignedClasses(Request $request, UnassignedCounselorClasses $classes)
    {
        abort_unless($this->management->admin() || $this->management->permission(), 403);
        $filters = $request->validate([
            'college' => ['nullable', 'string'], 'grade' => ['nullable', 'regex:/^(all|\d{4})$/'], 'q' => ['nullable', 'string', 'max:255'],
        ]);
        if (filled($filters['college'] ?? null)) {
            $this->management->authorize($filters['college']);
        }
        $query = mb_strtolower(trim($filters['q'] ?? ''));
        $data = $classes->all()->filter(fn ($row) => $this->management->allowed($row['college_code'])
            && (blank($filters['college'] ?? null) || $row['college_code'] === $filters['college'])
            && (blank($filters['grade'] ?? null) || $filters['grade'] === 'all' || $row['grade'] === $filters['grade'])
            && ($query === '' || str_contains(mb_strtolower($row['class_name'].' '.($row['class_code'] ?? '')), $query)))->values();

        return response()->json(['data' => $data, 'total' => $data->count()]);
    }

    public function bindUnassignedClass(Request $request, UnassignedCounselorClasses $classes, CounselorClassCatalog $catalog, CounselorClassOwnership $ownership)
    {
        abort_unless($this->management->admin() || $this->management->permission(), 403);
        $data = $request->validate(['class_key' => ['required', 'string', 'max:512'], 'counselor_id' => ['required', 'integer', 'exists:users,id']]);
        $user = User::findOrFail($data['counselor_id']);
        abort_unless($user->isCounselor() || $user->classAssignments()->exists(), 422, '请选择辅导员');

        $assignment = DB::transaction(function () use ($data, $user, $classes, $ownership) {
            $class = $classes->all()->firstWhere('key', $data['class_key']);
            abort_unless($class, 409, '该班级已绑定辅导员或不再属于当前在校班级，请刷新清单');
            $this->management->authorize($class['college_code']);
            abort_if(! $this->management->admin() && ! app(CounselorCollegeEligibility::class)->allows($user, $class['college_code']), 403);
            abort_unless(app(CounselorCollegeEligibility::class)->allows($user, $class['college_code']), 422, '该辅导员没有此学院委派');
            $students = Student::query()->where('student_category', Student::CATEGORY_CURRENT)
                ->where('rylx', '0')->where('dwbm', $class['college_code']);
            if ($class['class_code'] !== null) {
                $students->where('bjbm', $class['class_code']);
            } else {
                $students->where(fn ($query) => $query->whereNull('bjbm')->orWhere('bjbm', ''))->where('bjmc', $class['class_name']);
            }
            $students->orderBy('xgh')->lockForUpdate()->first();
            $class = $classes->all()->firstWhere('key', $data['class_key']);
            abort_unless($class, 409, '该班级已绑定辅导员或不再属于当前在校班级，请刷新清单');
            return $ownership->assign($user, $class);
        });
        $catalog->forget();

        return response()->json(['data' => $assignment], 201);
    }

    public function addClass(Request $request, User $user, CounselorClassCatalog $catalog, CounselorClassOwnership $ownership)
    {
        $this->viewable($user);
        $data = $request->validate(['class_name' => ['required', 'string', 'max:255'], 'class_code' => ['nullable', 'string', 'max:255'],
            'college_code' => ['nullable', 'string'],
            'transfer' => ['nullable', 'boolean'], 'expected_owner_ids' => ['required_if:transfer,true', 'array']]);
        $match = $catalog->all()->first(fn ($row) => filled($row['college_code']) && app(CounselorCollegeEligibility::class)->allows($user, $row['college_code'])
            && $this->management->allowed($row['college_code']) && $row['class_name'] === $data['class_name']
            && (blank($data['college_code'] ?? null) || $row['college_code'] === $data['college_code'])
            && (blank($data['class_code'] ?? null) || $row['class_code'] === $data['class_code']));
        abort_unless($match, 422, '班级不存在或不属于该学院');

        return DB::transaction(function () use ($user, $match, $catalog, $ownership, $request, $data) {
            $assignment = $ownership->assign($user, $match, $request->boolean('transfer'), $data['expected_owner_ids'] ?? null);
            $catalog->forget();

            return response()->json(['data' => $assignment], 201);
        });
    }

    public function addClasses(Request $request, User $user, CounselorClassCatalog $catalog, CounselorClassOwnership $ownership)
    {
        $this->viewable($user);
        $data = $request->validate([
            'classes' => ['required', 'array', 'min:1', 'max:100'], 'classes.*.class_name' => ['required', 'string', 'max:255'],
            'classes.*.college_code' => ['nullable', 'string'],
            'classes.*.class_code' => ['nullable', 'string', 'max:255'], 'transfer' => ['nullable', 'boolean'],
            'expected_owners' => ['nullable', 'array'],
        ]);
        $options = $catalog->all();
        $classes = collect($data['classes'])->map(function ($item) use ($options, $user) {
            $match = $options->first(fn ($row) => filled($row['college_code']) && app(CounselorCollegeEligibility::class)->allows($user, $row['college_code'])
                && $this->management->allowed($row['college_code']) && $row['class_name'] === $item['class_name']
                && (blank($item['college_code'] ?? null) || $row['college_code'] === $item['college_code'])
                && (blank($item['class_code'] ?? null) || $row['class_code'] === $item['class_code']));
            abort_unless($match, 422, '班级不存在或不属于该学院');

            return $match;
        })->unique('key')->values();

        $assignments = DB::transaction(function () use ($classes, $user, $ownership, $request, $data) {
            $conflicts = $classes->map(function ($class) use ($ownership, $user) {
                $allOwners = $ownership->owners($class, true);
                $owners = $allOwners->filter(fn ($row) => $row->counselor_cas_username !== $user->cas_username);
                if ($owners->isEmpty()) return null;
                $names = User::whereIn('cas_username', $owners->pluck('counselor_cas_username'))->pluck('name', 'cas_username');

                return ['class_name' => $class['class_name'], 'key' => $ownership->identity($class),
                    'owner_ids' => $allOwners->pluck('id')->all(),
                    'owners' => $owners->map(fn ($row) => ['id' => $row->id, 'name' => $names[$row->counselor_cas_username] ?? $row->counselor_cas_username])->values()->all()];
            })->filter()->values();
            if ($conflicts->isNotEmpty() && ! $request->boolean('transfer')) {
                return response()->json(['message' => '以下班级已有辅导员，请确认转交', 'conflicts' => $conflicts], 409);
            }
            if ($request->boolean('transfer')) {
                foreach ($classes as $class) {
                    $key = $ownership->identity($class);
                    $actual = $ownership->owners($class, true)->pluck('id')->sort()->values()->all();
                    if ($actual !== []) {
                        abort_unless(isset($data['expected_owners'][$key]) && collect($data['expected_owners'][$key])->sort()->values()->all() === $actual, 409, '带班关系已变化，请刷新后重试');
                    }
                }
            }

            return $classes->map(fn ($class) => $ownership->assign($user, $class, $request->boolean('transfer')))->all();
        });
        if ($assignments instanceof \Illuminate\Http\JsonResponse) return $assignments;
        $catalog->forget();

        return response()->json(['data' => $assignments], 201);
    }

    public function conflicts(CounselorClassOwnership $ownership)
    {
        abort_unless($this->management->admin() || $this->management->permission(), 403);

        return response()->json(['data' => $ownership->conflicts()->filter(fn ($row) => $this->management->allowed($row['college_code']))->values()]);
    }

    public function resolveConflict(Request $request, CounselorClassOwnership $ownership)
    {
        abort_unless($this->management->admin() || $this->management->permission(), 403);
        $data = $request->validate(['keep_assignment_id' => ['required', 'integer'], 'assignment_ids' => ['required', 'array', 'min:2'], 'assignment_ids.*' => ['integer']]);
        $keep = CounselorClassAssignment::findOrFail($data['keep_assignment_id']);
        $this->management->authorize($keep->college_code);
        $ownership->resolve($keep->id, $data['assignment_ids']);

        return response()->json(['message' => '重复带班关系已处理']);
    }

    public function removeClass(User $user, CounselorClassAssignment $assignment, CounselorClassCatalog $catalog)
    {
        $this->management->authorize($assignment->college_code);
        abort_unless($assignment->counselor_cas_username === $user->cas_username, 404);
        DB::transaction(function () use ($user, $assignment, $catalog) {
            $this->management->log('class.remove', $user->cas_username, $assignment->toArray(), null);
            $assignment->delete();
            $catalog->forget();
        });

        return response()->json(['message' => '带班关系已移除']);
    }

    public function matchClass(Request $request, User $user, CounselorClassAssignment $assignment, CounselorClassCatalog $catalog, CounselorClassOwnership $ownership)
    {
        $this->management->authorize($assignment->college_code);
        abort_unless(app(CounselorCollegeEligibility::class)->allows($user, $assignment->college_code), 422, '该辅导员没有此学院委派');
        abort_unless($assignment->counselor_cas_username === $user->cas_username, 404);
        $data = $request->validate(['class_code' => ['required', 'string'], 'transfer' => ['nullable', 'boolean'], 'expected_owner_ids' => ['required_if:transfer,true', 'array']]);
        $match = $catalog->all()->first(fn ($row) => $row['class_code'] === $data['class_code'] && $row['college_code'] === $assignment->college_code);
        abort_unless($match, 422, '请选择本学院正式班级');

        return DB::transaction(function () use ($user, $assignment, $match, $catalog, $ownership, $request, $data) {
            $owners = $ownership->owners($match, true);
            $others = $owners->reject(fn ($row) => $row->id === $assignment->id);
            if ($others->isNotEmpty() && ! $request->boolean('transfer')) {
                $names = User::whereIn('cas_username', $others->pluck('counselor_cas_username'))->pluck('name', 'cas_username');

                return response()->json(['message' => '该班级已有辅导员', 'conflicts' => [[
                    'class_name' => $match['class_name'], 'key' => $ownership->identity($match),
                    'owners' => $owners->map(fn ($row) => ['id' => $row->id, 'name' => $names[$row->counselor_cas_username] ?? $row->counselor_cas_username])->values()->all(),
                ]]], 409);
            }
            if ($request->boolean('transfer')) {
                abort_unless($owners->pluck('id')->sort()->values()->all() === collect($data['expected_owner_ids'])->sort()->values()->all(), 409, '带班关系已变化，请刷新后重试');
            }
            $others->each->delete();
            $before = $assignment->toArray();
            $assignment->update(['class_code' => $match['class_code'], 'class_identity_hash' => $ownership->identity($match)]);
            $this->management->log($others->isNotEmpty() ? 'class.transfer' : 'class.match', $user->cas_username, $before, $assignment->toArray());
            $catalog->forget();

            return response()->json(['data' => $assignment]);
        });
    }

    public function import(Request $request, CounselorWorkbookImport $import)
    {
        abort_unless($this->management->admin() || $this->management->permission(), 403);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'], 'commit' => ['nullable', 'boolean']]);
        $path = $request->file('file')->getRealPath();

        return response()->json($request->boolean('commit') ? $import->commit($path) : $import->preview($path));
    }

    private function payload(User $user): array
    {
        $codes = app(CounselorCollegeEligibility::class)->codes($user);
        $manageable = collect($codes)->filter(fn ($code) => $this->management->allowed($code))->values()->all();
        return $user->only(['id', 'cas_username', 'name', 'dwbm', 'dwmc', 'phone', 'office_phone', 'office_location']) + [
            'class_assignments_count' => $this->management->admin()
                ? ($user->class_assignments_count ?? $user->classAssignments()->count())
                : $user->classAssignments()->whereIn('college_code', $manageable)->count(),
            'can_manage' => $manageable !== [], 'can_manage_profile' => $this->management->allowed($user->dwbm),
            'managed_college_codes' => $manageable,
            'delegated_colleges' => $user->counselorDelegations()->where('is_active', true)->get(['college_code', 'college_name']),
            'can_delete' => $this->management->admin() && $user->isCounselor(),
        ];
    }
}
