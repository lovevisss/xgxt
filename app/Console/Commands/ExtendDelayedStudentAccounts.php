<?php

namespace App\Console\Commands;

use App\Models\SyncTask;
use App\Services\DelayedStudentAccountExtension;
use Illuminate\Console\Command;

class ExtendDelayedStudentAccounts extends Command
{
    protected $signature = 'sync:extend-delayed-student-accounts {--task-id= : 同步任务 ID}';

    protected $description = '延长仍在校的超期年级学生信息门户账号有效期';

    public function handle(DelayedStudentAccountExtension $extension): int
    {
        $task = SyncTask::query()->find((int) $this->option('task-id'));
        if (! $task || $task->key !== 'delayed_student_accounts') {
            $this->error('缺少有效的延毕生账号延期任务 ID。');

            return self::FAILURE;
        }

        $result = $extension->run($task->id);
        $this->info("年级阈值：{$result['cutoff']}级及更早；保障日期：{$result['target_date']}");
        $this->info("候选 {$result['candidates']}，中间库确认 {$result['matched']}，修改 {$result['updated']}，其中解冻 {$result['unfrozen']}，无账号 {$result['missing']}，跳过 {$result['skipped']}。逐人信息请查看任务变更日志。");

        return self::SUCCESS;
    }
}
