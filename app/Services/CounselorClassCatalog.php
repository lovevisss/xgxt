<?php

namespace App\Services;

use App\Models\CounselorClassAssignment;
use App\Models\Student;
use App\Models\StudentClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CounselorClassCatalog
{
    private const CACHE_KEY = 'counselor-class-catalog:v2';

    public function all(): Collection
    {
        return collect(Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn () => $this->build()->all()));
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function build(): Collection
    {
        $students = Student::where('rylx', '0')->where('student_category', Student::CATEGORY_CURRENT)->whereNotNull('bjmc')->where('bjmc', '!=', '')
            ->selectRaw('bjbm as class_code, bjmc as class_name, dwbm as college_code, MAX(dwmc) as college_name, COUNT(*) as student_count')
            ->groupBy('bjbm', 'bjmc', 'dwbm')->get()->map(fn ($row) => $row->toArray());
        $assignments = CounselorClassAssignment::query()
            ->get(['class_code', 'class_name', 'college_code', 'college_name'])
            ->map(fn ($row) => array_merge($row->toArray(), ['student_count' => 0]));
        $known = $students->concat($assignments);
        $classRows = StudentClass::query()->get(['class_code', 'class_name', 'major_code']);
        $byCode = $known->filter(fn ($row) => filled($row['class_code']))->groupBy('class_code');
        $byName = $known->groupBy(fn ($row) => CounselorClassAssignment::normalizeClassName($row['class_name']));
        $majorColleges = $classRows->filter(fn ($class) => filled($class->major_code))->groupBy('major_code')->map(function ($classes) use ($byCode) {
            return $classes->flatMap(fn ($class) => $byCode->get($class->class_code, collect()))->filter(fn ($row) => filled($row['college_code']))->unique('college_code');
        });
        $archives = $classRows->flatMap(function ($class) use ($byCode, $byName, $majorColleges) {
            $matches = $byCode->get($class->class_code, collect());
            if ($matches->isEmpty()) {
                $matches = $byName->get(CounselorClassAssignment::normalizeClassName($class->class_name), collect());
                if ($matches->pluck('college_code')->filter()->unique()->count() !== 1) {
                    $matches = collect();
                }
            }
            if ($matches->isEmpty() && filled($class->major_code)) {
                $departments = $majorColleges->get($class->major_code, collect());
                if ($departments->count() === 1) {
                    $matches = $departments->map(fn ($row) => array_merge($row, ['student_count' => 0]));
                }
            }

            return [
                ['class_code' => $class->class_code, 'class_name' => $class->class_name,
                    'college_code' => $matches->first()['college_code'] ?? null, 'college_name' => $matches->first()['college_name'] ?? null,
                    'student_count' => (int) $matches->max('student_count')],
            ];
        });

        return $students->concat($archives)->concat($assignments)
            ->groupBy(fn ($row) => ($row['college_code'] ?? '').'|'.CounselorClassAssignment::normalizeClassName($row['class_name']))
            ->map(function ($rows, $key) {
                $row = $rows->first(fn ($row) => filled($row['class_code'])) ?? $rows->first();
                $row['student_count'] = (int) $rows->max('student_count');
                $row['key'] = $key;
                $row['grade'] = substr($row['class_name'], 0, 2);
                $row['matched'] = filled($row['class_code']);

                return $row;
            })->sortBy('class_name')->values();
    }
}
