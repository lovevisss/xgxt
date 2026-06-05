<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAcademicYearAverage;
use App\Models\StudentClass;
use App\Models\StudentCourseGrade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentAcademicYearAverageService
{
    public function calculate(?string $academicYear = null): array
    {
        $years = $academicYear !== null && $academicYear !== ''
            ? [$academicYear]
            : $this->academicYears();

        $totalStudents = 0;
        $classCodes = collect();
        $majorCodes = collect();

        foreach ($years as $year) {
            $summaries = $this->summariesForYear($year);
            $ranked = $this->applyRanks(collect($summaries))->values();
            $this->replaceYearSummaries($year, $ranked);

            $totalStudents += $ranked->count();
            $classCodes = $classCodes->merge($ranked->pluck('class_code')->filter());
            $majorCodes = $majorCodes->merge($ranked->pluck('major_code')->filter());
        }

        return [
            'academic_year' => $academicYear,
            'students' => $totalStudents,
            'classes' => $classCodes->unique()->count(),
            'majors' => $majorCodes->unique()->count(),
        ];
    }

    public function courseScoreDetails(string $studentNumber, string $academicYear): Collection
    {
        $rows = StudentCourseGrade::query()
            ->where('xh', $studentNumber)
            ->where('xnxq', 'like', "{$academicYear}-%")
            ->orderBy('xnxq')
            ->orderBy('kcbm')
            ->get(['xh', 'xnxq', 'kcbm', 'kcmc', 'cj', 'xf', 'ksxz']);

        return $this->buildCourseScoreDetails($rows);
    }

    private function academicYears(): array
    {
        return StudentCourseGrade::query()
            ->select('xnxq')
            ->distinct()
            ->pluck('xnxq')
            ->map(fn (?string $semester) => $this->academicYearFromSemester($semester))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private function summariesForYear(string $academicYear): array
    {
        $summaries = [];

        DB::table('student_course_grades')
            ->select('xh')
            ->where('xnxq', 'like', "{$academicYear}-%")
            ->whereNotNull('xh')
            ->where('xh', '!=', '')
            ->distinct()
            ->orderBy('xh')
            ->chunk(500, function ($studentRows) use ($academicYear, &$summaries): void {
                $studentNumbers = $studentRows->pluck('xh')->filter()->values();
                if ($studentNumbers->isEmpty()) {
                    return;
                }

                $students = Student::query()
                    ->whereIn('xgh', $studentNumbers)
                    ->get(['xgh', 'xm', 'rylx', 'bjbm', 'bjmc'])
                    ->keyBy('xgh');

                $classes = StudentClass::query()
                    ->whereIn('class_code', $students->pluck('bjbm')->filter()->unique()->values())
                    ->get(['class_code', 'major_code'])
                    ->keyBy('class_code');

                $gradesByStudent = StudentCourseGrade::query()
                    ->whereIn('xh', $studentNumbers)
                    ->where('xnxq', 'like', "{$academicYear}-%")
                    ->get(['xh', 'xnxq', 'kcbm', 'kcmc', 'cj', 'xf', 'ksxz'])
                    ->groupBy('xh');

                foreach ($gradesByStudent as $studentNumber => $rows) {
                    $student = $students->get($studentNumber);
                    if (! $student || (string) $student->rylx !== '0') {
                        continue;
                    }

                    $courses = $this->courseScores($rows);
                    $totalCredits = (float) $courses->sum('credits');
                    if ($totalCredits <= 0) {
                        continue;
                    }

                    $weightedScore = (float) $courses->reduce(
                        fn (float $carry, array $course) => $carry + ($course['score'] * $course['credits']),
                        0.0
                    );

                    $classCode = trim((string) $student->bjbm);
                    $majorCode = $classes->get($classCode)?->major_code ?: $this->majorCodeFromClassCode($classCode);

                    $summaries[] = [
                        'student_xgh' => (string) $studentNumber,
                        'student_name' => $student->xm,
                        'academic_year' => $academicYear,
                        'class_code' => $classCode !== '' ? $classCode : null,
                        'class_name' => $student->bjmc,
                        'major_code' => $majorCode,
                        'average_score' => round($weightedScore / $totalCredits, 2),
                        'total_credits' => round($totalCredits, 2),
                        'course_count' => $courses->count(),
                    ];
                }
            });

        return $summaries;
    }

    private function replaceYearSummaries(string $academicYear, Collection $summaries): void
    {
        DB::transaction(function () use ($academicYear, $summaries): void {
            StudentAcademicYearAverage::query()
                ->where('academic_year', $academicYear)
                ->delete();

            $now = now();
            foreach ($summaries->chunk(500) as $chunk) {
                StudentAcademicYearAverage::query()->insert($chunk->map(fn (array $summary) => [
                    ...$summary,
                    'calculated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }
        });
    }

    private function courseScores(Collection $rows): Collection
    {
        return $this->buildCourseScoreDetails($rows)
            ->map(fn (array $course) => [
                'credits' => $course['credits'],
                'score' => $course['score'],
            ]);
    }

    private function buildCourseScoreDetails(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (StudentCourseGrade $grade) => ! $this->isPhysicalEducation($grade))
            ->filter(fn (StudentCourseGrade $grade) => (float) ($grade->xf ?? 0) > 0)
            ->groupBy('kcbm')
            ->map(function (Collection $courseRows) {
                $makeupRows = $courseRows->filter(fn (StudentCourseGrade $grade) => $this->isMakeupExam($grade));
                $selectedRows = $makeupRows->isNotEmpty() ? $makeupRows : $courseRows;
                $scoredRows = $selectedRows
                    ->map(fn (StudentCourseGrade $grade) => [
                        'grade' => $grade,
                        'score' => $this->normalizedScore($grade),
                    ])
                    ->filter(fn (array $row) => $row['score'] !== null)
                    ->values();

                if ($scoredRows->isEmpty()) {
                    return null;
                }

                $best = $scoredRows->sortByDesc('score')->first();
                $selectedGrade = $best['grade'];
                $score = (float) $best['score'];
                $credits = (float) $courseRows->max('xf');

                return [
                    'semester' => $selectedGrade->xnxq,
                    'course_code' => $selectedGrade->kcbm,
                    'course_name' => $selectedGrade->kcmc,
                    'original_score' => $selectedGrade->cj,
                    'exam_type' => $selectedGrade->ksxz,
                    'credits' => $credits,
                    'score' => $score,
                    'weighted_score' => round($score * $credits, 2),
                    'calculation_note' => $this->calculationNote($selectedGrade, $score, $makeupRows->isNotEmpty()),
                    'source_rows' => $courseRows->map(fn (StudentCourseGrade $grade) => [
                        'semester' => $grade->xnxq,
                        'original_score' => $grade->cj,
                        'exam_type' => $grade->ksxz,
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->values();
    }

    private function normalizedScore(StudentCourseGrade $grade): ?float
    {
        $score = trim((string) ($grade->cj ?? ''));
        $normalized = mb_strtolower($score);

        if ($this->isZeroScoreExam($score)) {
            return 0.0;
        }

        if (is_numeric($score)) {
            $value = (float) $score;

            return $this->isMakeupExam($grade) && $value >= 60 ? 60.0 : $value;
        }

        foreach (['合格', '通过', '及格', 'pass', 'p'] as $keyword) {
            if ($normalized === mb_strtolower($keyword)) {
                return 60.0;
            }
        }

        foreach (['不合格', '不通过', '不及格', 'fail', 'f'] as $keyword) {
            if ($normalized === mb_strtolower($keyword)) {
                return 0.0;
            }
        }

        return null;
    }

    private function calculationNote(StudentCourseGrade $grade, float $score, bool $usedMakeupRows): string
    {
        $rawScore = trim((string) ($grade->cj ?? ''));

        if ($this->isZeroScoreExam($rawScore)) {
            return '旷考、取消考试资格或作弊等情况，按 0 分计入';
        }

        if ($usedMakeupRows && $score === 60.0 && is_numeric($rawScore) && (float) $rawScore >= 60) {
            return '补考及格，按 60 分计入';
        }

        if ($usedMakeupRows) {
            return '补考不及格或低于 60 分，按实际分数计入';
        }

        return '按课程成绩计入';
    }

    private function applyRanks(Collection $summaries): Collection
    {
        $ranked = $summaries->keyBy(fn (array $summary) => $summary['student_xgh'].'|'.$summary['academic_year']);

        foreach ($summaries->filter(fn (array $summary) => filled($summary['class_code']))->groupBy(fn (array $summary) => $summary['academic_year'].'|'.$summary['class_code']) as $group) {
            $this->applyRankToGroup($ranked, $group, 'class_rank', 'class_size');
        }

        foreach ($summaries->filter(fn (array $summary) => filled($summary['major_code']))->groupBy(fn (array $summary) => $summary['academic_year'].'|'.$summary['major_code']) as $group) {
            $this->applyRankToGroup($ranked, $group, 'major_rank', 'major_size');
        }

        return $ranked->values();
    }

    private function applyRankToGroup(Collection $ranked, Collection $group, string $rankKey, string $sizeKey): void
    {
        $ordered = $group->sortByDesc('average_score')->values();
        $previousScore = null;
        $rank = 0;

        foreach ($ordered as $index => $summary) {
            if ($previousScore === null || (float) $summary['average_score'] !== (float) $previousScore) {
                $rank = $index + 1;
                $previousScore = $summary['average_score'];
            }

            $key = $summary['student_xgh'].'|'.$summary['academic_year'];
            $current = $ranked->get($key);
            $current[$rankKey] = $rank;
            $current[$sizeKey] = $ordered->count();
            $ranked->put($key, $current);
        }
    }

    private function academicYearFromSemester(?string $semester): string
    {
        return preg_match('/(20\d{2})\s*[-—至]\s*(20\d{2})/', (string) $semester, $matches)
            ? $matches[1].'-'.$matches[2]
            : '';
    }

    private function isPhysicalEducation(StudentCourseGrade $grade): bool
    {
        $courseName = (string) $grade->kcmc;

        return str_contains($courseName, '体育');
    }

    private function isMakeupExam(StudentCourseGrade $grade): bool
    {
        $examType = (string) $grade->ksxz;

        return str_contains($examType, '补考');
    }

    private function isZeroScoreExam(string $score): bool
    {
        foreach (['旷考', '缺考', '取消考试资格', '作弊', '违纪'] as $keyword) {
            if (str_contains($score, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function majorCodeFromClassCode(?string $classCode): ?string
    {
        $classCode = trim((string) $classCode);

        return strlen($classCode) >= 4 ? substr($classCode, 0, 4) : null;
    }
}
