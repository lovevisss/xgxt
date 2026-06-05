<?php

namespace App\Console\Commands;

use App\Services\StudentAcademicYearAverageService;
use Illuminate\Console\Command;

class CalculateStudentAcademicYearAverages extends Command
{
    protected $signature = 'calculate:student-academic-year-averages {--academic-year= : 只计算指定学年，如 2025-2026}';

    protected $description = '按学年计算学生学习平均成绩，并生成班级排名与专业排名';

    public function handle(StudentAcademicYearAverageService $service): int
    {
        $startedAt = microtime(true);
        $academicYear = trim((string) $this->option('academic-year')) ?: null;
        $result = $service->calculate($academicYear);
        $elapsed = round(microtime(true) - $startedAt, 2);

        $this->info("学习平均成绩计算完成，学生 {$result['students']} 人，班级 {$result['classes']} 个，专业 {$result['majors']} 个，耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }
}
