<?php

use App\Models\Student;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('syncs student education histories from middata and shows them on profile', function () {
    config()->set('database.connections.middata', array_merge(
        config('database.connections.sqlite'),
        ['database' => ':memory:']
    ));

    Schema::connection('middata')->create('t_ejxyybt_xsjyjlxx', function (Blueprint $table) {
        $table->string('id');
        $table->string('stu_no');
        $table->string('qualifications')->nullable();
        $table->string('start_year')->nullable();
        $table->string('end_year')->nullable();
        $table->string('school_name')->nullable();
        $table->timestamp('update_time')->nullable();
        $table->timestamp('create_time')->nullable();
        $table->integer('sort')->nullable();
    });

    DB::connection('middata')->table('t_ejxyybt_xsjyjlxx')->insert([
        [
            'id' => '1972264986172588034',
            'stu_no' => '20260031',
            'qualifications' => '高中',
            'start_year' => '2022-09',
            'end_year' => '2025-06',
            'school_name' => '海宁市第一中学读高中',
            'update_time' => '2026-06-01 10:00:00',
            'create_time' => '2026-06-01 10:00:00',
            'sort' => 1,
        ],
        [
            'id' => '1972264986176782337',
            'stu_no' => '20260031',
            'qualifications' => '初中',
            'start_year' => '2019-09',
            'end_year' => '2022-06',
            'school_name' => '海宁市第五中学读初中',
            'update_time' => '2026-06-01 10:00:00',
            'create_time' => '2026-06-01 10:00:00',
            'sort' => 2,
        ],
    ]);

    Student::query()->create([
        'xgh' => '20260031',
        'xm' => 'Education Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '测试学院',
        'dwbm' => 'T',
    ]);

    $this->artisan('sync:student-education-histories-from-middata')->assertExitCode(0);

    $this->assertDatabaseHas('student_education_histories', [
        'source_id' => '1972264986172588034',
        'stu_no' => '20260031',
        'school_name' => '海宁市第一中学读高中',
        'sort' => 1,
    ]);

    $this->get('/students/profile/20260031')
        ->assertOk()
        ->assertSee('"educationHistories":[', false)
        ->assertSee('"school_name":"\u6d77\u5b81\u5e02\u7b2c\u4e00\u4e2d\u5b66\u8bfb\u9ad8\u4e2d"', false);
});
