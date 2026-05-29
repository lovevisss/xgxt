<?php

use App\Models\Student;
use App\Models\StudentMedicalInsurance;
use App\Services\StudentImportWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('downloads the student medical insurance import template from unified import center', function () {
    $this->get('/student-imports/template/medical_insurance')
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports student medical insurance records from excel', function () {
    Student::query()->create([
        'xgh' => '20260031',
        'xm' => 'Insurance Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => 'FIN',
    ]);

    $path = storage_path('app/test-student-medical-insurance.xlsx');
    app(StudentImportWorkbook::class)->write($path, [
        '参保缴费名单导出' => [
            ['姓名', '学号', '参保地', '参保日期', '险种', '参保状态', '城居参保身份', '年度', '年度是否缴费', '缴费开始年月', '缴费结束年月', '缴费类型'],
            ['Excel Name', '20260031', '西湖区', '2026-01-01', '城乡居民基本医疗保险', '正常参保', '大学生', '2026年度', '是', '202601', '202612', '足额缴纳'],
        ],
    ]);

    $file = new UploadedFile($path, 'student-medical-insurance.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->postJson('/student-imports/medical_insurance', [
        'file' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('imported', 1);

    $this->assertDatabaseHas('student_medical_insurances', [
        'student_xgh' => '20260031',
        'student_name' => 'Insurance Student',
        'insured_area' => '西湖区',
        'insurance_status' => '正常参保',
        'identity_type' => '大学生',
        'annual_year' => 2026,
        'has_paid' => true,
        'payment_start_month' => '202601',
        'payment_end_month' => '202612',
        'payment_type' => '足额缴纳',
    ]);
});

it('shows current year medical insurance status on the student profile', function () {
    $year = (int) now()->year;

    Student::query()->create([
        'xgh' => '20260032',
        'xm' => 'Profile Insurance Student',
        'xbm' => '1',
        'rylx' => '0',
        'dwmc' => '金融与经贸学院',
        'dwbm' => 'FIN',
    ]);

    StudentMedicalInsurance::query()->create([
        'student_xgh' => '20260032',
        'student_name' => 'Profile Insurance Student',
        'insured_area' => '西湖区',
        'enrolled_on' => "{$year}-01-01",
        'insurance_type' => '城乡居民基本医疗保险',
        'insurance_status' => '正常参保',
        'identity_type' => '大学生',
        'annual_year' => $year,
        'has_paid' => true,
        'payment_start_month' => "{$year}01",
        'payment_end_month' => "{$year}12",
        'payment_type' => '足额缴纳',
    ]);

    $this->get('/students/profile/20260032')
        ->assertOk()
        ->assertSee('"medicalInsurances":[', false)
        ->assertSee('"currentMedicalInsurance":', false)
        ->assertSee('"has_paid":true', false)
        ->assertSee('"insurance_status":"\u6b63\u5e38\u53c2\u4fdd"', false);
});
