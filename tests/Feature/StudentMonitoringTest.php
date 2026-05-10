<?php

use App\Models\Pass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('calculates realtime status and average pass interval in students api', function () {
    Carbon::setTestNow('2026-04-21 12:00:00');

    Student::create([
        'xgh' => '1001',
        'xm' => 'Lost User',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
        'status' => 'normal',
        'last_smsj' => now()->subDays(8),
    ]);

    Student::create([
        'xgh' => '1002',
        'xm' => 'Normal User',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
        'status' => 'lost',
        'last_smsj' => now()->subDays(1),
    ]);

    Student::create([
        'xgh' => '1003',
        'xm' => 'Excluded User',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
        'status' => 'lost',
        'last_smsj' => now()->subDays(20),
        'exclude_until' => now()->addDays(2),
    ]);

    Pass::create([
        'gh' => '1002',
        'xm' => 'Normal User',
        'device' => 'A',
        'smdd' => 'Gate A',
        'smsj' => now()->copy()->subHours(4),
        'sblx' => 'qr',
        'crlx' => 'in',
    ]);

    Pass::create([
        'gh' => '1002',
        'xm' => 'Normal User',
        'device' => 'A',
        'smdd' => 'Gate A',
        'smsj' => now()->copy()->subHours(3),
        'sblx' => 'qr',
        'crlx' => 'out',
    ]);

    Pass::create([
        'gh' => '1002',
        'xm' => 'Normal User',
        'device' => 'A',
        'smdd' => 'Gate A',
        'smsj' => now()->copy()->subHours(1),
        'sblx' => 'qr',
        'crlx' => 'in',
    ]);

    $response = $this->getJson('/students/data');

    $response->assertOk();
    $response->assertJsonPath('summary.lost_total', 1);

    $students = collect($response->json('data'));
    $lostStudent = $students->firstWhere('xgh', '1001');
    $normalStudent = $students->firstWhere('xgh', '1002');
    $excludedStudent = $students->firstWhere('xgh', '1003');

    expect($lostStudent['status'])->toBe('lost');
    expect($normalStudent['status'])->toBe('normal');
    expect($normalStudent['avg_pass_interval_minutes'])->toBe(90);
    expect($excludedStudent['status'])->toBe('normal');

    $lostOnlyResponse = $this->getJson('/students/data?status=lost');
    $lostOnlyResponse->assertOk();
    expect(collect($lostOnlyResponse->json('data'))->pluck('xgh')->all())->toBe(['1001']);

    Carbon::setTestNow();
});

it('shows elapsed time in reconcile command output', function () {
    $this->artisan('sync:reconcile-student-passes')
        ->expectsOutputToContain('耗时')
        ->assertExitCode(0);
});

it('supports grade and class filters ordered by lost counts', function () {
    Carbon::setTestNow('2026-04-29 12:00:00');

    Student::create([
        'xgh' => '2001',
        'xm' => 'Grade24 Lost A',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
        'bjbm' => '2401',
        'bjmc' => '24级1班',
        'last_smsj' => now()->subDays(9),
    ]);

    Student::create([
        'xgh' => '2002',
        'xm' => 'Grade24 Lost B',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
        'bjbm' => '2402',
        'bjmc' => '24级2班',
        'last_smsj' => now()->subDays(10),
    ]);

    Student::create([
        'xgh' => '2003',
        'xm' => 'Grade23 Normal',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
        'bjbm' => '2301',
        'bjmc' => '23级1班',
        'last_smsj' => now()->subDays(1),
    ]);

    $filters = $this->getJson('/students/filters?grade=24')->assertOk();
    $grades = $filters->json('grades');
    $classes = $filters->json('classes');

    expect($grades[0]['grade_code'])->toBe('24');
    expect($grades[0]['lost_count'])->toBe(2);
    expect($classes[0]['class_code'])->toBe('2401');
    expect($classes[1]['class_code'])->toBe('2402');

    $filtered = $this->getJson('/students/data?grade=24&class_code=2401')->assertOk();
    expect(collect($filtered->json('data'))->pluck('xgh')->all())->toBe(['2001']);

    Carbon::setTestNow();
});

