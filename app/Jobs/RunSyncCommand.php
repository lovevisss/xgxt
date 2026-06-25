<?php

namespace App\Jobs;

use App\Models\SyncTask;
use App\Services\SyncTaskRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunSyncCommand implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public int $taskId)
    {
    }

    public function handle(SyncTaskRunner $runner): void
    {
        $runner->run(SyncTask::query()->findOrFail($this->taskId));
    }
}
