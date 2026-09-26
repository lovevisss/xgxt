<?php

use App\Models\Student;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('database.connections.middata', array_merge(
        config('database.connections.sqlite'),
        ['database' => ':memory:']
    ));

    Schema::connection('middata')->create('t_cx_zzqxryxx', function (Blueprint $table) {
        $table->string('xgh');
        foreach (['xm', 'xbm', 'rylx', 'dwmc', 'dwbm', 'bjbm', 'bjmc', 'dzyx', 'yddh', 'csrq', 'jg', 'mzm', 'sfzjh', 'politicalcode', 'zgxl', 'wlkh', 'zhbz'] as $column) {
            $table->string($column)->nullable();
        }
    });

    foreach (range(1, 4) as $number) {
        Student::query()->create([
            'xgh' => (string) $number,
            'xm' => '学生'.$number,
            'xbm' => '1',
            'rylx' => '0',
            'dwmc' => '会计学院',
            'dwbm' => 'KJ',
            'bjbm' => '24KJ1',
            'bjmc' => '24会计1班',
        ]);
    }
});

it('graduates missing students and restores them when they reappear', function () {
    $source = fn (int $number) => [
        'xgh' => (string) $number,
        'xm' => '学生'.$number,
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'KJ',
        'bjbm' => '24KJ1',
        'bjmc' => '24会计1班',
    ];
    DB::connection('middata')->table('t_cx_zzqxryxx')->insert(array_map($source, [1, 2, 3]));

    $this->artisan('sync:students-from-middata')
        ->expectsOutputToContain('转为毕业 1')
        ->assertExitCode(0);

    $graduate = Student::query()->findOrFail('4');
    expect($graduate->student_category)->toBe(Student::CATEGORY_GRADUATED)
        ->and($graduate->graduated_at)->not->toBeNull();

    $this->getJson('/students/data')->assertOk()->assertJsonPath('summary.total', 3);
    $this->getJson('/students/filters?grade=24')->assertOk()->assertJsonPath('grades.0.total_count', 3);
    $this->get('/graduates')->assertOk()->assertSee('data-page="students"', false);
    $this->getJson('/graduates/data?q=会计学院')->assertOk()->assertJsonPath('data.0.xgh', '4');
    $this->get('/students/profile/4')->assertOk()
        ->assertSee('"student_category":"graduated"', false)
        ->assertSee('"canUpdateFamilies":false', false);

    DB::connection('middata')->table('t_cx_zzqxryxx')->insert($source(4));
    $this->artisan('sync:students-from-middata')
        ->expectsOutputToContain('恢复在校 1')
        ->assertExitCode(0);

    $graduate->refresh();
    expect($graduate->student_category)->toBe(Student::CATEGORY_CURRENT)
        ->and($graduate->graduated_at)->toBeNull();
    $this->getJson('/graduates/data')->assertOk()->assertJsonPath('total', 0);
});

it('does not graduate anyone when the source is empty or drops below the safety threshold', function () {
    $this->artisan('sync:students-from-middata')->assertExitCode(1);
    expect(Student::query()->where('student_category', Student::CATEGORY_GRADUATED)->count())->toBe(0);

    DB::connection('middata')->table('t_cx_zzqxryxx')->insert([
        ['xgh' => '1', 'xm' => '学生1', 'rylx' => '0'],
        ['xgh' => '2', 'xm' => '学生2', 'rylx' => '0'],
    ]);
    $this->artisan('sync:students-from-middata')->assertExitCode(1);
    expect(Student::query()->where('student_category', Student::CATEGORY_GRADUATED)->count())->toBe(0);

    Schema::connection('middata')->drop('t_cx_zzqxryxx');
    expect(fn () => $this->artisan('sync:students-from-middata'))->toThrow(Exception::class);
    expect(Student::query()->where('student_category', Student::CATEGORY_GRADUATED)->count())->toBe(0);
});
