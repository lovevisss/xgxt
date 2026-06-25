<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncTask extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'key',
        'title',
        'command',
        'options',
        'status',
        'exit_code',
        'log',
        'error',
        'started_at',
        'last_output_at',
        'finished_at',
    ];

    protected $casts = [
        'options' => 'array',
        'started_at' => 'datetime',
        'last_output_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }
}
