<?php

use App\Models\Student;
use App\Models\StudentCadreAssessment;
use App\Services\StudentCadreAssessmentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
