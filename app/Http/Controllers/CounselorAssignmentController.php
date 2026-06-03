<?php

namespace App\Http\Controllers;

use App\Models\CounselorClassAssignment;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CounselorAssignmentController extends Controller
{
    public function page()
    {
        return view('counselor-assignments');
    }

    public function index(): JsonResponse
    {
        $query = User::query()
            ->where('role', User::ROLE_COUNSELOR)
            ->withCount('classAssignments')
            ->orderBy('dwmc')
            ->orderBy('name');

        $user = CurrentUser::get();
        if (config('cas.enabled') && $user?->isCounselor()) {
            $query->whereKey($user->id);
        }

        $groups = $query->get()
            ->groupBy(fn (User $user) => $user->dwmc ?: '未设置分院')
            ->map(fn ($users, string $college) => [
                'college' => $college,
                'count' => $users->count(),
                'counselors' => $users->map(fn (User $user) => $this->userPayload($user))->values(),
            ])
            ->values();

        return response()->json(['data' => $groups]);
    }

    public function show(User $user): JsonResponse
    {
        abort_unless($user->isCounselor(), 404);
        $this->authorizeView($user);

        $user->load(['classAssignments' => fn ($query) => $query->orderBy('class_name')]);

        return response()->json([
            'data' => array_merge($this->userPayload($user), [
                'assignments' => $user->classAssignments->map(fn (CounselorClassAssignment $assignment) => $this->assignmentPayload($assignment))->values(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'cas_username' => ['required', 'string', 'max:255', 'unique:users,cas_username'],
            'name' => ['required', 'string', 'max:255'],
            'dwmc' => ['nullable', 'string', 'max:255'],
            'dwbm' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'office_phone' => ['nullable', 'string', 'max:255'],
            'office_location' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->create([
            'cas_username' => $validated['cas_username'],
            'name' => $validated['name'],
            'email' => $validated['cas_username'].'@counselor.local',
            'password' => Hash::make(Str::random(32)),
            'role' => User::ROLE_COUNSELOR,
            'dwmc' => $validated['dwmc'] ?? null,
            'dwbm' => $validated['dwbm'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'office_phone' => $validated['office_phone'] ?? null,
            'office_location' => $validated['office_location'] ?? null,
        ]);

        return response()->json(['data' => $this->userPayload($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();
        abort_unless($user->isCounselor(), 404);

        $validated = $request->validate([
            'cas_username' => ['required', 'string', 'max:255', Rule::unique('users', 'cas_username')->ignore($user->id)],
            'name' => ['required', 'string', 'max:255'],
            'dwmc' => ['nullable', 'string', 'max:255'],
            'dwbm' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'office_phone' => ['nullable', 'string', 'max:255'],
            'office_location' => ['nullable', 'string', 'max:255'],
        ]);

        $originalCasUsername = $user->cas_username;
        $user->fill($validated);
        $user->role = User::ROLE_COUNSELOR;
        $user->email = $validated['cas_username'].'@counselor.local';
        $user->save();

        if ($originalCasUsername !== $user->cas_username) {
            CounselorClassAssignment::query()
                ->where(function ($query) use ($originalCasUsername, $user) {
                    $query->where('user_id', $user->id);

                    if (filled($originalCasUsername)) {
                        $query->orWhere('counselor_cas_username', $originalCasUsername);
                    }
                })
                ->update(['counselor_cas_username' => $user->cas_username]);
        }

        return response()->json(['data' => $this->userPayload($user)]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorizeAdmin();
        abort_unless($user->isCounselor(), 404);
        $user->delete();

        return response()->json(['message' => '辅导员已删除']);
    }

    public function classes(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $target = null;

        if ($request->filled('counselor_id')) {
            $target = User::query()->findOrFail($request->integer('counselor_id'));
            abort_unless($target->isCounselor(), 404);
            $this->authorizeView($target);
        } elseif (config('cas.enabled') && CurrentUser::get()?->isCounselor()) {
            $target = CurrentUser::get();
        }

        $cohortStartYear = (int) now()->year - (now()->month >= 9 ? 3 : 4);
        $cohorts = collect(range($cohortStartYear, $cohortStartYear + 3))
            ->map(fn (int $year) => substr((string) $year, -2))
            ->values();

        $classes = Student::query()
            ->where('rylx', '0')
            ->whereNotNull('bjmc')
            ->where('bjmc', '!=', '')
            ->when($target?->dwbm, fn ($query) => $query->where('dwbm', $target->dwbm))
            ->when(! $target?->dwbm && $target?->dwmc, function ($query) use ($target) {
                $query->where(function ($subQuery) use ($target) {
                    $subQuery->where('dwmc', $target->dwmc)
                        ->orWhere('dwmc', 'like', "%{$target->dwmc}%");
                });
            })
            ->where(function ($query) use ($cohorts) {
                foreach ($cohorts as $cohort) {
                    $query->orWhere('bjmc', 'like', "{$cohort}%")
                        ->orWhere('bjbm', 'like', "{$cohort}%");
                }
            })
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('bjmc', 'like', "%{$keyword}%")
                        ->orWhere('bjbm', 'like', "%{$keyword}%");
                });
            })
            ->selectRaw('COALESCE(NULLIF(bjbm, ""), bjmc) as class_code, bjmc as class_name, MAX(dwmc) as college_name, MAX(dwbm) as college_code, COUNT(*) as student_count')
            ->groupBy('bjbm', 'bjmc')
            ->limit(300)
            ->get()
            ->sort(function ($left, $right) {
                $grade = strcmp(substr((string) $right->class_name, 0, 2), substr((string) $left->class_name, 0, 2));

                return $grade !== 0 ? $grade : strcmp((string) $left->class_name, (string) $right->class_name);
            })
            ->values();

        return response()->json([
            'data' => $classes->map(fn ($class) => [
                'class_code' => $class->class_code,
                'class_name' => $class->class_name,
                'grade' => substr((string) $class->class_name, 0, 2),
                'college_code' => $class->college_code,
                'college_name' => $class->college_name,
                'student_count' => (int) $class->student_count,
            ]),
        ]);
    }

    public function addClass(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();
        abort_unless($user->isCounselor(), 404);

        $validated = $request->validate([
            'class_code' => ['nullable', 'string', 'max:255'],
            'class_name' => ['required', 'string', 'max:255'],
        ]);

        $class = Student::query()
            ->where('rylx', '0')
            ->when(filled($validated['class_code'] ?? null), fn ($query) => $query->where('bjbm', $validated['class_code']))
            ->where('bjmc', $validated['class_name'])
            ->select('bjbm', 'bjmc')
            ->first();

        $assignment = CounselorClassAssignment::query()->updateOrCreate(
            [
                'counselor_cas_username' => $user->cas_username,
                'normalized_class_name' => CounselorClassAssignment::normalizeClassName($validated['class_name']),
            ],
            [
                'user_id' => $user->id,
                'class_code' => $class?->bjbm ?: ($validated['class_code'] ?? null),
                'class_name' => $class?->bjmc ?: $validated['class_name'],
                'college_code' => $user->dwbm,
                'college_name' => $user->dwmc,
                'source' => 'manual',
            ]
        );

        return response()->json(['data' => $this->assignmentPayload($assignment)], 201);
    }

    public function removeClass(User $user, CounselorClassAssignment $assignment): JsonResponse
    {
        $this->authorizeAdmin();
        abort_unless(
            $user->isCounselor()
            && (
                (string) $assignment->counselor_cas_username === (string) $user->cas_username
                || (int) $assignment->user_id === (int) $user->id
            ),
            404
        );
        $assignment->delete();

        return response()->json(['message' => '带班关系已移除']);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(! config('cas.enabled') || (bool) CurrentUser::get()?->isAdmin(), 403);
    }

    private function authorizeView(User $target): void
    {
        $user = CurrentUser::get();
        if (! config('cas.enabled') || $user?->isAdmin()) {
            return;
        }

        abort_unless($user?->isCounselor() && (int) $user->id === (int) $target->id, 403);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'cas_username' => $user->cas_username,
            'name' => $user->name,
            'dwbm' => $user->dwbm,
            'dwmc' => $user->dwmc,
            'phone' => $user->phone,
            'office_phone' => $user->office_phone,
            'office_location' => $user->office_location,
            'class_assignments_count' => $user->class_assignments_count ?? $user->classAssignments()->count(),
        ];
    }

    private function assignmentPayload(CounselorClassAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'counselor_cas_username' => $assignment->counselor_cas_username,
            'class_code' => $assignment->class_code,
            'class_name' => $assignment->class_name,
            'college_code' => $assignment->college_code,
            'college_name' => $assignment->college_name,
            'source' => $assignment->source,
        ];
    }
}
