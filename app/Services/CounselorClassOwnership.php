<?php

namespace App\Services;

use App\Models\CounselorClassAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CounselorClassOwnership
{
    public function identity(array|CounselorClassAssignment $class): string
    {
        $get = fn ($field) => is_array($class) ? ($class[$field] ?? null) : $class->$field;
        $code = trim((string) $get('class_code'));

        return hash('sha256', json_encode([
            $get('college_code'), $code !== '' ? 'code' : 'name',
            $code !== '' ? $code : CounselorClassAssignment::normalizeClassName($get('class_name') ?? $get('normalized_class_name')),
        ], JSON_UNESCAPED_UNICODE));
    }

    public function matches(array|CounselorClassAssignment $left, array|CounselorClassAssignment $right): bool
    {
        $get = fn ($row, $field) => is_array($row) ? ($row[$field] ?? null) : $row->$field;
        if ($get($left, 'college_code') !== $get($right, 'college_code')) {
            return false;
        }
        $leftCode = trim((string) $get($left, 'class_code'));
        $rightCode = trim((string) $get($right, 'class_code'));
        if ($leftCode !== '' && $rightCode !== '') {
            return $leftCode === $rightCode;
        }

        return CounselorClassAssignment::normalizeClassName($get($left, 'class_name') ?? $get($left, 'normalized_class_name'))
            === CounselorClassAssignment::normalizeClassName($get($right, 'class_name') ?? $get($right, 'normalized_class_name'));
    }

    public function owners(array $class, bool $lock = false): Collection
    {
        $query = CounselorClassAssignment::query()->where('college_code', $class['college_code']);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->filter(fn ($assignment) => $this->matches($class, $assignment))->values();
    }

    public function ambiguous(array $class): bool
    {
        $normalized = CounselorClassAssignment::normalizeClassName($class['class_name']);

        return app(CounselorClassCatalog::class)->all()
            ->filter(fn ($row) => $row['college_code'] === $class['college_code']
                && CounselorClassAssignment::normalizeClassName($row['class_name']) === $normalized
                && filled($row['class_code']))->pluck('class_code')->unique()->count() > 1;
    }

    public function conflicts(): Collection
    {
        $rows = CounselorClassAssignment::query()->with('user:id,cas_username,name')->orderBy('id')->get();
        $codesByName = $rows->filter(fn ($row) => filled($row->class_code))
            ->groupBy(fn ($row) => $row->college_code.'|'.$row->normalized_class_name)
            ->map(fn ($group) => $group->pluck('class_code')->unique()->values());
        return $rows->groupBy(function ($row) use ($codesByName) {
            if (filled($row->class_code)) return $row->college_code.'|code:'.$row->class_code;
            $codes = $codesByName->get($row->college_code.'|'.$row->normalized_class_name, collect());

            return $codes->count() === 1 ? $row->college_code.'|code:'.$codes->first() : $row->college_code.'|name:'.$row->normalized_class_name;
        })
            ->filter(fn ($group) => $group->count() > 1)
            ->map(function ($group, $key) {
                $first = $group->first();

                return ['key' => $key, 'college_code' => $first->college_code, 'college_name' => $first->college_name,
                    'class_code' => $first->class_code, 'class_name' => $first->class_name,
                    'assignments' => $group->map(fn ($row) => ['id' => $row->id, 'user_id' => $row->user_id,
                        'name' => $row->user?->name, 'cas_username' => $row->counselor_cas_username,
                        'source' => $row->source])->values()->all()];
            })->values();
    }

    public function assign(User $user, array $class, bool $transfer = false, ?array $expectedOwners = null, string $source = 'manual'): CounselorClassAssignment
    {
        abort_unless($user->dwbm === $class['college_code'], 422, '只能绑定同学院辅导员');
        $owners = $this->owners($class, true);
        if ($this->ambiguous($class) && (blank($class['class_code']) || $owners->contains(fn ($row) => blank($row->class_code)))) {
            throw ValidationException::withMessages(['class_name' => '同名班级有多个正式代码，请先明确关联班级']);
        }
        $otherOwners = $owners->filter(fn ($row) => $row->counselor_cas_username !== $user->cas_username);
        if ($otherOwners->isNotEmpty() && ! $transfer) {
            $names = User::whereIn('cas_username', $otherOwners->pluck('counselor_cas_username'))->pluck('name', 'cas_username');
            throw new HttpResponseException(response()->json([
                'message' => '该班级已由 '.($names->values()->implode('、') ?: '其他辅导员').' 带班',
                'conflicts' => [['class_name' => $class['class_name'], 'key' => $this->identity($class),
                    'owner_ids' => $owners->pluck('id')->all(),
                    'owners' => $otherOwners->map(fn ($row) => ['id' => $row->id, 'name' => $names[$row->counselor_cas_username] ?? $row->counselor_cas_username])->values()->all()]],
            ], 409));
        }
        if ($expectedOwners !== null && $owners->pluck('id')->sort()->values()->all() !== collect($expectedOwners)->sort()->values()->all()) {
            abort(409, '带班关系已变化，请刷新后重试');
        }
        $normalized = CounselorClassAssignment::normalizeClassName($class['class_name']);
        $own = $user->classAssignments()->where('normalized_class_name', $normalized)->first();
        if ($own && filled($own->class_code) && filled($class['class_code']) && $own->class_code !== $class['class_code']) {
            throw ValidationException::withMessages(['class_name' => '该辅导员已有同名但不同代码的班级']);
        }
        $before = $owners->map->toArray()->all();
        $otherOwners->each->delete();
        try {
            $assignment = CounselorClassAssignment::updateOrCreate(
                ['counselor_cas_username' => $user->cas_username, 'normalized_class_name' => $normalized],
                ['user_id' => $user->id, 'class_code' => $class['class_code'] ?: null, 'class_name' => $class['class_name'],
                    'college_code' => $class['college_code'], 'college_name' => $class['college_name'] ?? $user->dwmc,
                    'source' => $source, 'class_identity_hash' => $this->identity($class)]
            );
        } catch (UniqueConstraintViolationException) {
            abort(409, '带班关系已变化，请刷新后重试');
        }
        app(CounselorManagement::class)->log($otherOwners->isNotEmpty() ? 'class.transfer' : 'class.save', $user->cas_username, $before, $assignment->toArray());

        return $assignment;
    }

    public function resolve(int $keepId, array $expectedIds): void
    {
        DB::transaction(function () use ($keepId, $expectedIds) {
            $group = $this->conflicts()->first(fn ($item) => collect($item['assignments'])->pluck('id')->contains($keepId));
            abort_unless($group, 409, '该班级已无重复带班关系');
            $owners = CounselorClassAssignment::whereIn('id', array_column($group['assignments'], 'id'))->lockForUpdate()->get();
            abort_unless($owners->pluck('id')->sort()->values()->all() === collect($expectedIds)->sort()->values()->all(), 409, '带班关系已变化，请刷新后重试');
            $keep = $owners->firstWhere('id', $keepId);
            $before = $owners->map->toArray()->all();
            $owners->reject(fn ($row) => $row->id === $keepId)->each->delete();
            $keep->update(['class_identity_hash' => $this->identity($keep)]);
            app(CounselorManagement::class)->log('class.resolve', $keep->counselor_cas_username, $before, $keep->toArray());
        });
        app(CounselorClassCatalog::class)->forget();
    }
}
