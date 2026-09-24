<?php

namespace App\Jobs;

use App\Models\StudentImportTask;
use App\Services\StudentCadreAssessmentImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportStudentCadreAssessments implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $taskId)
    {
    }

    public function handle(StudentCadreAssessmentImportService $importer): void
    {
        $task = StudentImportTask::query()->findOrFail($this->taskId);
        if ($task->status !== StudentImportTask::STATUS_QUEUED) {
            return;
        }

        $options = $task->result ?? [];
        $task->update([
            'status' => StudentImportTask::STATUS_RUNNING,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $path = Storage::disk('local')->path($task->path);
            $file = new UploadedFile($path, $task->original_name ?: basename($path), null, null, true);
            $result = $importer->import(
                $file,
                (string) $options['academic_year'],
                $options['semester'] ?? null,
                function (array $progress) use ($task, $options): void {
                    $task->forceFill(['result' => array_merge($options, $progress)])->save();
                }
            );

            $task->update([
                'status' => StudentImportTask::STATUS_SUCCEEDED,
                'result' => array_merge($options, $result),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $task->update([
                'status' => StudentImportTask::STATUS_FAILED,
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        } finally {
            Storage::disk('local')->delete($task->path);
        }
    }
}
