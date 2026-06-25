<?php

namespace App\Services;

use App\Models\SyncTask;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;
use Throwable;

class SyncTaskRunner
{
    private const MAX_LOG_LENGTH = 60000;

    public function run(SyncTask $task): void
    {
        $task->update([
            'status' => SyncTask::STATUS_RUNNING,
            'started_at' => now(),
            'last_output_at' => now(),
            'log' => $this->appendLog($task->log, '任务开始：php artisan '.$task->command),
            'error' => null,
        ]);

        try {
            $process = new Process($this->arguments($task), base_path());
            $process->setTimeout(null);
            $lastFlush = microtime(true);
            $buffer = '';

            $process->run(function (string $type, string $output) use ($task, &$lastFlush, &$buffer): void {
                $buffer .= $this->cleanOutput($output);

                if (microtime(true) - $lastFlush < 0.8 && strlen($buffer) < 2048) {
                    return;
                }

                $this->flushLog($task, $buffer);
                $lastFlush = microtime(true);
                $buffer = '';
            });

            if ($buffer !== '') {
                $this->flushLog($task, $buffer);
            }

            $exitCode = $process->getExitCode();
            $success = $process->isSuccessful();

            $task->forceFill([
                'status' => $success ? SyncTask::STATUS_SUCCEEDED : SyncTask::STATUS_FAILED,
                'exit_code' => $exitCode,
                'log' => $this->appendLog($task->fresh()->log, $success ? '任务完成。' : '任务失败，退出码：'.$exitCode),
                'error' => $success ? null : $process->getErrorOutput(),
                'finished_at' => now(),
                'last_output_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $task->forceFill([
                'status' => SyncTask::STATUS_FAILED,
                'error' => $e->getMessage(),
                'log' => $this->appendLog($task->fresh()->log, '任务异常：'.$e->getMessage()),
                'finished_at' => now(),
                'last_output_at' => now(),
            ])->save();

            throw $e;
        }
    }

    private function arguments(SyncTask $task): array
    {
        $arguments = [PHP_BINARY, base_path('artisan'), $task->command, '--no-ansi', '--no-interaction'];

        foreach ($task->options ?? [] as $name => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }

            $arguments[] = $value === true ? "--{$name}" : "--{$name}={$value}";
        }

        return $arguments;
    }

    private function flushLog(SyncTask $task, string $output): void
    {
        $fresh = $task->fresh();
        $fresh->forceFill([
            'log' => $this->appendLog($fresh->log, $output),
            'last_output_at' => Carbon::now(),
        ])->save();
    }

    private function appendLog(?string $existing, string $output): string
    {
        $output = trim($output);

        if ($output === '') {
            return (string) $existing;
        }

        $next = trim((string) $existing."\n".$output);

        return strlen($next) > self::MAX_LOG_LENGTH ? substr($next, -self::MAX_LOG_LENGTH) : $next;
    }

    private function cleanOutput(string $output): string
    {
        return str_replace(["\r\n", "\r"], "\n", $output);
    }
}
