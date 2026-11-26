<?php

namespace App\Services;

use App\Models\CounselorClassAssignment;
use App\Models\Student;
use App\Models\StudentClass;
use Illuminate\Support\Collection;

class UnassignedCounselorClasses
{
    public function all(): Collection
    {
        $grades = StudentClass::query()->pluck('grade', 'class_code');
        $assignments = CounselorClassAssignment::query()->get(['college_code', 'class_code', 'normalized_class_name']);
        $rows = Student::query()
            ->where('student_category', Student::CATEGORY_CURRENT)
            ->where('rylx', '0')
            ->whereNotNull('dwbm')->where('dwbm', '!=', '')
            ->whereNotNull('bjmc')->where('bjmc', '!=', '')
            ->selectRaw('dwbm as college_code, MAX(dwmc) as college_name, bjbm as class_code, bjmc as class_name, COUNT(*) as student_count')
            ->groupBy('dwbm', 'bjbm', 'bjmc')->get();

        return $rows->map(function ($row) use ($grades) {
            $code = trim((string) $row->class_code);
            $name = trim((string) $row->class_name);
            $college = trim((string) $row->college_code);
            $normalized = CounselorClassAssignment::normalizeClassName($name);
            $grade = $grades->get($code);
            if (! preg_match('/^\d{4}$/', (string) $grade)) {
                $grade = preg_match('/^(\d{2})/', $name, $match) ? '20'.$match[1] : null;
            }

            return [
                'key' => $college.'|'.($code !== '' ? 'code:'.$code : 'name:'.$normalized),
                'college_code' => $college,
                'college_name' => trim((string) $row->college_name),
                'class_code' => $code !== '' ? $code : null,
                'class_name' => $name,
                'normalized_class_name' => $normalized,
                'grade' => $grade,
                'student_count' => (int) $row->student_count,
            ];
        })->filter(fn ($row) => $row['normalized_class_name'] !== '')
            ->groupBy('key')->map(function ($group) {
                $row = $group->first();
                $row['student_count'] = $group->sum('student_count');

                return $row;
            })->values()
            ->reject(fn ($row) => $assignments->contains(fn ($assignment) => app(CounselorClassOwnership::class)->matches($row, $assignment)))
            ->sortBy(fn ($row) => $row['college_name'].'|'.$row['class_name'])->values();
    }
}
