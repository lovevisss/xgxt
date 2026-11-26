<?php

namespace App\Http\Controllers;

use App\Models\CounselorClassAssignment;
use App\Models\Student;
use App\Models\User;
use App\Services\CounselorClassCatalog;
use App\Services\CounselorClassOwnership;
use App\Services\CounselorManagement;
use App\Services\CounselorWorkbookImport;
use App\Services\UnassignedCounselorClasses;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        abort_unless($this->management->allowed($user->dwbm) || (CurrentUser::get()?->isCounselor() && $user->id === CurrentUser::get()?->id), 403);
        abort_unless($user->isCounselor() || $user->classAssignments()->exists(), 404);
    }

    public function index()
    {
        $users = User::where(fn ($q) => $q->where('role', User::ROLE_COUNSELOR)->orWhereHas('classAssignments'))->withCount('classAssignments')->orderBy('name')->get()
            ->filter(fn ($u) => $this->management->allowed($u->dwbm) || (CurrentUser::get()?->isCounselor() && $u->id === CurrentUser::get()?->id));

        return response()->json(['data' => $users->groupBy(fn ($u) => $u->dwmc ?: '未设置分院')->map(fn ($users, $college) => [
            'college' => $college, 'count' => $users->count(), 'counselors' => $users->map(fn ($u) => $this->payload($u))->values(),
        ])->values(), 'admin' => $this->management->admin(), 'can_delegate' => (bool) CurrentUser::get()?->isSuperAdmin(), 'can_manage' => $this->management->admin() || (bool) $this->management->permission(),
            'colleges' => collect($this->management->colleges())->filter(fn ($c) => $this->management->allowed($c['code']))->values()]);
    }

    public function show(User $user)
    {
        $this->viewable($user);

        return response()->json(['data' => $this->payload($user) + ['assignments' => $user->classAssignments()->orderBy('class_name')->get()]]);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'cas_username' => ['required', 'string', 'max:255', Rule::unique('users', 'cas_username')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:255'], 'dwbm' => ['required', 'string', Rule::in(array_column($this->management->colleges(), 'code'))],
            'phone' => ['nullable', 'string', 'max:255'], 'office_phone' => ['nullable', 'string', 'max:255'], 'office_location' => ['nullable', 'string', 'max:255'],
        ]);
        $this->management->authorize($data['dwbm']);
        if ($user) {
            $this->management->authorize($user->dwbm);
            abort_if(! $this->management->admin() && $data['cas_username'] !== $user->cas_username, 403, '工号仅管理员可修改');
            abort_if($data['dwbm'] !== $user->dwbm && $user->classAssignments()->exists(), 422, '请先移除原学院带班关系再调整学院');
        }
        $data['dwmc'] = collect($this->management->colleges())->firstWhere('code', $data['dwbm'])['name'];

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return DB::transaction(function () use ($data) {
            $user = User::create($data + ['role' => User::ROLE_COUNSELOR, 'email' => $data['cas_username'].'@counselor.local', 'password' => Str::random(40)]);
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
            $user->delete();
            $catalog->forget();
        });

        return response()->json(['message' => '辅导员已删除']);
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
            if ($target && $row['college_code'] !== $target->dwbm) {
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
        $this->management->authorize($user->dwbm);
        abort_unless($user->isCounselor() || $user->classAssignments()->exists(), 422, '请选择辅导员');

        $assignment = DB::transaction(function () use ($data, $user, $classes, $ownership) {
            $class = $classes->all()->firstWhere('key', $data['class_key']);
            abort_unless($class, 409, '该班级已绑定辅导员或不再属于当前在校班级，请刷新清单');
            $this->management->authorize($class['college_code']);
            abort_unless($class['college_code'] === $user->dwbm, 422, '只能绑定同学院辅导员');
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
        $this->management->authorize($user->dwbm);
        $data = $request->validate(['class_name' => ['required', 'string', 'max:255'], 'class_code' => ['nullable', 'string', 'max:255'],
            'transfer' => ['nullable', 'boolean'], 'expected_owner_ids' => ['required_if:transfer,true', 'array']]);
        $match = $catalog->all()->first(fn ($row) => $row['college_code'] === $user->dwbm && $row['class_name'] === $data['class_name'] && (blank($data['class_code'] ?? null) || $row['class_code'] === $data['class_code']));
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
        $this->management->authorize($user->dwbm);
        $data = $request->validate([
            'classes' => ['required', 'array', 'min:1', 'max:100'], 'classes.*.class_name' => ['required', 'string', 'max:255'],
            'classes.*.class_code' => ['nullable', 'string', 'max:255'], 'transfer' => ['nullable', 'boolean'],
            'expected_owners' => ['nullable', 'array'],
        ]);
        $options = $catalog->all();
        $classes = collect($data['classes'])->map(function ($item) use ($options, $user) {
            $match = $options->first(fn ($row) => $row['college_code'] === $user->dwbm && $row['class_name'] === $item['class_name']
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
        $this->management->authorize($user->dwbm);
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
        $this->management->authorize($user->dwbm);
        abort_unless($assignment->counselor_cas_username === $user->cas_username, 404);
        $data = $request->validate(['class_code' => ['required', 'string'], 'transfer' => ['nullable', 'boolean'], 'expected_owner_ids' => ['required_if:transfer,true', 'array']]);
        $match = $catalog->all()->first(fn ($row) => $row['class_code'] === $data['class_code'] && $row['college_code'] === $user->dwbm);
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
        return $user->only(['id', 'cas_username', 'name', 'dwbm', 'dwmc', 'phone', 'office_phone', 'office_location']) + [
            'class_assignments_count' => $user->class_assignments_count ?? $user->classAssignments()->count(),
            'can_manage' => $this->management->allowed($user->dwbm), 'can_delete' => $this->management->admin() && $user->isCounselor(),
        ];
    }
}
