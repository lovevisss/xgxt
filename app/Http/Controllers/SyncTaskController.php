<?php

namespace App\Http\Controllers;

use App\Models\SyncTask;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SyncTaskController extends Controller
{
    private const DEFINITIONS = [
        'students' => [
            'title' => '学生基础信息',
            'command' => 'sync:students-from-middata',
            'description' => '从 middata 同步 students 表基础资料。',
            'options' => [],
        ],
        'passes' => [
            'title' => '刷码记录',
            'command' => 'sync:passes-from-middata',
            'description' => '同步最近 N 天刷码数据，并更新学生最近刷码状态。',
            'options' => [
                'days' => ['type' => 'number', 'label' => '同步天数', 'default' => 3, 'min' => 1, 'max' => 365],
            ],
        ],
        'student_families' => [
            'title' => '家长信息',
            'command' => 'sync:student-families-from-middata',
            'description' => '同步中间库家庭联系人；默认保留本地手工修改。',
            'options' => [
                'overwrite-local' => ['type' => 'boolean', 'label' => '覆盖本地修改', 'default' => false],
            ],
        ],
        'student_dormitories' => [
            'title' => '住宿信息',
            'command' => 'sync:student-dormitories-from-middata',
            'description' => '同步学生宿舍、床位和寝室信息。',
            'options' => [],
        ],
        'student_classes' => [
            'title' => '班级信息',
            'command' => 'sync:student-classes-from-middata',
            'description' => '同步班级基础信息，供筛选、权限和学生页使用。',
            'options' => [],
        ],
        'course_schedules' => [
            'title' => '课表信息',
            'command' => 'sync:course-schedules-from-middata',
            'description' => '同步课程节次、课程基础和学生课表。',
            'options' => [
                'semester' => ['type' => 'text', 'label' => '学年学期', 'placeholder' => '如 2025-2026-1，留空同步全部'],
            ],
        ],
        'student_grades' => [
            'title' => '成绩信息',
            'command' => 'sync:student-grades-from-middata',
            'description' => '同步课程成绩，可按学年学期限制范围。',
            'options' => [
                'semester' => ['type' => 'text', 'label' => '学年学期', 'placeholder' => '如 2025-2026-1'],
            ],
        ],
        'student_education_histories' => [
            'title' => '大学前教育经历',
            'command' => 'sync:student-education-histories-from-middata',
            'description' => '同步学生大学前教育经历，可按学号单独同步。',
            'options' => [
                'student' => ['type' => 'text', 'label' => '指定学号', 'placeholder' => '留空同步全部'],
            ],
        ],
        'reconcile_student_passes' => [
            'title' => '刷码状态重算',
            'command' => 'sync:reconcile-student-passes',
            'description' => '根据已有刷码记录重新计算学生最近刷码状态。',
            'options' => [],
        ],
    ];

    public function page()
    {
        $this->markStaleTasks();

        return view('sync-tasks', [
            'definitions' => array_values($this->definitions()),
            'tasks' => $this->recentTasks(),
        ]);
    }

    public function index()
    {
        $this->markStaleTasks();

        return response()->json([
            'definitions' => array_values($this->definitions()),
            'tasks' => $this->recentTasks(),
        ]);
    }

    public function store(Request $request)
    {
        $keys = array_keys(self::DEFINITIONS);

        $data = $request->validate([
            'key' => ['required', 'string', Rule::in($keys)],
            'options' => ['nullable', 'array'],
        ]);

        $definition = self::DEFINITIONS[$data['key']];
        $options = $this->sanitizeOptions($definition, $data['options'] ?? []);

        $task = SyncTask::query()->create([
            'key' => $data['key'],
            'title' => $definition['title'],
            'command' => $definition['command'],
            'options' => $options,
            'status' => SyncTask::STATUS_QUEUED,
            'log' => '任务已创建，正在启动后台同步进程。',
        ]);

        $this->launchTaskProcess($task);

        return response()->json($this->taskPayload($task->fresh()), 202);
    }

    public function show(SyncTask $task)
    {
        return response()->json($this->taskPayload($task));
    }

    private function definitions(): array
    {
        return collect(self::DEFINITIONS)
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'title' => $definition['title'],
                'command' => $definition['command'],
                'description' => $definition['description'],
                'options' => $definition['options'],
            ])
            ->values()
            ->all();
    }

    private function recentTasks(): array
    {
        return SyncTask::query()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (SyncTask $task) => $this->taskPayload($task))
            ->all();
    }

    private function taskPayload(SyncTask $task): array
    {
        return [
            'id' => $task->id,
            'key' => $task->key,
            'title' => $task->title,
            'command' => $task->command,
            'options' => $task->options ?? [],
            'status' => $task->status,
            'exit_code' => $task->exit_code,
            'log' => $task->log,
            'error' => $task->error,
            'started_at' => optional($task->started_at)->toDateTimeString(),
            'last_output_at' => optional($task->last_output_at)->toDateTimeString(),
            'finished_at' => optional($task->finished_at)->toDateTimeString(),
            'created_at' => optional($task->created_at)->toDateTimeString(),
            'updated_at' => optional($task->updated_at)->toDateTimeString(),
            'elapsed_seconds' => $this->elapsedSeconds($task),
            'is_active' => $task->isActive(),
        ];
    }

    private function sanitizeOptions(array $definition, array $input): array
    {
        $options = [];

        foreach ($definition['options'] as $name => $config) {
            $value = Arr::get($input, $name, $config['default'] ?? null);

            if (($config['type'] ?? 'text') === 'boolean') {
                $options[$name] = filter_var($value, FILTER_VALIDATE_BOOL);
                continue;
            }

            if (($config['type'] ?? 'text') === 'number') {
                $number = (int) $value;
                $min = (int) ($config['min'] ?? PHP_INT_MIN);
                $max = (int) ($config['max'] ?? PHP_INT_MAX);
                $options[$name] = max($min, min($max, $number));
                continue;
            }

            $text = trim((string) $value);
            if ($text !== '') {
                $options[$name] = $text;
            }
        }

        return $options;
    }

    private function launchTaskProcess(SyncTask $task): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $php = $this->shellArg($this->cliPhpPath());
        $artisan = $this->shellArg(base_path('artisan'));
        $taskId = (string) $task->id;
        $command = PHP_OS_FAMILY === 'Windows'
            ? 'cmd /C start /B "" '.$php.' '.$artisan.' sync-task:run '.$taskId.' > NUL 2>&1'
            : $php.' '.$artisan.' sync-task:run '.$taskId.' > /dev/null 2>&1 &';

        $handle = @popen($command, 'r');

        if (! is_resource($handle)) {
            $task->update([
                'status' => SyncTask::STATUS_FAILED,
                'error' => '无法启动后台同步进程。',
                'finished_at' => now(),
            ]);

            return;
        }

        pclose($handle);

        $task->forceFill([
            'log' => trim((string) $task->log."\n".'后台同步进程已启动，等待状态回写。'),
            'last_output_at' => now(),
        ])->save();
    }

    private function markStaleTasks(): void
    {
        $now = now();

        SyncTask::query()
            ->where('status', SyncTask::STATUS_QUEUED)
            ->where('created_at', '<', $now->copy()->subMinutes(2))
            ->update([
                'status' => SyncTask::STATUS_FAILED,
                'error' => '后台同步进程未在 2 分钟内启动，请重新点击开始同步。',
                'finished_at' => $now,
                'updated_at' => $now,
            ]);

        SyncTask::query()
            ->where('status', SyncTask::STATUS_RUNNING)
            ->where(function ($query) use ($now) {
                $query->where('last_output_at', '<', $now->copy()->subMinutes(5))
                    ->orWhere(function ($subQuery) use ($now) {
                        $subQuery->whereNull('last_output_at')
                            ->where('started_at', '<', $now->copy()->subMinutes(5));
                    });
            })
            ->update([
                'status' => SyncTask::STATUS_FAILED,
                'error' => '同步任务超过 5 分钟没有输出，已自动标记为失败，可重新发起。',
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function cliPhpPath(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return PHP_BINARY;
        }

        $candidates = [
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'php.exe',
            PHP_BINDIR.DIRECTORY_SEPARATOR.'php.exe',
            PHP_BINARY,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return PHP_BINARY;
    }

    private function elapsedSeconds(SyncTask $task): ?int
    {
        if (! $task->started_at) {
            return null;
        }

        $end = $task->finished_at ?? now();

        return max(0, $task->started_at->diffInSeconds($end));
    }

    private function shellArg(string $value): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return escapeshellarg($value);
    }
}
