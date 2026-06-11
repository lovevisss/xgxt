<?php

namespace App\Http\Controllers;

use App\Models\Pass;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\StudentAcademicYearAverage;
use App\Models\StudentAward;
use App\Models\StudentCadreAssessment;
use App\Models\StudentComprehensiveAssessment;
use App\Models\StudentCourseSchedule;
use App\Models\StudentCourseGrade;
use App\Models\StudentFamily;
use App\Models\StudentLoan;
use App\Models\StudentMedicalInsurance;
use App\Models\StudentDormitory;
use App\Models\StudentEducationHistory;
use App\Models\StudentPhysicalTest;
use App\Models\StudentPunishment;
use App\Models\StudentSafetyInsurance;
use App\Models\StudentSupportRecipient;
use App\Services\StudentAcademicYearAverageService;
use App\Support\CurrentUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentController extends Controller
{
    // 分页查询学生
    public function index()
    {
        $baseQuery = Student::where('rylx', '0');
        $this->applyStudentVisibility($baseQuery);
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
        $this->applyStudentVisibility($baseQuery);

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
        abort_unless($this->canViewStudent($student), 403, 'Only assigned counselors or admins can view this student.');
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
        $loans = StudentLoan::query()
            ->where('student_xgh', $xgh)
            ->orderByDesc('annual_year')
            ->orderBy('source')
            ->get();
        $supportRecipients = StudentSupportRecipient::query()
            ->where('student_xgh', $xgh)
            ->orderByDesc('academic_year')
            ->get();
        $educationHistories = Schema::hasTable('student_education_histories')
            ? StudentEducationHistory::query()
                ->where('stu_no', $xgh)
                ->orderByRaw('sort IS NULL')
                ->orderBy('sort')
                ->orderByDesc('start_year')
                ->get()
            : collect();
        $currentYear = (int) now()->year;
        $medicalInsurances = Schema::hasTable('student_medical_insurances')
            ? StudentMedicalInsurance::query()
                ->where('student_xgh', $xgh)
                ->orderByDesc('annual_year')
                ->get()
            : collect();
        $safetyInsurances = Schema::hasTable('student_safety_insurances')
            ? StudentSafetyInsurance::query()
                ->where('student_xgh', $xgh)
                ->orderByDesc('annual_year')
                ->get()
            : collect();
        $physicalTests = Schema::hasTable('student_physical_tests')
            ? StudentPhysicalTest::query()
                ->where('student_xgh', $xgh)
                ->orderByDesc('academic_year')
                ->get()
            : collect();
        $comprehensiveAssessments = Schema::hasTable('student_comprehensive_assessments')
            ? StudentComprehensiveAssessment::query()
                ->where('student_xgh', $xgh)
                ->orderByDesc('academic_year')
                ->get()
            : collect();
        $cadreAssessments = Schema::hasTable('student_cadre_assessments')
            ? StudentCadreAssessment::query()
                ->where('student_xgh', $xgh)
                ->orderByDesc('academic_year')
                ->orderByDesc('semester')
                ->orderBy('organization')
                ->orderBy('department')
                ->get()
            : collect();
        $dormitory = StudentDormitory::query()->where('xh', $xgh)->first();
        $dormitoryResidents = filled($dormitory?->ssh)
            ? $this->buildDormitoryResidents($dormitory->ssh, $xgh)
            : collect();
        $roommates = $dormitoryResidents
            ->reject(fn (array $resident) => $resident['is_current_student'])
            ->values();
        $dormitorySummary = $this->buildDormitorySummary($dormitoryResidents, $xgh);
        $recentPasses = Pass::query()
            ->where('gh', $xgh)
            ->orderByDesc('smsj')
            ->limit(5)
            ->get(['gh', 'xm', 'smsj', 'smdd', 'crlx', 'device', 'sblx']);
        $companionInsights = $this->buildCompanionInsights($xgh);
        $selectedSemester = trim((string) request('xnxq', ''));
        $semesterOptions = StudentCourseSchedule::query()
            ->where('xh', $xgh)
            ->select('xnxq')
            ->distinct()
            ->orderByDesc('xnxq')
            ->pluck('xnxq');

        if ($selectedSemester === '') {
            $selectedSemester = $this->resolveDefaultSemester($semesterOptions);
        }

        $semesterSchedulesQuery = StudentCourseSchedule::query()->where('xh', $xgh);
        if ($selectedSemester !== '') {
            $semesterSchedulesQuery->where('xnxq', $selectedSemester);
        }

        $semesterSchedules = $semesterSchedulesQuery
            ->orderBy('week_start')
            ->orderBy('weekday')
            ->orderBy('period_start')
            ->orderBy('pkbh')
            ->get();

        [$minWeek, $maxWeek] = $this->scheduleWeekBounds($semesterSchedules);
        $requestedWeek = request()->has('week') ? (int) request('week') : null;
        $defaultWeek = $this->defaultWeekForSemester($selectedSemester, $minWeek, $maxWeek);
        $selectedWeek = $requestedWeek ?? $defaultWeek;
        $selectedWeek = max($minWeek, min($maxWeek, $selectedWeek));

        $courseSections = CourseSection::query()
            ->whereIn('jxb_id', $semesterSchedules->pluck('pkbh')->filter()->unique()->values())
            ->get()
            ->keyBy('jxb_id');

        $weeklySchedule = $this->buildWeeklySchedule($semesterSchedules, $courseSections, $selectedWeek);
        $semesterLabel = $selectedSemester !== '' ? $this->formatSemesterLabel($selectedSemester) : '暂无学期';
        $gradeRows = StudentCourseGrade::query()
            ->where('xh', $xgh)
            ->orderByDesc('xnxq')
            ->orderBy('kcbm')
            ->get();
        $gradesBySemester = $this->buildGradesBySemester($gradeRows);
        $earnedCreditsTotal = $this->earnedCreditsTotal($gradeRows);
        $averageGpa = $this->averageGpa($gradeRows);
        $academicYearAverages = Schema::hasTable('student_academic_year_averages')
            ? StudentAcademicYearAverage::query()
                ->where('student_xgh', $xgh)
                ->orderByDesc('academic_year')
                ->get()
            : collect();
        $averageService = app(StudentAcademicYearAverageService::class);
        $academicYearAverages = $academicYearAverages->map(function (StudentAcademicYearAverage $average) use ($averageService, $xgh) {
            $average->setAttribute(
                'calculation_courses',
                $averageService->courseScoreDetails($xgh, (string) $average->academic_year)->all()
            );

            return $average;
        });

        return view('student-profile', [
            'student' => $student,
            'families' => $families,
            'awards' => $awards,
            'punishments' => $punishments,
            'loans' => $loans,
            'supportRecipients' => $supportRecipients,
            'educationHistories' => $educationHistories,
            'medicalInsurances' => $medicalInsurances,
            'currentMedicalInsurance' => $medicalInsurances->firstWhere('annual_year', $currentYear),
            'safetyInsurances' => $safetyInsurances,
            'currentSafetyInsurance' => $safetyInsurances->firstWhere('annual_year', $currentYear),
            'physicalTests' => $physicalTests,
            'comprehensiveAssessments' => $comprehensiveAssessments,
            'cadreAssessments' => $cadreAssessments,
            'currentYear' => $currentYear,
            'dormitory' => $dormitory,
            'dormitorySummary' => $dormitorySummary,
            'roommates' => $roommates,
            'selectedSemester' => $selectedSemester,
            'semesterLabel' => $semesterLabel,
            'selectedWeek' => $selectedWeek,
            'weekLabel' => '第'.$selectedWeek.'周',
            'prevWeekUrl' => $selectedWeek > $minWeek && $selectedSemester !== ''
                ? route('students.profile', ['xgh' => $xgh, 'xnxq' => $selectedSemester, 'week' => $selectedWeek - 1])
                : null,
            'nextWeekUrl' => $selectedWeek < $maxWeek && $selectedSemester !== ''
                ? route('students.profile', ['xgh' => $xgh, 'xnxq' => $selectedSemester, 'week' => $selectedWeek + 1])
                : null,
            'weeklySchedule' => $weeklySchedule,
            'gradesBySemester' => $gradesBySemester,
            'academicYearAverages' => $academicYearAverages,
            'earnedCreditsTotal' => $earnedCreditsTotal,
            'averageGpa' => $averageGpa,
            'recentPasses' => $recentPasses,
            'companionInsights' => $companionInsights,
            'canUpdateFamilies' => CurrentUser::canManageDepartment($student->dwbm),
        ]);
    }

    public function dormitory(string $ssh)
    {
        $ssh = trim($ssh);
        abort_if($ssh === '', 404);

        $residents = $this->buildDormitoryResidents($ssh);
        abort_if($residents->isEmpty(), 404);

        return view('student-dormitory', [
            'ssh' => $ssh,
            'residents' => $residents,
            'dormitorySummary' => $this->buildDormitorySummary($residents),
        ]);
    }

    private function buildDormitoryResidents(string $ssh, ?string $currentXgh = null): Collection
    {
        $residents = StudentDormitory::query()
            ->where('ssh', $ssh)
            ->orderBy('ch')
            ->orderBy('xh')
            ->get(['xh', 'xm', 'xy', 'zy', 'bj', 'nj', 'ssh', 'ch', 'xz', 'qslx', 'xb']);

        if ($residents->isEmpty()) {
            return collect();
        }

        $students = Student::query()
            ->whereIn('xgh', $residents->pluck('xh')->filter()->values())
            ->get(['xgh', 'xm', 'dwmc', 'dwbm', 'bjmc', 'last_smsj', 'exclude_until'])
            ->keyBy('xgh');

        $now = now();
        $lostThreshold = $now->copy()->subDays(7);

        return $residents->map(function (StudentDormitory $resident) use ($students, $currentXgh, $now, $lostThreshold) {
            $student = $students->get($resident->xh);
            $lastSmsj = $student?->last_smsj;
            $isExcluded = (bool) ($student?->exclude_until && $student->exclude_until->isFuture());
            $isLost = ! $isExcluded && (! $lastSmsj || $lastSmsj->lt($lostThreshold));
            $isHighRisk = ! $isExcluded && (! $lastSmsj || $lastSmsj->lt($now->copy()->subDays(7)));

            return [
                'xh' => $resident->xh,
                'xm' => $student?->xm ?: $resident->xm,
                'xy' => $resident->xy ?: $student?->dwmc,
                'zy' => $resident->zy,
                'bj' => $resident->bj ?: $student?->bjmc,
                'nj' => $resident->nj,
                'ssh' => $resident->ssh,
                'ch' => $resident->ch,
                'xz' => $resident->xz,
                'qslx' => $resident->qslx,
                'xb' => $resident->xb,
                'last_smsj' => $lastSmsj,
                'status' => $isLost ? 'lost' : 'normal',
                'is_high_risk' => $isHighRisk,
                'is_current_student' => $currentXgh !== null && (string) $resident->xh === (string) $currentXgh,
            ];
        })->values();
    }

    private function buildDormitorySummary(Collection $residents, ?string $currentXgh = null): array
    {
        $roommates = $currentXgh === null
            ? $residents
            : $residents->reject(fn (array $resident) => (string) $resident['xh'] === (string) $currentXgh)->values();

        return [
            'resident_total' => $residents->count(),
            'roommate_total' => $roommates->count(),
            'lost_roommate_count' => $roommates->where('status', 'lost')->count(),
            'high_risk_roommate_count' => $roommates->where('is_high_risk', true)->count(),
        ];
    }

    private function buildWeeklySchedule(Collection $schedules, Collection $courseSections, int $selectedWeek): Collection
    {
        return $schedules
            ->filter(fn (StudentCourseSchedule $schedule) => $this->scheduleAppliesToWeek($schedule, $selectedWeek))
            ->map(function (StudentCourseSchedule $schedule) use ($courseSections) {
                $section = $courseSections->get($schedule->pkbh);

                return [
                    'xnxq' => $schedule->xnxq,
                    'xh' => $schedule->xh,
                    'pkbh' => $schedule->pkbh,
                    'weekday' => $this->scheduleWeekday($schedule),
                    'weekday_label' => match ($this->scheduleWeekday($schedule)) {
                        1 => '周一',
                        2 => '周二',
                        3 => '周三',
                        4 => '周四',
                        5 => '周五',
                        6 => '周六',
                        7 => '周日',
                        default => '未知',
                    },
                    'period_start' => $this->schedulePeriodStart($schedule),
                    'period_end' => $this->schedulePeriodEnd($schedule),
                    'period_label' => $this->periodLabel($schedule),
                    'week_start' => $this->scheduleWeekStart($schedule),
                    'week_end' => $this->scheduleWeekEnd($schedule),
                    'course_code' => data_get($section, 'kch') ?: $schedule->kcbm,
                    'course_name' => data_get($section, 'kcmc') ?: ($schedule->kcsxm ?: $schedule->kcbm),
                    'teacher_name' => data_get($section, 'rkjs') ?: $schedule->skjsxm,
                    'location' => data_get($section, 'jxdd') ?: $schedule->jxdd,
                    'college' => data_get($section, 'kkxy') ?: $schedule->kkyxbm,
                    'class_name' => $schedule->kkbjbm,
                    'credit' => data_get($section, 'xf') ?: $schedule->xf,
                    'raw_schedule' => $schedule->sksj ?: data_get($section, 'sksj'),
                ];
            })
            ->sortBy(fn (array $item) => sprintf('%02d-%02d-%s', (int) ($item['weekday'] ?? 0), (int) ($item['period_start'] ?? 0), (string) ($item['course_name'] ?? '')))
            ->values();
    }

    private function scheduleAppliesToWeek(StudentCourseSchedule $schedule, int $selectedWeek): bool
    {
        $weekStart = $this->scheduleWeekStart($schedule);
        $weekEnd = $this->scheduleWeekEnd($schedule);

        if ($weekStart !== null && $selectedWeek < $weekStart) {
            return false;
        }

        if ($weekEnd !== null && $selectedWeek > $weekEnd) {
            return false;
        }

        $pattern = trim((string) ($schedule->week_pattern ?? ''));
        if ($pattern === 'odd' && $selectedWeek % 2 === 0) {
            return false;
        }

        if ($pattern === 'even' && $selectedWeek % 2 === 1) {
            return false;
        }

        return true;
    }

    private function scheduleWeekday(StudentCourseSchedule $schedule): ?int
    {
        $weekday = $schedule->weekday ?? $schedule->xqj;

        return $weekday ?: $this->weekdayFromScheduleText($schedule->sksj);
    }

    private function schedulePeriodStart(StudentCourseSchedule $schedule): ?int
    {
        if ($schedule->period_start !== null) {
            return $schedule->period_start;
        }

        if ($schedule->jc !== null && preg_match('/^(\d+)/', (string) $schedule->jc, $matches) === 1) {
            return (int) $matches[1];
        }

        return $this->periodRangeFromScheduleText($schedule->sksj)[0];
    }

    private function schedulePeriodEnd(StudentCourseSchedule $schedule): ?int
    {
        if ($schedule->period_end !== null) {
            return $schedule->period_end;
        }

        if ($schedule->jc !== null && preg_match('/(\d+)$/', (string) $schedule->jc, $matches) === 1) {
            return (int) $matches[1];
        }

        return $this->periodRangeFromScheduleText($schedule->sksj)[1];
    }

    private function scheduleWeekStart(StudentCourseSchedule $schedule): ?int
    {
        return $schedule->week_start ?: $this->weekRangeFromScheduleText($schedule->sksj)[0];
    }

    private function scheduleWeekEnd(StudentCourseSchedule $schedule): ?int
    {
        return $schedule->week_end ?: $this->weekRangeFromScheduleText($schedule->sksj)[1];
    }

    private function periodLabel(StudentCourseSchedule $schedule): string
    {
        $start = $this->schedulePeriodStart($schedule);
        $end = $this->schedulePeriodEnd($schedule);

        if ($start === null) {
            return $schedule->jc ?: '-';
        }

        return $start === $end ? "第{$start}节" : "第{$start}-{$end}节";
    }

    private function weekdayFromScheduleText(?string $scheduleText): ?int
    {
        $scheduleText = (string) $scheduleText;
        if (preg_match('/星期([一二三四五六日天])/u', $scheduleText, $matches) !== 1) {
            return null;
        }

        return match ($matches[1]) {
            '一' => 1,
            '二' => 2,
            '三' => 3,
            '四' => 4,
            '五' => 5,
            '六' => 6,
            '日', '天' => 7,
            default => null,
        };
    }

    /**
     * @return array{0:?int, 1:?int}
     */
    private function periodRangeFromScheduleText(?string $scheduleText): array
    {
        $scheduleText = (string) $scheduleText;

        if (preg_match('/第(\d+)(?:-(\d+))?节/u', $scheduleText, $matches) !== 1) {
            return [null, null];
        }

        $start = (int) $matches[1];
        $end = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $start;

        return [$start, $end];
    }

    /**
     * @return array{0:?int, 1:?int}
     */
    private function weekRangeFromScheduleText(?string $scheduleText): array
    {
        $scheduleText = (string) $scheduleText;

        if (preg_match('/(\d+)-(\d+)周(?:\(([单双])\))?/u', $scheduleText, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        if (preg_match('/(\d+)周(?:\(([单双])\))?/u', $scheduleText, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[1]];
        }

        return [null, null];
    }

    private function formatSemesterLabel(string $semester): string
    {
        if (preg_match('/^(\d{4}-\d{4})-(\d)$/', $semester, $matches) === 1) {
            return $matches[1].' 学年第'.$matches[2].'学期';
        }

        return $semester;
    }

    private function resolveDefaultSemester(Collection $semesterOptions): string
    {
        $normalized = $semesterOptions
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return '';
        }

        $today = now();
        $currentSemester = $this->currentSemesterByDate($today);
        if ($currentSemester !== '' && $normalized->contains($currentSemester)) {
            return $currentSemester;
        }

        $candidates = $normalized
            ->map(function (string $semester) use ($today) {
                $startDate = $this->semesterStartDate($semester);
                if (! $startDate) {
                    return null;
                }

                return [
                    'semester' => $semester,
                    'distance' => abs($startDate->startOfDay()->diffInDays($today->startOfDay(), false)),
                ];
            })
            ->filter()
            ->sortBy('distance')
            ->values();

        if ($candidates->isNotEmpty()) {
            return (string) $candidates->first()['semester'];
        }

        return (string) ($normalized->first() ?? '');
    }

    private function currentSemesterByDate(Carbon $date): string
    {
        $year = (int) $date->year;
        $month = (int) $date->month;

        if ($month >= 3 && $month <= 8) {
            return ($year - 1).'-'.$year.'-1';
        }

        if ($month >= 9) {
            return $year.'-'.($year + 1).'-2';
        }

        return ($year - 1).'-'.$year.'-2';
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function scheduleWeekBounds(Collection $semesterSchedules): array
    {
        $starts = $semesterSchedules
            ->map(fn (StudentCourseSchedule $schedule) => $this->scheduleWeekStart($schedule))
            ->filter(fn (?int $week) => $week !== null)
            ->values();

        $ends = $semesterSchedules
            ->map(fn (StudentCourseSchedule $schedule) => $this->scheduleWeekEnd($schedule))
            ->filter(fn (?int $week) => $week !== null)
            ->values();

        $minWeek = (int) ($starts->min() ?? 1);
        $maxWeek = (int) ($ends->max() ?? $minWeek);

        if ($maxWeek < $minWeek) {
            $maxWeek = $minWeek;
        }

        return [max(1, $minWeek), max(1, $maxWeek)];
    }

    private function defaultWeekForSemester(string $semester, int $minWeek, int $maxWeek): int
    {
        $startDate = $this->semesterStartDate($semester);
        if (! $startDate) {
            return $minWeek;
        }

        $days = $startDate->startOfDay()->diffInDays(now()->startOfDay(), false);
        $week = $days < 0 ? 1 : intdiv($days, 7) + 1;

        return max($minWeek, min($maxWeek, $week));
    }

    private function semesterStartDate(string $semester): ?Carbon
    {
        $configured = config('semester.start_dates', []);
        if (isset($configured[$semester]) && is_string($configured[$semester])) {
            try {
                return Carbon::parse($configured[$semester]);
            } catch (\Throwable) {
                // Ignore invalid config and fall back to rule-based date.
            }
        }

        if (preg_match('/^(\d{4})-(\d{4})-(\d)$/', $semester, $matches) !== 1) {
            return null;
        }

        $firstYear = (int) $matches[1];
        $secondYear = (int) $matches[2];
        $term = (int) $matches[3];

        return match ($term) {
            1 => Carbon::create($secondYear, 3, 1),
            2 => Carbon::create($firstYear, 9, 1),
            default => null,
        };
    }

    private function buildGradesBySemester(Collection $grades): Collection
    {
        return $grades
            ->groupBy('xnxq')
            ->map(function (Collection $rows, string $semester) {
                $items = $rows->map(function (StudentCourseGrade $grade) {
                    return [
                        'id' => $grade->id,
                        'kcbm' => $grade->kcbm,
                        'kcmc' => $grade->kcmc ?: $grade->kcbm,
                        'cj' => $grade->cj,
                        'jd' => $grade->jd,
                        'xf' => $grade->xf,
                        'ksxz' => $grade->ksxz,
                        'is_passed' => $this->isPassedGrade($grade),
                    ];
                })->values();

                return [
                    'semester' => $semester,
                    'semester_label' => $this->formatSemesterLabel($semester),
                    'total_credits' => round((float) $rows->sum('xf'), 2),
                    'total_grade_points' => round((float) $rows->reduce(function (float $carry, StudentCourseGrade $grade) {
                        return $carry + ((float) ($grade->jd ?? 0)) * ((float) ($grade->xf ?? 0));
                    }, 0.0), 2),
                    'earned_credits' => round((float) $rows->filter(fn (StudentCourseGrade $grade) => $this->isPassedGrade($grade))->sum('xf'), 2),
                    'items' => $items,
                ];
            })
            ->sortByDesc('semester')
            ->values();
    }

    private function earnedCreditsTotal(Collection $grades): float
    {
        $passedByCourse = $grades
            ->filter(fn (StudentCourseGrade $grade) => $this->isPassedGrade($grade))
            ->groupBy('kcbm')
            ->map(fn (Collection $rows) => (float) $rows->max('xf'));

        return round((float) $passedByCourse->sum(), 2);
    }

    private function averageGpa(Collection $grades): ?float
    {
        $valid = $grades->filter(function (StudentCourseGrade $grade) {
            return $grade->jd !== null && $grade->xf !== null && (float) $grade->xf > 0;
        });

        $totalCredits = (float) $valid->sum('xf');
        if ($totalCredits <= 0) {
            return null;
        }

        $totalGradePoints = (float) $valid->reduce(function (float $carry, StudentCourseGrade $grade) {
            return $carry + ((float) $grade->jd) * ((float) $grade->xf);
        }, 0.0);

        return round($totalGradePoints / $totalCredits, 2);
    }

    private function isPassedGrade(StudentCourseGrade $grade): bool
    {
        $score = trim((string) ($grade->cj ?? ''));

        if ($score !== '' && is_numeric($score)) {
            return (float) $score >= 60;
        }

        $passKeywords = ['合格', '通过', '及格', 'pass', 'p'];
        $normalized = mb_strtolower($score);
        foreach ($passKeywords as $keyword) {
            if ($normalized === mb_strtolower($keyword)) {
                return true;
            }
        }

        return false;
    }

    private function buildCompanionInsights(string $studentXgh): Collection
    {
        $referencePasses = Pass::query()
            ->where('gh', $studentXgh)
            ->orderByDesc('smsj')
            ->limit(120)
            ->get(['smsj', 'smdd', 'crlx']);

        if ($referencePasses->isEmpty()) {
            return collect();
        }

        $stats = [];

        foreach ($referencePasses as $pass) {
            if (! $pass->smsj) {
                continue;
            }

            $start = $pass->smsj->copy()->subSeconds(10);
            $end = $pass->smsj->copy()->addSeconds(10);

            $candidates = Pass::query()
                ->where('gh', '!=', $studentXgh)
                ->where('crlx', $pass->crlx)
                ->whereBetween('smsj', [$start, $end])
                ->when(
                    filled($pass->smdd),
                    fn ($query) => $query->where('smdd', $pass->smdd),
                    fn ($query) => $query->whereNull('smdd')
                )
                ->orderBy('smsj')
                ->get(['gh', 'xm', 'smsj']);

            // Count each companion only once for the same reference pass.
            foreach ($candidates->unique('gh') as $candidate) {
                $candidateXgh = (string) $candidate->gh;
                if ($candidateXgh === '') {
                    continue;
                }

                if (! isset($stats[$candidateXgh])) {
                    $stats[$candidateXgh] = [
                        'xgh' => $candidateXgh,
                        'xm' => $candidate->xm,
                        'companion_count' => 0,
                        'last_met_at' => null,
                        'last_smdd' => $pass->smdd,
                        'last_crlx' => $pass->crlx,
                    ];
                }

                $stats[$candidateXgh]['companion_count']++;
                $stats[$candidateXgh]['last_met_at'] = $candidate->smsj;
                $stats[$candidateXgh]['last_smdd'] = $pass->smdd;
                $stats[$candidateXgh]['last_crlx'] = $pass->crlx;
            }
        }

        if ($stats === []) {
            return collect();
        }

        $students = Student::query()
            ->whereIn('xgh', array_keys($stats))
            ->pluck('xm', 'xgh');

        return collect($stats)
            ->map(function (array $item) use ($students) {
                $item['xm'] = (string) ($students[$item['xgh']] ?? $item['xm'] ?? '');
                $item['is_possible_friend'] = $item['companion_count'] > 2;

                return $item;
            })
            ->sortByDesc('companion_count')
            ->values();
    }

    // 显示单个学生
    public function show($xgh)
    {
        $student = Student::where('xgh', $xgh)->firstOrFail();
        abort_unless($this->canViewStudent($student), 403, 'Only assigned counselors or admins can view this student.');

        return response()->json($student);
    }

    // 更新学生信息
    public function update($xgh)
    {
        $student = Student::where('xgh', $xgh)->firstOrFail();
        abort_unless(! config('cas.enabled') || (bool) CurrentUser::get()?->isAdmin(), 403, 'Only admins can update student records.');
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

    private function applyStudentVisibility($query): void
    {
        $user = CurrentUser::get();
        if (! config('cas.enabled') || $user === null || $user->isAdmin()) {
            return;
        }

        $accessPermissions = $user->studentAccessPermissions()->where('is_active', true)->get(['scope_type', 'department_code']);
        if ($accessPermissions->contains(fn ($permission) => $permission->scope_type === \App\Models\StudentAccessPermission::SCOPE_ALL)) {
            return;
        }

        $departmentCodes = $accessPermissions
            ->where('scope_type', \App\Models\StudentAccessPermission::SCOPE_COLLEGE)
            ->pluck('department_code')
            ->filter()
            ->unique()
            ->values();

        if (! $user->isCounselor() && $departmentCodes->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $assignments = $user->classAssignments()->get(['class_code', 'normalized_class_name']);
        $classCodes = $assignments->pluck('class_code')->filter()->unique()->values();
        $classNames = $assignments->pluck('normalized_class_name')->filter()->unique()->values();

        if ($classCodes->isEmpty() && $classNames->isEmpty() && $departmentCodes->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($subQuery) use ($classCodes, $classNames, $departmentCodes) {
            if ($departmentCodes->isNotEmpty()) {
                $subQuery->whereIn('dwbm', $departmentCodes->all());
            }

            if ($classCodes->isNotEmpty()) {
                $subQuery->orWhereIn('bjbm', $classCodes->all());
            }

            if ($classNames->isNotEmpty()) {
                foreach ($classNames as $className) {
                    $subQuery->orWhere('bjmc', 'like', '%'.$className.'%');
                }
            }
        });
    }

    private function canViewStudent(Student $student): bool
    {
        $user = CurrentUser::get();

        if (! config('cas.enabled') || $user === null) {
            return true;
        }

        return $user->canViewStudent($student);
    }
}
