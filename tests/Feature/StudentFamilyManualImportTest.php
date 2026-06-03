<?php

use App\Jobs\ImportStudentFamilyContacts;
use App\Models\StudentImportTask;
use App\Models\Student;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('downloads the student family import template from unified import center', function () {
    $this->get('/student-imports/template/family')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports manual student family contacts from excel into student families', function () {
    Student::query()->create([
        'xgh' => '20230001',
        'xm' => 'Profile Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'ACC',
    ]);

    $path = storage_path('app/test-student-family.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '学生信息' => [
            ['2020-2023级学生信息汇总'],
            ['学号', '姓名', '学院', '专业', '班级', '身份证号', '性别', '民族', '政治面貌', '籍贯', '入学前户口', '本人联系手机号', '家庭座机号', 'QQ号', '家庭住址', '户籍地址', '身高', '体重', '家庭成员称谓1', '姓名1', '工作单位1', '职务1', '联系电话1', '家庭成员称谓2', '姓名2', '工作单位2', '职务2', '联系电话2', '家庭成员称谓3', '姓名3', '工作单位3', '职务3', '联系电话3'],
            ['20230001', '张三', '会计学院', '会计学', '23会计1', '', '男', '汉族', '', '', '', '', '', '', '', '', '', '', '父亲', '张建国', '杭州某公司', '经理', '13900000001', '母亲', '李芳', '杭州某学校', '教师', '13900000002', '父亲', '张建国', '杭州某公司', '经理', '13900000001'],
        ],
    ]);

    $file = new UploadedFile($path, 'student-family.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/family', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('imported', 2)
        ->assertJsonPath('students', 1)
        ->assertJsonPath('skipped', 0);

    $this->assertDatabaseCount('student_families', 2);
    $this->assertDatabaseHas('student_families', [
        'stu_no' => '20230001',
        'name' => '张建国',
        'relationship' => '父亲',
        'specific_relationship' => '父亲',
        'work_unit' => '杭州某公司',
        'position' => '经理',
        'phone' => '13900000001',
        'is_local_modified' => 1,
        'is_emergency_contact' => 0,
    ]);
    $this->assertDatabaseHas('student_families', [
        'stu_no' => '20230001',
        'name' => '李芳',
        'relationship' => '母亲',
        'phone' => '13900000002',
        'is_local_modified' => 1,
    ]);

    $this->get('/students/profile/20230001')
        ->assertOk()
        ->assertSee('"families":[', false)
        ->assertSee('"name":"\u5f20\u5efa\u56fd"', false)
        ->assertSee('"name":"\u674e\u82b3"', false);
});

it('queues large manual student family imports and exposes task status', function () {
    Queue::fake();

    $path = storage_path('app/test-student-family-queued.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '学生信息' => [
            ['学号', '姓名', '家庭成员称谓1', '姓名1', '工作单位1', '职务1', '联系电话1'],
            ['20230003', '王五', '父亲', '王建', '某公司', '主管', '13900000003'],
        ],
    ]);

    $file = new UploadedFile($path, 'student-family-queued.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/family', [
        'file' => $file,
        'async' => '1',
    ])
        ->assertStatus(202)
        ->assertJsonPath('queued', true)
        ->assertJsonPath('status', StudentImportTask::STATUS_QUEUED);

    $task = StudentImportTask::query()->firstOrFail();

    Queue::assertPushed(ImportStudentFamilyContacts::class, fn (ImportStudentFamilyContacts $job) => $job->taskId === $task->id);

    $this->getJson("/student-imports/status/{$task->id}")
        ->assertOk()
        ->assertJsonPath('id', $task->id)
        ->assertJsonPath('status', StudentImportTask::STATUS_QUEUED)
        ->assertJsonPath('result.imported', 0);
});
