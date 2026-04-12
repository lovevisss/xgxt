<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Support\Carbon;

class StudentController extends Controller
{
    // 分页查询学生
    public function index()
    {
        $baseQuery = Student::where('rylx', '0');
        $query = clone $baseQuery;

        $status = trim((string) request('status', ''));
        if (in_array($status, ['normal', 'lost'], true)) {
            $query->where('status', $status);
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
        $students->getCollection()->transform(function (Student $student) {
            $days = null;
            if ($student->last_smsj) {
                $days = Carbon::parse($student->last_smsj)->startOfDay()->diffInDays(now()->startOfDay());
            }

            $student->setAttribute('days_since_last_smsj', $days);

            return $student;
        });

        $lostTodayDate = now()->subDays(7)->toDateString();

        return response()->json(array_merge($students->toArray(), [
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'lost_total' => (clone $baseQuery)->where('status', 'lost')->count(),
                'lost_today' => (clone $baseQuery)
                    ->where('status', 'lost')
                    ->whereDate('last_smsj', $lostTodayDate)
                    ->count(),
            ],
        ]));
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
        $student->fill(request()->only([
            'xm', 'xbm', 'dwmc', 'dwbm', 'bjbm', 'bjmc', 'dzyx', 'yddh', 'csrq', 'jg', 'mzm', 'sfzjh', 'politicalcode', 'zgxl', 'wlkh', 'zhbz',
        ]));
        $student->updated_at = now();
        $student->save();

        return response()->json($student);
    }
}
