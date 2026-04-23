<?php

namespace App\Http\Controllers;

use App\Models\Pass;
use App\Models\Student;
use Illuminate\Support\Carbon;

class StudentController extends Controller
{
    // 分页查询学生
    public function index()
    {
        $baseQuery = Student::where('rylx', '0');
        $query = clone $baseQuery;
        $now = now();
        $lostThreshold = $now->copy()->subDays(7);

        $countableScope = function ($q) use ($now) {
            $q->where(function ($sub) use ($now) {
                $sub->whereNull('exclude_until')
                    ->orWhere('exclude_until', '<=', $now);
            });
        };

        $lostScope = function ($q) use ($countableScope, $lostThreshold) {
            $q->where($countableScope)
                ->where(function ($sub) use ($lostThreshold) {
                    $sub->whereNull('last_smsj')
                        ->orWhere('last_smsj', '<', $lostThreshold);
                });
        };

        $normalScope = function ($q) use ($now, $lostThreshold) {
            $q->where(function ($sub) use ($now, $lostThreshold) {
                $sub->whereNotNull('exclude_until')
                    ->where('exclude_until', '>', $now);
            })->orWhere(function ($sub) use ($now, $lostThreshold) {
                $sub->where(function ($countable) use ($now) {
                    $countable->whereNull('exclude_until')
                        ->orWhere('exclude_until', '<=', $now);
                })->whereNotNull('last_smsj')
                    ->where('last_smsj', '>=', $lostThreshold);
            });
        };

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
