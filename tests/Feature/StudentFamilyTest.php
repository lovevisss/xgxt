<?php

use App\Models\Student;
use App\Models\StudentFamily;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('lists and updates student family records', function () {
    $family = StudentFamily::query()->create([
        'sync_key' => md5('20260001|张三|父亲|'),
        'stu_no' => '20260001',
        'name' => '张三',
        'relationship' => '父亲',
        'work_unit' => '某单位',
        'position' => '工程师',
        'phone' => '13800000000',
        'is_emergency_contact' => true,
    ]);

    $this->getJson('/student-families/data?q=20260001')
        ->assertOk()
        ->assertJsonPath('data.0.stu_no', '20260001');

    $this->putJson("/student-families/data/{$family->id}", [
        'work_unit' => '新单位',
        'position' => '主管',
        'phone' => '13900000000',
        'is_emergency_contact' => 0,
    ])->assertOk();

    $this->assertDatabaseHas('student_families', [
        'id' => $family->id,
        'work_unit' => '新单位',
        'position' => '主管',
        'phone' => '13900000000',
        'is_emergency_contact' => 0,
        'is_local_modified' => 1,
    ]);
});

it('syncs family records and keeps local edits by default', function () {
    config()->set('database.connections.middata', array_merge(
        config('database.connections.sqlite'),
        ['database' => ':memory:']
    ));

    Schema::connection('middata')->create('t_ejxyybt_xsjtxxb', function (Blueprint $table) {
        $table->string('stu_no');
        $table->string('name')->nullable();
        $table->string('relationship')->nullable();
        $table->string('work_unit')->nullable();
        $table->string('position')->nullable();
        $table->string('phone')->nullable();
        $table->string('specific_relationship')->nullable();
        $table->tinyInteger('is_emergency_contact')->default(0);
    });

    DB::connection('middata')->table('t_ejxyybt_xsjtxxb')->insert([
        'stu_no' => '20260002',
        'name' => '李四',
        'relationship' => '母亲',
        'work_unit' => '旧单位',
        'position' => '职员',
        'phone' => '13700000000',
        'specific_relationship' => null,
        'is_emergency_contact' => 1,
    ]);

    $this->artisan('sync:student-families-from-middata')->assertExitCode(0);

    $this->assertDatabaseHas('student_families', [
        'stu_no' => '20260002',
        'name' => '李四',
        'work_unit' => '旧单位',
    ]);

    StudentFamily::query()
        ->where('stu_no', '20260002')
        ->update([
            'work_unit' => '本地修正单位',
            'is_local_modified' => true,
            'local_modified_at' => now(),
        ]);

    DB::connection('middata')->table('t_ejxyybt_xsjtxxb')->update([
        'work_unit' => '中间库新单位',
    ]);

    $this->artisan('sync:student-families-from-middata')->assertExitCode(0);

    $this->assertDatabaseHas('student_families', [
        'stu_no' => '20260002',
        'work_unit' => '本地修正单位',
        'is_local_modified' => 1,
    ]);
});

it('searches family records by student name', function () {
    Student::query()->create([
        'xgh' => '20260003',
        'xm' => '王同学',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '测试学院',
        'dwbm' => 'T',
    ]);

    StudentFamily::query()->create([
        'sync_key' => md5('20260003|王家长|父亲|'),
        'stu_no' => '20260003',
        'name' => '王家长',
        'relationship' => '父亲',
        'phone' => '13600000000',
    ]);

    $this->getJson('/student-families/data?q=王同学')
        ->assertOk()
        ->assertJsonPath('data.0.stu_no', '20260003')
        ->assertJsonPath('data.0.student_name', '王同学');
});

it('shows a student profile page with family info', function () {
    Student::query()->create([
        'xgh' => '20260004',
        'xm' => '赵同学',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '测试学院',
        'dwbm' => 'T',
        'bjmc' => '24级1班',
    ]);

    StudentFamily::query()->create([
        'sync_key' => md5('20260004|赵家长|母亲|'),
        'stu_no' => '20260004',
        'name' => '赵家长',
        'relationship' => '母亲',
        'is_emergency_contact' => true,
    ]);

    $this->get('/students/profile/20260004')
        ->assertOk()
        ->assertSee('data-page="studentProfile"', false)
        ->assertSee('"xgh":"20260004"', false)
        ->assertSee('"stu_no":"20260004"', false);
});

it('allows only same department counselors and super admins to update family records', function () {
    config()->set('cas.enabled', true);

    Student::query()->create([
        'xgh' => '20260005',
        'xm' => 'Permission Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => 'Finance College',
        'dwbm' => 'FIN',
    ]);

    $family = StudentFamily::query()->create([
        'sync_key' => md5('20260005|Parent|Father|'),
        'stu_no' => '20260005',
        'name' => 'Parent',
        'relationship' => 'Father',
        'phone' => '13600000001',
    ]);

    User::factory()->create([
        'cas_username' => 'counselor-other',
        'role' => 'counselor',
        'dwbm' => 'ART',
    ]);

    $this->withSession([
        config('cas.session_key') => ['user' => 'counselor-other'],
    ])->putJson("/student-families/data/{$family->id}", [
        'phone' => '13600000002',
    ])->assertForbidden();

    User::factory()->create([
        'cas_username' => 'counselor-fin',
        'role' => 'counselor',
        'dwbm' => 'FIN',
    ]);

    $this->withSession([
        config('cas.session_key') => ['user' => 'counselor-fin'],
    ])->putJson("/student-families/data/{$family->id}", [
        'phone' => '13600000003',
    ])->assertOk();

    $this->assertDatabaseHas('student_families', [
        'id' => $family->id,
        'phone' => '13600000003',
    ]);

    User::factory()->create([
        'cas_username' => 'root-user',
        'role' => 'super_admin',
        'dwbm' => null,
    ]);

    $this->withSession([
        config('cas.session_key') => ['user' => 'root-user'],
    ])->putJson("/student-families/data/{$family->id}", [
        'phone' => '13600000004',
    ])->assertOk();
});
