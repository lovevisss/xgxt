<?php

namespace App\Jobs;

use App\Models\StudentImportTask;
use App\Services\StudentFamilyManualImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportStudentFamilyContacts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $taskId)
    {
    }

    public function handle(StudentFamilyManualImportService $importer): void
    {
        $task = StudentImportTask::query()->findOrFail($this->taskId);
        $task->update([
            'status' => StudentImportTask::STATUS_RUNNING,
            'started_at' => now(),
            'result' => ['imported' => 0, 'students' => 0, 'skipped' => 0, 'errors' => []],
            'error' => null,
        ]);

        try {
            $fullPath = Storage::disk('local')->path($task->path);
            $extension = pathinfo((string) $task->original_name, PATHINFO_EXTENSION) ?: pathinfo($task->path, PATHINFO_EXTENSION);

            $result = $importer->import($fullPath, $extension, function (array $result) use ($task): void {
                $task->forceFill([
                    'result' => $result,
                    'updated_at' => now(),
                ])->save();
            });

            $task->update([
                'status' => StudentImportTask::STATUS_SUCCEEDED,
                'result' => $result,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $task->update([
                'status' => StudentImportTask::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        } finally {
            Storage::disk('local')->delete($task->path);
        }
    }
}
