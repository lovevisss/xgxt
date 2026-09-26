<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    public const CATEGORY_CURRENT = 'current';

    public const CATEGORY_GRADUATED = 'graduated';

    protected $table = 'students';

    protected $primaryKey = 'xgh';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'xgh', 'xm', 'xbm', 'rylx', 'dwmc', 'dwbm', 'bjbm', 'bjmc', 'dzyx', 'yddh', 'csrq', 'jg', 'mzm',
        'sfzjh', 'politicalcode', 'zgxl', 'wlkh', 'zhbz', 'updated_at', 'last_smsj', 'status',
        'exclude_reason', 'exclude_until', 'student_category', 'graduated_at', 'source_sync_id',
    ];

    protected $casts = [
        'last_smsj' => 'datetime',
        'exclude_until' => 'datetime',
        'graduated_at' => 'datetime',
    ];
}
