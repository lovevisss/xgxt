<?php

use App\Models\SyncTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the sync task center with all sync commands', function () {
    $this->get('/sync-tasks')
        ->assertOk()
        ->assertSee('data-page="syncTasks"', false)
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
