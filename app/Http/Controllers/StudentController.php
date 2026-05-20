<?php

namespace App\Http\Controllers;

use App\Models\Pass;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\StudentFamily;
use App\Models\StudentPunishment;
use App\Support\CurrentUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    // 分页查询学生
    public function index()
    {
        $baseQuery = Student::where('rylx', '0');
        $query = clone $baseQuery;
        $now = now();
        $lostThreshold = $now->copy()->subDays(7);

        $countableScope = $this->countableScope($now);
        $lostScope = $this->lostScope($now, $lostThreshold);
        $normalScope = $this->normalScope($now, $lostThreshold);

        $grade = trim((string) request('grade', ''));
        if ($grade !== '') {
            $query->where('bjbm', 'like', "{$grade}%");
        }

        $classCode = trim((string) request('class_code', ''));
        if ($classCode !== '') {
            $query->where('bjbm', $classCode);
        }

        $status = trim((string) request('status', ''));
        if ($status === 'lost') {
            $query->where($lostScope);
        } elseif ($status === 'normal') {
            $query->where($normalScope);
        }

        $risk = trim((string) request('risk', ''));
        if ($risk === 'high') {
            $query->where(function ($sub) use ($now) {
                $sub->where(function ($countable) use ($now) {
                    $countable->whereNull('exclude_until')
                        ->orWhere('exclude_until', '<=', $now);
                })->where(function ($riskQuery) use ($now) {
                    $riskQuery->whereNull('last_smsj')
                        ->orWhere('last_smsj', '<', $now->copy()->subDays(7));
                });
            });
        }

        $keyword = trim((string) request('q', ''));
        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('xgh', 'like', "%{$keyword}%")
                    ->orWhere('xm', 'like', "%{$keyword}%")
                    ->orWhere('bjmc', 'like', "%{$keyword}%");
            });
        }

        $students = $query->orderBy('xgh')->paginate(15);
        $studentKeys = $students->getCollection()->pluck('xgh')->filter()->values()->all();
        $intervalMap = $this->buildAverageIntervals($studentKeys);

        $students->getCollection()->transform(function (Student $student) use ($intervalMap, $lostThreshold) {
            $days = null;
            if ($student->last_smsj) {
                $days = Carbon::parse($student->last_smsj)->startOfDay()->diffInDays(now()->startOfDay());
            }

            $isExcluded = $student->exclude_until && $student->exclude_until->isFuture();
            $isLost = ! $isExcluded && (! $student->last_smsj || $student->last_smsj->lt($lostThreshold));
            $avgMinutes = $intervalMap[$student->xgh] ?? null;

            $student->setAttribute('days_since_last_smsj', $days);
            $student->setAttribute('is_excluded', $isExcluded);
            $student->setAttribute('status', $isLost ? 'lost' : 'normal');
            $student->setAttribute('avg_pass_interval_minutes', $avgMinutes);

            return $student;
        });

        $activeExcluded = function ($q) use ($now) {
            $q->whereNotNull('exclude_until')
                ->where('exclude_until', '>', $now);
        };

        return response()->json(array_merge($students->toArray(), [
            'filters' => [
                'grade' => $grade,
                'class_code' => $classCode,
            ],
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'excluded_total' => (clone $baseQuery)->where($activeExcluded)->count(),
                'lost_total' => (clone $baseQuery)
                    ->where($lostScope)
                    ->count(),
                'lost_today' => (clone $baseQuery)
                    ->where($countableScope)
                    ->whereDate('last_smsj', $lostThreshold->toDateString())
                    ->count(),
            ],
        ]));
    }

    // 年级/班级筛选项，按失联人数降序
    public function filters()
    {
        $now = now();
        $lostThreshold = $now->copy()->subDays(7);
        $lostScope = $this->lostScope($now, $lostThreshold);
        $gradeExpr = $this->gradeSqlExpression();
        $gradeGroup = DB::raw($gradeExpr);
        $baseQuery = Student::query()
            ->where('rylx', '0')
            ->whereNotNull('bjbm')
            ->where('bjbm', '!=', '');

        $gradeTotals = (clone $baseQuery)
            ->selectRaw("{$gradeExpr} as grade_code, COUNT(*) as total_count")
            ->groupBy($gradeGroup)
            ->get();

        $gradeLostMap = (clone $baseQuery)
            ->selectRaw("{$gradeExpr} as grade_code, COUNT(*) as lost_count")
            ->where($lostScope)
            ->groupBy($gradeGroup)
            ->pluck('lost_count', 'grade_code');

        $grades = $gradeTotals
            ->map(function ($row) use ($gradeLostMap) {
                $code = (string) $row->grade_code;

                return [
                    'grade_code' => $code,
                    'total_count' => (int) $row->total_count,
                    'lost_count' => (int) ($gradeLostMap[$code] ?? 0),
                ];
            })
            ->sort(function (array $a, array $b) {
                if ($a['lost_count'] !== $b['lost_count']) {
                    return $b['lost_count'] <=> $a['lost_count'];
                }
                if ($a['total_count'] !== $b['total_count']) {
                    return $b['total_count'] <=> $a['total_count'];
                }

                return $a['grade_code'] <=> $b['grade_code'];
            })
            ->values();

        $selectedGrade = trim((string) request('grade', ''));
        $classes = collect();

        if ($selectedGrade !== '') {
            $classBase = (clone $baseQuery)->where('bjbm', 'like', "{$selectedGrade}%");

            $classTotals = (clone $classBase)
                ->selectRaw('bjbm as class_code, MAX(bjmc) as class_name, COUNT(*) as total_count')
                ->groupBy('bjbm')
                ->get();

            $classLostMap = (clone $classBase)
                ->selectRaw('bjbm as class_code, COUNT(*) as lost_count')
                ->where($lostScope)
                ->groupBy('bjbm')
                ->pluck('lost_count', 'class_code');

            $classes = $classTotals
                ->map(function ($row) use ($classLostMap) {
                    $code = (string) $row->class_code;

                    return [
                        'class_code' => $code,
                        'class_name' => (string) ($row->class_name ?? ''),
                        'total_count' => (int) $row->total_count,
                        'lost_count' => (int) ($classLostMap[$code] ?? 0),
                    ];
                })
                ->sort(function (array $a, array $b) {
                    if ($a['lost_count'] !== $b['lost_count']) {
                        return $b['lost_count'] <=> $a['lost_count'];
                    }
                    if ($a['total_count'] !== $b['total_count']) {
                        return $b['total_count'] <=> $a['total_count'];
                    }

                    return $a['class_code'] <=> $b['class_code'];
                })
                ->values();
        }

        return response()->json([
            'grades' => $grades,
            'classes' => $classes,
        ]);
    }

    private function buildAverageIntervals(array $studentKeys): array
    {
        if ($studentKeys === []) {
            return [];
        }

        $passes = Pass::query()
            ->select(['gh', 'smsj'])
            ->whereIn('gh', $studentKeys)
            ->orderBy('gh')
            ->orderBy('smsj')
            ->get();

        $intervals = [];
        $lastTimes = [];

        foreach ($passes as $pass) {
            $key = (string) $pass->gh;
            if (isset($lastTimes[$key])) {
                $minutes = abs($pass->smsj->diffInMinutes($lastTimes[$key], false));
                $intervals[$key]['sum'] = ($intervals[$key]['sum'] ?? 0) + $minutes;
                $intervals[$key]['count'] = ($intervals[$key]['count'] ?? 0) + 1;
            }
            $lastTimes[$key] = $pass->smsj;
        }

        $result = [];
        foreach ($intervals as $key => $stat) {
            if (($stat['count'] ?? 0) > 0) {
                $result[$key] = round($stat['sum'] / $stat['count'], 1);
            }
        }

        return $result;
    }

    private function countableScope($now): \Closure
    {
        return function ($query) use ($now) {
            $query->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('exclude_until')
                    ->orWhere('exclude_until', '<=', $now);
            });
        };
    }

    private function lostScope($now, $lostThreshold): \Closure
    {
        return function ($query) use ($now, $lostThreshold) {
            $query->where($this->countableScope($now))
                ->where(function ($subQuery) use ($lostThreshold) {
                    $subQuery->whereNull('last_smsj')
                        ->orWhere('last_smsj', '<', $lostThreshold);
                });
        };
    }

    private function normalScope($now, $lostThreshold): \Closure
    {
        return function ($query) use ($now, $lostThreshold) {
            $query->where(function ($subQuery) use ($now) {
                $subQuery->whereNotNull('exclude_until')
                    ->where('exclude_until', '>', $now);
            })->orWhere(function ($subQuery) use ($now, $lostThreshold) {
                $subQuery->where($this->countableScope($now))
                    ->whereNotNull('last_smsj')
                    ->where('last_smsj', '>=', $lostThreshold);
            });
        };
    }

    private function gradeSqlExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'substr(bjbm, 1, 2)',
            default => 'SUBSTRING(bjbm, 1, 2)',
        };
    }

    public function profile($xgh)
    {
        $student = Student::where('xgh', $xgh)->firstOrFail();
        $families = StudentFamily::query()
            ->where('stu_no', $xgh)
            ->orderByDesc('is_emergency_contact')
            ->orderBy('id')
            ->get();
        $awards = StudentAward::query()
            ->where('student_xgh', $xgh)
            ->orderByDesc('annual_year')
            ->orderBy('level')
            ->orderBy('award_name')
            ->get();
        $punishments = StudentPunishment::query()
            ->where('student_xgh', $xgh)
            ->orderByDesc('annual_year')
            ->orderByDesc('punished_at')
            ->get();

        return view('student-profile', [
            'student' => $student,
            'families' => $families,
            'awards' => $awards,
            'punishments' => $punishments,
            'canUpdateFamilies' => CurrentUser::canManageDepartment($student->dwbm),
        ]);
    }

    // 显示单个学生
    public function show($xgh)
    {
        $student = Student::where('xgh', $xgh)->firstOrFail();

        return response()->json($student);
    }

    // 更新学生信息
    public function update($xgh)
    {
        $student = Student::where('xgh', $xgh)->firstOrFail();
        $excludeUntil = request('exclude_until');

        $student->fill(request()->only([
            'xm', 'xbm', 'dwmc', 'dwbm', 'bjbm', 'bjmc', 'dzyx', 'yddh', 'csrq', 'jg', 'mzm', 'sfzjh', 'politicalcode', 'zgxl', 'wlkh', 'zhbz',
            'exclude_reason',
        ]));

        $student->exclude_until = $excludeUntil ? Carbon::parse($excludeUntil) : null;
        $student->updated_at = now();
        $student->save();

        return response()->json($student);
    }
}
