<?php

namespace App\Console\Commands;

use App\Models\SyncTask;
use App\Services\SyncTaskRunner;
use Illuminate\Console\Command;

class RunSyncTask extends Command
{
    protected $signature = 'sync-task:run {task : 同步任务 ID}';

    protected $description = 'Run a queued web sync task by id';

    public function handle(SyncTaskRunner $runner): int
    {
        $task = SyncTask::query()->find((int) $this->argument('task'));

        if (! $task) {
            $this->error('Sync task not found.');

            return self::FAILURE;
        }

        if (! $task->isActive()) {
            $this->warn('Sync task is not active.');

            return self::SUCCESS;
        }

        $runner->run($task);

        return self::SUCCESS;
    }
}
