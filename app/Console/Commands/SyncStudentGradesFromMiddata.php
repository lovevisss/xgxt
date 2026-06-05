<?php

namespace App\Console\Commands;

use App\Services\StudentAcademicYearAverageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentGradesFromMiddata extends Command
{
    protected $signature = 'sync:student-grades-from-middata {--semester= : 只同步指定学年学期(如 2025-2026-1)}';

    protected $description = '同步中间库课程基本信息与成绩信息到本地 course_basics / student_course_grades';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $totalBasics = 0;
        $totalGrades = 0;
        $progressStep = 5000;
        $lastBasicsLogged = 0;
        $lastGradesLogged = 0;
        $semester = trim((string) $this->option('semester'));
        $minAcademicStartYear = (int) now()->year - 3;
        $readChunkSize = 1000;
        $upsertBatchSize = 500;

        DB::connection('middata')
            ->table('t_ejxyybt_bzkskcjbxx')
            ->select('*')
            ->whereNotNull('kcbm')
            ->where('kcbm', '!=', '')
            ->orderBy('kcbm')
            ->chunk($readChunkSize, function ($rows) use (&$totalBasics, &$lastBasicsLogged, $progressStep, $upsertBatchSize) {
                $now = now();
                $payload = [];

                foreach ($rows as $row) {
                    $kcbm = trim((string) $this->pick($row, ['kcbm', 'KCBM']));
                    if ($kcbm === '') {
                        continue;
                    }

                    $payload[] = [
                        'kcbm' => $kcbm,
                        'kcmc' => $this->nullableString($this->pick($row, ['kcmc', 'KCMC'])),
                        'raw' => json_encode((array) $row, JSON_UNESCAPED_UNICODE),
                        'synced_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];
                }

                foreach (array_chunk($payload, $upsertBatchSize) as $batch) {
                    DB::table('course_basics')->upsert(
                        $batch,
                        ['kcbm'],
                        ['kcmc', 'raw', 'synced_at', 'updated_at']
                    );
                }

                $totalBasics += count($payload);

                if ($totalBasics - $lastBasicsLogged >= $progressStep) {
                    $lastBasicsLogged = $totalBasics;
                    $this->info("已同步(课程基础): {$totalBasics} 条...");
                }
            });

        DB::connection('middata')
            ->table('t_ejxyybt_bzkscjxx')
            ->select('*')
            ->whereNotNull('xh')
            ->where('xh', '!=', '')
            ->whereNotNull('kcbm')
            ->where('kcbm', '!=', '')
            ->when($semester !== '', fn ($query) => $query->where('xnxq', $semester))
            ->when($semester === '', function ($query) use ($minAcademicStartYear) {
                $driver = DB::connection('middata')->getDriverName();
                $yearExpr = $driver === 'sqlite'
                    ? "CAST(substr(xnxq, 1, 4) AS INTEGER)"
                    : "CAST(SUBSTRING(xnxq, 1, 4) AS UNSIGNED)";

                $query->whereRaw("{$yearExpr} >= ?", [$minAcademicStartYear]);
            })
            ->orderBy('xh')
            ->orderBy('xnxq')
            ->orderBy('kcbm')
            ->chunk($readChunkSize, function ($rows) use (&$totalGrades, &$lastGradesLogged, $progressStep, $upsertBatchSize) {
                $now = now();
                $payload = [];

                $courseNames = DB::table('course_basics')
                    ->whereIn('kcbm', collect($rows)->map(fn ($row) => trim((string) $this->pick($row, ['kcbm', 'KCBM'])))->filter()->unique()->values())
                    ->pluck('kcmc', 'kcbm');

                foreach ($rows as $row) {
                    $xh = trim((string) $this->pick($row, ['xh', 'XH']));
                    $xnxq = trim((string) $this->pick($row, ['xnxq', 'XNXQ']));
                    $kcbm = trim((string) $this->pick($row, ['kcbm', 'KCBM']));
                    $ksxz = trim((string) $this->pick($row, ['ksxz', 'KSXZ'], ''));

                    if ($xh === '' || $xnxq === '' || $kcbm === '') {
                        continue;
                    }

                    $kcmc = $this->nullableString($this->pick($row, ['kcmc', 'KCMC'])) ?: ($courseNames[$kcbm] ?? null);

                    $payload[] = [
                        'xh' => $xh,
                        'xnxq' => $xnxq,
                        'kcbm' => $kcbm,
                        'kcmc' => $kcmc,
                        'cj' => $this->nullableString($this->pick($row, ['cj', 'CJ'])),
                        'jd' => $this->decimalOrNull($this->pick($row, ['jd', 'JD'])),
                        'xf' => $this->decimalOrNull($this->pick($row, ['xf', 'XF'])),
                        'ksxz' => $ksxz,
                        'raw' => json_encode((array) $row, JSON_UNESCAPED_UNICODE),
                        'synced_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];
                }

                foreach (array_chunk($payload, $upsertBatchSize) as $batch) {
                    DB::table('student_course_grades')->upsert(
                        $batch,
                        ['xh', 'xnxq', 'kcbm', 'ksxz'],
                        ['kcmc', 'cj', 'jd', 'xf', 'raw', 'synced_at', 'updated_at']
                    );
                }

                $totalGrades += count($payload);

                if ($totalGrades - $lastGradesLogged >= $progressStep) {
                    $lastGradesLogged = $totalGrades;
                    $this->info("已同步(成绩): {$totalGrades} 条...");
                }
            });

        $academicYear = $this->academicYearFromSemester($semester);
        $averageResult = app(StudentAcademicYearAverageService::class)->calculate($academicYear);
        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("成绩同步完成，课程基本信息 {$totalBasics} 条，成绩 {$totalGrades} 条，耗时 {$elapsed} 秒");

        $this->info("学习平均成绩计算完成：{$averageResult['students']} 人");

        return self::SUCCESS;
    }

    private function pick(object|array $row, array $candidates, mixed $default = null): mixed
    {
        $data = (array) $row;

        foreach ($candidates as $candidate) {
            $names = [$candidate, strtoupper($candidate), strtolower($candidate)];
            foreach ($names as $name) {
                if (! array_key_exists($name, $data)) {
                    continue;
                }

                $value = $data[$name];
                if ($value !== null && trim((string) $value) !== '') {
                    return $value;
                }
            }
        }

        return $default;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function decimalOrNull(mixed $value): ?float
    {
        $normalized = trim((string) $value);
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function academicYearFromSemester(string $semester): ?string
    {
        return preg_match('/(20\d{2})\s*[-—]\s*(20\d{2})/', $semester, $matches)
            ? $matches[1].'-'.$matches[2]
            : null;
    }
}
