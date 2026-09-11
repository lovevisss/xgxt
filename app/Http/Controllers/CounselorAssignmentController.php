<?php

namespace App\Http\Controllers;

use App\Models\CounselorClassAssignment;
use App\Models\User;
use App\Services\CounselorClassCatalog;
use App\Services\CounselorManagement;
use App\Services\CounselorWorkbookImport;
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

    private function validated(Request $request, User $user = null): array
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

    public function classes(Request $request, CounselorClassCatalog $catalog)
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

        return response()->json(['data' => $data]);
    }

    public function addClass(Request $request, User $user, CounselorClassCatalog $catalog)
    {
        $this->viewable($user);
        $this->management->authorize($user->dwbm);
        $data = $request->validate(['class_name' => ['required', 'string', 'max:255'], 'class_code' => ['nullable', 'string', 'max:255']]);
        $match = $catalog->all()->first(fn ($row) => $row['college_code'] === $user->dwbm && $row['class_name'] === $data['class_name'] && (blank($data['class_code'] ?? null) || $row['class_code'] === $data['class_code']));
        abort_unless($match, 422, '班级不存在或不属于该学院');

        return DB::transaction(function () use ($user, $match, $catalog) {
            $key = ['counselor_cas_username' => $user->cas_username, 'normalized_class_name' => CounselorClassAssignment::normalizeClassName($match['class_name'])];
            $before = CounselorClassAssignment::where($key)->first()?->toArray();
            $assignment = CounselorClassAssignment::updateOrCreate($key, ['user_id' => $user->id, 'class_code' => $match['class_code'], 'class_name' => $match['class_name'], 'college_code' => $user->dwbm, 'college_name' => $user->dwmc, 'source' => 'manual']);
            $this->management->log('class.save', $user->cas_username, $before, $assignment->toArray());
            $catalog->forget();

            return response()->json(['data' => $assignment], 201);
        });
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

    public function matchClass(Request $request, User $user, CounselorClassAssignment $assignment, CounselorClassCatalog $catalog)
    {
        $this->management->authorize($user->dwbm);
        abort_unless($assignment->counselor_cas_username === $user->cas_username, 404);
        $data = $request->validate(['class_code' => ['required', 'string']]);
        $match = $catalog->all()->first(fn ($row) => $row['class_code'] === $data['class_code'] && $row['college_code'] === $user->dwbm);
        abort_unless($match, 422, '请选择本学院正式班级');

        return DB::transaction(function () use ($user, $assignment, $match, $catalog) {
            $before = $assignment->toArray();
            $assignment->update(['class_code' => $match['class_code']]);
            $this->management->log('class.match', $user->cas_username, $before, $assignment->toArray());
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
