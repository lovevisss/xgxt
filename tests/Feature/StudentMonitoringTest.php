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

it('shows recent passes and companion insights on student profile', function () {
    Student::create([
        'xgh' => '3001',
        'xm' => 'Profile Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
        'bjbm' => '2401',
        'bjmc' => '24级1班',
    ]);

    Student::create([
        'xgh' => '3002',
        'xm' => 'Friend Candidate',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
    ]);

    Student::create([
        'xgh' => '3003',
        'xm' => 'Observer Candidate',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Test',
        'dwbm' => 'T',
    ]);

    Pass::create(['gh' => '3001', 'xm' => 'Profile Student', 'device' => 'old-device', 'smdd' => 'Old Gate', 'smsj' => '2026-05-01 07:00:00', 'crlx' => 'in']);
    Pass::create(['gh' => '3001', 'xm' => 'Profile Student', 'device' => 'dev-a1', 'smdd' => 'Gate A', 'smsj' => '2026-05-01 08:00:00', 'crlx' => 'in']);
    Pass::create(['gh' => '3001', 'xm' => 'Profile Student', 'device' => 'dev-b1', 'smdd' => 'Gate B', 'smsj' => '2026-05-01 09:00:00', 'crlx' => 'out']);
    Pass::create(['gh' => '3001', 'xm' => 'Profile Student', 'device' => 'dev-a2', 'smdd' => 'Gate A', 'smsj' => '2026-05-01 10:00:00', 'crlx' => 'in']);
    Pass::create(['gh' => '3001', 'xm' => 'Profile Student', 'device' => 'dev-c1', 'smdd' => 'Gate C', 'smsj' => '2026-05-01 11:00:00', 'crlx' => 'out']);
    Pass::create(['gh' => '3001', 'xm' => 'Profile Student', 'device' => 'dev-a3', 'smdd' => 'Gate A', 'smsj' => '2026-05-01 12:00:00', 'crlx' => 'in']);

    // 3002 appears together 3 times within 10 seconds, same place and direction.
    Pass::create(['gh' => '3002', 'xm' => 'Friend Candidate', 'device' => 'friend-1', 'smdd' => 'Gate A', 'smsj' => '2026-05-01 08:00:05', 'crlx' => 'in']);
    Pass::create(['gh' => '3002', 'xm' => 'Friend Candidate', 'device' => 'friend-2', 'smdd' => 'Gate B', 'smsj' => '2026-05-01 09:00:08', 'crlx' => 'out']);
    Pass::create(['gh' => '3002', 'xm' => 'Friend Candidate', 'device' => 'friend-3', 'smdd' => 'Gate A', 'smsj' => '2026-05-01 10:00:06', 'crlx' => 'in']);

    // 3003 appears together only twice.
    Pass::create(['gh' => '3003', 'xm' => 'Observer Candidate', 'device' => 'obs-1', 'smdd' => 'Gate A', 'smsj' => '2026-05-01 08:00:04', 'crlx' => 'in']);
    Pass::create(['gh' => '3003', 'xm' => 'Observer Candidate', 'device' => 'obs-2', 'smdd' => 'Gate A', 'smsj' => '2026-05-01 10:00:04', 'crlx' => 'in']);

    // This one should be ignored due to different direction.
    Pass::create(['gh' => '3002', 'xm' => 'Friend Candidate', 'device' => 'friend-ignore', 'smdd' => 'Gate A', 'smsj' => '2026-05-01 12:00:06', 'crlx' => 'out']);

    $response = $this->get('/students/profile/3001')->assertOk();

    $response->assertSee('"recentPasses"', false);
    $response->assertSee('"dev-a3"', false);
    $response->assertDontSee('"old-device"', false);
    $response->assertSee('"companionInsights"', false);
    $response->assertSee('"xgh":"3002"', false);
    $response->assertSee('"companion_count":3', false);
    $response->assertSee('"is_possible_friend":true', false);
    $response->assertSee('"xgh":"3003"', false);
    $response->assertSee('"companion_count":2', false);
});

