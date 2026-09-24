<?php

use App\Models\SyncTask;
use App\Http\Controllers\SyncTaskController;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the sync task center with all sync commands', function () {
    $this->get('/sync-tasks')
        ->assertOk()
        ->assertSee('data-page="syncTasks"', false)
        ->assertSee('sync:staff-from-middata', false)
        ->assertSee('sync:students-from-middata', false)
        ->assertSee('sync:passes-from-middata', false)
        ->assertSee('sync:student-families-from-middata', false)
        ->assertSee('sync:student-dormitories-from-middata', false)
        ->assertSee('sync:student-classes-from-middata', false)
        ->assertSee('sync:course-schedules-from-middata', false)
        ->assertSee('sync:student-grades-from-middata', false)
        ->assertSee('sync:student-education-histories-from-middata', false)
        ->assertSee('sync:reconcile-student-passes', false);
});

it('queues a sync command task and exposes status', function () {
    $this->postJson('/sync-tasks/data', [
        'key' => 'passes',
        'options' => ['days' => 7],
    ])
        ->assertStatus(202)
        ->assertJsonPath('status', SyncTask::STATUS_QUEUED)
        ->assertJsonPath('command', 'sync:passes-from-middata')
        ->assertJsonPath('options.days', 7);

    $task = SyncTask::query()->firstOrFail();

    $this->assertDatabaseHas('sync_tasks', [
        'id' => $task->id,
        'key' => 'passes',
        'command' => 'sync:passes-from-middata',
        'status' => SyncTask::STATUS_QUEUED,
    ]);

    $this->getJson("/sync-tasks/data/{$task->id}")
        ->assertOk()
        ->assertJsonPath('id', $task->id)
        ->assertJsonPath('is_active', true);
});

it('requires a valid CLI PHP binary for background syncs', function () {
    $controller = app(SyncTaskController::class);
    $method = new ReflectionMethod($controller, 'cliPhpPath');

    config()->set('sync.php_binary', PHP_BINARY);
    expect($method->invoke($controller))->toBe(PHP_BINARY);

    config()->set('sync.php_binary', null);
    expect($method->invoke($controller))->not->toBeNull();

    config()->set('sync.php_binary', base_path('missing-php-cli'));
    expect($method->invoke($controller))->toBeNull();
});

it('shows startup diagnostics when a queued sync never starts', function () {
    $task = SyncTask::query()->create([
        'key' => 'students',
        'title' => '学生基础信息',
        'command' => 'sync:students-from-middata',
        'options' => [],
        'status' => SyncTask::STATUS_QUEUED,
        'log' => '任务已创建',
    ]);
    $task->forceFill(['created_at' => now()->subMinutes(3)])->save();
    $path = storage_path('logs/sync-task-'.$task->id.'.log');
    file_put_contents($path, 'php: command not found');

    try {
        $this->getJson('/sync-tasks/data')
            ->assertOk()
            ->assertJsonPath('tasks.0.status', SyncTask::STATUS_FAILED)
            ->assertJsonPath('tasks.0.error', '后台进程启动失败：php: command not found');
    } finally {
        unlink($path);
    }
});
