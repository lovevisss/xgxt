<?php

use App\Models\Student;
use App\Models\StudentClass;
use App\Models\StudentCourseGrade;
use App\Services\StudentAcademicYearAverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('calculates academic year weighted averages and rankings', function () {
    StudentClass::query()->create(['class_code' => '250101', 'class_name' => '25会计1班', 'major_code' => '2501']);
    StudentClass::query()->create(['class_code' => '250102', 'class_name' => '25会计2班', 'major_code' => '2501']);

    Student::query()->create(['xgh' => '20250001', 'xm' => '甲同学', 'xbm' => '1', 'rylx' => '0', 'dwmc' => '会计学院', 'dwbm' => 'ACC', 'bjbm' => '250101', 'bjmc' => '25会计1班']);
    Student::query()->create(['xgh' => '20250002', 'xm' => '乙同学', 'xbm' => '1', 'rylx' => '0', 'dwmc' => '会计学院', 'dwbm' => 'ACC', 'bjbm' => '250101', 'bjmc' => '25会计1班']);
    Student::query()->create(['xgh' => '20250003', 'xm' => '丙同学', 'xbm' => '1', 'rylx' => '0', 'dwmc' => '会计学院', 'dwbm' => 'ACC', 'bjbm' => '250102', 'bjmc' => '25会计2班']);

    StudentCourseGrade::query()->create(['xh' => '20250001', 'xnxq' => '2025-2026-1', 'kcbm' => 'A001', 'kcmc' => '高等数学', 'cj' => '80', 'xf' => 3, 'ksxz' => '正常考试']);
    StudentCourseGrade::query()->create(['xh' => '20250001', 'xnxq' => '2025-2026-1', 'kcbm' => 'A002', 'kcmc' => '大学英语', 'cj' => '50', 'xf' => 2, 'ksxz' => '正常考试']);
    StudentCourseGrade::query()->create(['xh' => '20250001', 'xnxq' => '2025-2026-1', 'kcbm' => 'A002', 'kcmc' => '大学英语', 'cj' => '70', 'xf' => 2, 'ksxz' => '补考']);
    StudentCourseGrade::query()->create(['xh' => '20250001', 'xnxq' => '2025-2026-2', 'kcbm' => 'A003', 'kcmc' => '会计学', 'cj' => '旷考', 'xf' => 1, 'ksxz' => '正常考试']);
    StudentCourseGrade::query()->create(['xh' => '20250001', 'xnxq' => '2025-2026-2', 'kcbm' => 'PE01', 'kcmc' => '大学体育', 'cj' => '100', 'xf' => 5, 'ksxz' => '正常考试']);
    StudentCourseGrade::query()->create(['xh' => '20250001', 'xnxq' => '2025-2026-2', 'kcbm' => 'SC01', 'kcmc' => '第二课堂（综合素质拓展）', 'cj' => '100', 'xf' => 10, 'ksxz' => '正常考试']);

    StudentCourseGrade::query()->create(['xh' => '20250002', 'xnxq' => '2025-2026-1', 'kcbm' => 'A001', 'kcmc' => '高等数学', 'cj' => '70', 'xf' => 3, 'ksxz' => '正常考试']);
    StudentCourseGrade::query()->create(['xh' => '20250002', 'xnxq' => '2025-2026-1', 'kcbm' => 'A002', 'kcmc' => '大学英语', 'cj' => '65', 'xf' => 2, 'ksxz' => '正常考试']);
    StudentCourseGrade::query()->create(['xh' => '20250003', 'xnxq' => '2025-2026-1', 'kcbm' => 'A001', 'kcmc' => '高等数学', 'cj' => '90', 'xf' => 3, 'ksxz' => '正常考试']);

    $result = app(StudentAcademicYearAverageService::class)->calculate('2025-2026');

    expect($result['students'])->toBe(3);

    $this->assertDatabaseHas('student_academic_year_averages', [
        'student_xgh' => '20250001',
        'academic_year' => '2025-2026',
        'average_score' => 60,
        'total_credits' => 6,
        'course_count' => 3,
        'class_rank' => 2,
        'class_size' => 2,
        'major_rank' => 3,
        'major_size' => 3,
    ]);

    $this->assertDatabaseHas('student_academic_year_averages', [
        'student_xgh' => '20250003',
        'academic_year' => '2025-2026',
        'average_score' => 90,
        'major_rank' => 1,
    ]);
});

it('shows academic year average calculation courses on student profile', function () {
    Student::query()->create(['xgh' => '20250001', 'xm' => '甲同学', 'xbm' => '1', 'rylx' => '0', 'dwmc' => '会计学院', 'dwbm' => 'ACC', 'bjbm' => '250101', 'bjmc' => '25会计1班']);
    StudentCourseGrade::query()->create(['xh' => '20250001', 'xnxq' => '2025-2026-1', 'kcbm' => 'A001', 'kcmc' => '高等数学', 'cj' => '80', 'xf' => 3, 'ksxz' => '正常考试']);

    DB::table('student_academic_year_averages')->insert([
        'student_xgh' => '20250001',
        'student_name' => '甲同学',
        'academic_year' => '2025-2026',
        'average_score' => 80,
        'total_credits' => 3,
        'course_count' => 1,
        'class_rank' => 1,
        'class_size' => 1,
        'major_rank' => 1,
        'major_size' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get('/students/profile/20250001')
        ->assertOk()
        ->assertSee('"academicYearAverages":[', false)
        ->assertSee('"average_score":80', false)
        ->assertSee('"class_rank":1', false)
        ->assertSee('"major_rank":1', false)
        ->assertSee('"calculation_courses":[', false)
        ->assertSee('"course_name":"\u9ad8\u7b49\u6570\u5b66"', false)
        ->assertSee('"weighted_score":240', false);
});
