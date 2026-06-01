<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    protected $table = 'course_sections';

    protected $fillable = [
        'jxb_id',
        'kkzt',
        'kch',
        'kcmc',
        'xf',
        'jxbmc',
        'kclb',
        'kcxz',
        'kcgs',
        'kkxiaoq',
        'ktrl',
        'yxrs',
        'zxs',
        'jgh',
        'rkjs',
        'jszc',
        'sksj',
        'jxdd',
        'lh',
        'khfs',
        'ksxs',
        'kkxy',
        'hbxx',
        'xn',
        'xq',
        'qsjsz',
        'weekday',
        'period_start',
        'period_end',
        'week_start',
        'week_end',
        'week_pattern',
        'synced_at',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'period_start' => 'integer',
        'period_end' => 'integer',
        'week_start' => 'integer',
        'week_end' => 'integer',
        'synced_at' => 'datetime',
    ];
}
