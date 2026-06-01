<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCourseSchedule extends Model
{
    protected $table = 'student_course_schedules';

    protected $fillable = [
        'xnxq',
        'xh',
        'pkbh',
        'kkyxbm',
        'kkzybm',
        'kkbjbm',
        'kcbm',
        'zc',
        'qsz',
        'zzz',
        'dsz',
        'xqj',
        'jc',
        'sksj',
        'jxdd',
        'jslxm',
        'xf',
        'llxs',
        'syxs',
        'sjxs',
        'zxs',
        'skjsgh',
        'skjsxm',
        'kcxzm',
        'kcsxm',
        'kslbm',
        'ksfsm',
        'ksxzm',
        'weekday_label',
        'weekday',
        'period_start',
        'period_end',
        'week_start',
        'week_end',
        'week_pattern',
        'tstamp',
        'synced_at',
    ];

    protected $casts = [
        'qsz' => 'integer',
        'zzz' => 'integer',
        'xqj' => 'integer',
        'weekday' => 'integer',
        'period_start' => 'integer',
        'period_end' => 'integer',
        'week_start' => 'integer',
        'week_end' => 'integer',
        'synced_at' => 'datetime',
    ];
}
