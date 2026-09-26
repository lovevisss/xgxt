<?php

use App\Models\Student;
use App\Models\StudentCadreAssessment;
use App\Models\StudentImportTask;
use App\Jobs\ImportStudentCadreAssessments;
use App\Services\StudentCadreAssessmentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('parses cadre assessment rows from pdf text', function () {
    $service = app(StudentCadreAssessmentImportService::class);

    $records = $service->parseText(
        '浙江财经大学东方学院 2025—2026 学年第一学期团学干部考核成绩汇总表'."\n".
        '孙 俊 会计学院党群服务中心 培训学习部 负责人 10.00 15.65 26.00 36.00 87.65 良好'."\n",
        '2025-2026',
        null,
        'test.pdf'
    );

    expect($records)->toHaveCount(1)
        ->and($records[0]['student_name'])->toBe('孙俊')
        ->and($records[0]['organization'])->toBe('会计学院党群服务中心')
        ->and($records[0]['department'])->toBe('培训学习部')
        ->and($records[0]['position'])->toBe('负责人')
        ->and($records[0]['total_score'])->toBe(87.65)
        ->and($records[0]['grade'])->toBe('良好')
        ->and($records[0]['semester'])->toBe('1');
});

it('imports cadre assessments from docx tables by student number', function () {
    Student::query()->create([
        'xgh' => '2420110227',
        'xm' => '雷雨晴',
        'xbm' => '2',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'KJ',
        'bjmc' => '24会计2班',
    ]);

    $path = storage_path('app/test-cadre-assessment.docx');
    $archive = new ZipArchive();
    expect($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    $archive->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
    $archive->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
    $archive->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:tbl>
    <w:tr>
      <w:tc><w:p><w:r><w:t>姓名</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>学号</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>所在二级学院(团学机构)</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>班级（部门）</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>任职</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>自评10%</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>民主测评20%</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>指导老师测评30%</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>指导部门测评40%</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>总分</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>考核等级</w:t></w:r></w:p></w:tc>
    </w:tr>
    <w:tr>
      <w:tc><w:p><w:r><w:t>雷雨晴</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>2420110227</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>会计学院学生会</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>办公室</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>负责人</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>10.00</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>18.00</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>29.00</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>38.00</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>95.00</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>优秀</w:t></w:r></w:p></w:tc>
    </w:tr>
    <w:tr>
      <w:tc><w:p><w:r><w:t>未匹配学生</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>9999999999</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>会计学院学生会</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>办公室</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>干事</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>10</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>18</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>25</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>30</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>83</w:t></w:r></w:p></w:tc>
      <w:tc><w:p><w:r><w:t>良好</w:t></w:r></w:p></w:tc>
    </w:tr>
  </w:tbl></w:body>
</w:document>
XML);
    $archive->close();

    $file = new UploadedFile(
        $path,
        '团学干部考核成绩汇总表.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );

    $this->postJson('/student-imports/cadre_assessment', [
        'file' => $file,
        'academic_year' => '2025-2026',
        'semester' => '1',
    ])
        ->assertStatus(202)
        ->assertJsonPath('queued', true)
        ->assertJsonPath('status', StudentImportTask::STATUS_QUEUED);

    $task = StudentImportTask::query()->firstOrFail();
    app()->call([new ImportStudentCadreAssessments($task->id), 'handle']);

    $this->getJson("/student-imports/status/{$task->id}")
        ->assertOk()
        ->assertJsonPath('status', StudentImportTask::STATUS_SUCCEEDED)
        ->assertJsonPath('result.imported', 1)
        ->assertJsonPath('result.processed', 2)
        ->assertJsonPath('result.total', 2)
        ->assertJsonPath('result.pending', 1);

    $this->getJson("/student-imports/status/{$task->id}/matches")
        ->assertOk()
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.student_name', '未匹配学生');

    $this->assertDatabaseHas('student_cadre_assessments', [
        'student_xgh' => '2420110227',
        'student_name' => '雷雨晴',
        'academic_year' => '2025-2026',
        'semester' => '1',
        'organization' => '会计学院学生会',
        'department' => '办公室',
        'position' => '负责人',
        'total_score' => 95,
        'grade' => '优秀',
    ]);

    $uploadId = (string) Str::uuid();
    $contents = file_get_contents($path);
    $parts = str_split($contents, (int) ceil(strlen($contents) / 3));

    $this->postJson('/student-imports/cadre_assessment', [
        'upload_phase' => 'complete',
        'upload_id' => $uploadId,
        'total' => count($parts),
        'file_name' => '团学干部考核成绩汇总表.docx',
        'academic_year' => '2025-2026',
    ])->assertUnprocessable();

    foreach ($parts as $index => $part) {
        $this->postJson('/student-imports/cadre_assessment', [
            'upload_phase' => 'chunk',
            'upload_id' => $uploadId,
            'index' => $index,
            'total' => count($parts),
            'file' => UploadedFile::fake()->createWithContent('part.bin', $part),
        ])->assertOk()->assertJsonPath('received', $index);
    }

    $this->postJson('/student-imports/cadre_assessment', [
        'upload_phase' => 'complete',
        'upload_id' => $uploadId,
        'total' => count($parts),
        'file_name' => '团学干部考核成绩汇总表.docx',
        'academic_year' => '2025-2026',
        'semester' => '1',
    ])->assertStatus(202)->assertJsonPath('queued', true);

    $chunkTask = StudentImportTask::query()->latest('id')->firstOrFail();
    expect($chunkTask->original_name)->toBe('团学干部考核成绩汇总表.docx')
        ->and(file_get_contents(Storage::disk('local')->path($chunkTask->path)))->toBe($contents);

    app()->call([new ImportStudentCadreAssessments($chunkTask->id), 'handle']);
    $this->getJson("/student-imports/status/{$chunkTask->id}")
        ->assertOk()
        ->assertJsonPath('status', StudentImportTask::STATUS_SUCCEEDED)
        ->assertJsonPath('result.imported', 1);
});

it('shows cadre assessments on the student profile', function () {
    Student::query()->create([
        'xgh' => '20260001',
        'xm' => '孙俊',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '会计学院',
        'dwbm' => 'KJ',
        'bjmc' => '25会计1班',
    ]);

    StudentCadreAssessment::query()->create([
        'student_xgh' => '20260001',
        'student_name' => '孙俊',
        'academic_year' => '2025-2026',
        'semester' => '1',
        'organization' => '会计学院党群服务中心',
        'department' => '培训学习部',
        'position' => '负责人',
        'total_score' => 87.65,
        'grade' => '良好',
        'sync_key' => 'test-cadre-20260001',
    ]);

    $this->get('/students/profile/20260001')
        ->assertOk()
        ->assertSee('"cadreAssessments":[', false)
        ->assertSee('"organization":"\u4f1a\u8ba1\u5b66\u9662\u515a\u7fa4\u670d\u52a1\u4e2d\u5fc3"', false)
        ->assertSee('"position":"\u8d1f\u8d23\u4eba"', false)
        ->assertSee('"grade":"\u826f\u597d"', false);
});
