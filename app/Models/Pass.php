<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pass extends Model
{
    protected $table = 'passes';

    protected $fillable = [
        'gh',
        'xm',
        'device',
        'smdd',
        'smsj',
        'sblx',
        'crlx',
        'is_recorded',
        'recorded_at',
        'synced_at',
    ];

    protected $casts = [
        'smsj' => 'datetime',
        'recorded_at' => 'datetime',
        'synced_at' => 'datetime',
        'is_recorded' => 'boolean',
    ];
}
