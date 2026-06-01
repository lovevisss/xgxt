<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCourseSchedulesFromMiddata extends Command
{
    protected $signature = 'sync:course-schedules-from-middata {--semester= : 只同步指定学年学期(如 2026-2027-1)，留空则同步全部}';

    protected $description = '同步中间库全校课程表与学生课表到本地 course_sections / student_course_schedules';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $totalSections = 0;
        $totalSchedules = 0;
        $hbxxTrimmedCount = 0;
        $hbxxTrimmedSamples = [];
        $progressStep = 5000;
        $lastSectionsLogged = 0;
        $lastSchedulesLogged = 0;
        $semester = trim((string) $this->option('semester'));
        $readChunkSize = 1000;
        $upsertBatchSize = 500;

        $courseQuery = DB::connection('middata')->table('t_ejxyybt_qxkcb')
            ->select('*')
            ->whereNotNull('jxb_id')
            ->where('jxb_id', '!=', '');

        if ($semester !== '') {
            [$academicYear, $term] = $this->parseSemester($semester);
            $courseQuery->where('xn', $academicYear);
            if ($term !== null) {
                $courseQuery->where('xq', (string) $term);
            }
        }

        $courseQuery->orderBy('jxb_id')->chunk($readChunkSize, function ($rows) use (&$totalSections, &$hbxxTrimmedCount, &$hbxxTrimmedSamples, &$lastSectionsLogged, $progressStep, $upsertBatchSize) {
            $now = now();
            $payload = [];

            foreach ($rows as $row) {
                $parsed = $this->parseScheduleString((string) ($row->sksj ?? ''), (string) ($row->qsjsz ?? ''));
                $rawHbxx = $this->nullableString($row->hbxx ?? null);
                $hbxx = $this->limitedString($rawHbxx, 255);
                if ($rawHbxx !== null && $hbxx !== $rawHbxx) {
                    $hbxxTrimmedCount++;
                    if (count($hbxxTrimmedSamples) < 5) {
                        $hbxxTrimmedSamples[] = trim((string) $row->jxb_id);
                    }
                }

                $payload[] = [
                    'jxb_id' => trim((string) $row->jxb_id),
                    'kkzt' => $this->nullableString($row->kkzt ?? null),
                    'kch' => $this->nullableString($row->kch ?? null),
                    'kcmc' => $this->nullableString($row->kcmc ?? null),
                    'xf' => $this->nullableString($row->xf ?? null),
                    'jxbmc' => $this->nullableString($row->jxbmc ?? null),
                    'kclb' => $this->nullableString($row->kclb ?? null),
                    'kcxz' => $this->nullableString($row->kcxz ?? null),
                    'kcgs' => $this->nullableString($row->kcgs ?? null),
                    'kkxiaoq' => $this->nullableString($row->kkxiaoq ?? null),
                    'ktrl' => $this->nullableString($row->ktrl ?? null),
                    'yxrs' => $this->nullableString($row->yxrs ?? null),
                    'zxs' => $this->nullableString($row->zxs ?? null),
                    'jgh' => $this->nullableString($row->jgh ?? null),
                    'rkjs' => $this->nullableString($row->rkjs ?? null),
                    'jszc' => $this->nullableString($row->jszc ?? null),
                    'sksj' => $this->nullableString($row->sksj ?? null),
                    'jxdd' => $this->nullableString($row->jxdd ?? null),
                    'lh' => $this->nullableString($row->lh ?? null),
                    'khfs' => $this->nullableString($row->khfs ?? null),
                    'ksxs' => $this->nullableString($row->ksxs ?? null),
                    'kkxy' => $this->nullableString($row->kkxy ?? null),
                    'hbxx' => $hbxx,
                    'xn' => $this->nullableString($row->xn ?? null),
                    'xq' => $this->nullableString($row->xq ?? null),
                    'qsjsz' => $this->nullableString($row->qsjsz ?? null),
                    'weekday' => $parsed['weekday'],
                    'period_start' => $parsed['period_start'],
                    'period_end' => $parsed['period_end'],
                    'week_start' => $parsed['week_start'],
                    'week_end' => $parsed['week_end'],
                    'week_pattern' => $parsed['week_pattern'],
                    'synced_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            }

            if ($payload === []) {
                return;
            }

            foreach (array_chunk($payload, $upsertBatchSize) as $batch) {
                DB::table('course_sections')->upsert(
                    $batch,
                    ['jxb_id'],
                    ['kkzt','kch','kcmc','xf','jxbmc','kclb','kcxz','kcgs','kkxiaoq','ktrl','yxrs','zxs','jgh','rkjs','jszc','sksj','jxdd','lh','khfs','ksxs','kkxy','hbxx','xn','xq','qsjsz','weekday','period_start','period_end','week_start','week_end','week_pattern','synced_at','updated_at']
                );
            }

            $totalSections += count($payload);

            if ($totalSections - $lastSectionsLogged >= $progressStep) {
                $lastSectionsLogged = $totalSections;
                $this->info("已同步(课程主数据): {$totalSections} 条...");
            }
        });

        $scheduleQuery = DB::connection('middata')->table('t_ejxyybt_bzkskbxx')
            ->select('*')
            ->whereNotNull('pkbh')
            ->where('pkbh', '!=', '')
            ->whereNotNull('xh')
            ->where('xh', '!=', '');

        if ($semester !== '') {
            $scheduleQuery->where('xnxq', $semester);
        }

        $scheduleQuery->orderBy('xnxq')->orderBy('xh')->orderBy('pkbh')->chunk($readChunkSize, function ($rows) use (&$totalSchedules, &$lastSchedulesLogged, $progressStep, $upsertBatchSize) {
            $now = now();
            $payload = [];

            foreach ($rows as $row) {
                $studentNo = trim((string) $this->pick($row, ['xh', 'XH', '学号']));
                $pkbh = trim((string) $this->pick($row, ['pkbh', 'PKBH']));

                if ($studentNo === '' || $pkbh === '') {
                    continue;
                }

                $scheduleText = (string) $this->pick($row, ['sksj', 'SKSJ']);
                $parsed = $this->parseScheduleString($scheduleText, '');
                $weekday = $parsed['weekday'] ?? $this->normalizeWeekday($this->intOrNull($this->pick($row, ['xqj', 'XQJ'])));
                [$jcStart, $jcEnd] = $this->parseJcRange((string) $this->pick($row, ['jc', 'JC']));

                $payload[] = [
                    'xnxq' => $this->nullableString($this->pick($row, ['xnxq', 'XNXQ'])),
                    'xh' => $studentNo,
                    'pkbh' => $pkbh,
                    'kkyxbm' => $this->nullableString($this->pick($row, ['kkyxbm', 'KKYXBM'])),
                    'kkzybm' => $this->nullableString($this->pick($row, ['kkzybm', 'KKZYBM'])),
                    'kkbjbm' => $this->nullableString($this->pick($row, ['kkbjbm', 'KKBJBM'])),
                    'kcbm' => $this->nullableString($this->pick($row, ['kcbm', 'KCBM'])),
                    'zc' => $this->nullableString($this->pick($row, ['zc', 'ZC'])),
                    'qsz' => $this->intOrNull($this->pick($row, ['qsz', 'QSZ'])),
                    'zzz' => $this->intOrNull($this->pick($row, ['zzz', 'ZZZ'])),
                    'dsz' => $this->nullableString($this->pick($row, ['dsz', 'DSZ'])),
                    'xqj' => $this->intOrNull($this->pick($row, ['xqj', 'XQJ'])),
                    'jc' => $this->nullableString($this->pick($row, ['jc', 'JC'])),
                    'sksj' => $this->nullableString($scheduleText),
                    'jxdd' => $this->nullableString($this->pick($row, ['jxdd', 'JXDD'])),
                    'jslxm' => $this->nullableString($this->pick($row, ['jslxm', 'JSLXM'])),
                    'xf' => $this->nullableString($this->pick($row, ['xf', 'XF'])),
                    'llxs' => $this->nullableString($this->pick($row, ['llxs', 'LLXS'])),
                    'syxs' => $this->nullableString($this->pick($row, ['syxs', 'SYXS'])),
                    'sjxs' => $this->nullableString($this->pick($row, ['sjxs', 'SJXS'])),
                    'zxs' => $this->nullableString($this->pick($row, ['zxs', 'ZXS'])),
                    'skjsgh' => $this->nullableString($this->pick($row, ['skjsgh', 'SKJSGH'])),
                    'skjsxm' => $this->nullableString($this->pick($row, ['skjsxm', 'SKJSXM'])),
                    'kcxzm' => $this->nullableString($this->pick($row, ['kcxzm', 'KCXZM'])),
                    'kcsxm' => $this->nullableString($this->pick($row, ['kcsxm', 'KCSXM'])),
                    'kslbm' => $this->nullableString($this->pick($row, ['kslbm', 'KSLBM'])),
                    'ksfsm' => $this->nullableString($this->pick($row, ['ksfsm', 'KSFSM'])),
                    'ksxzm' => $this->nullableString($this->pick($row, ['ksxzm', 'KSXZM'])),
                    'weekday_label' => $this->weekdayLabel($weekday),
                    'weekday' => $weekday,
                    'period_start' => $parsed['period_start'] ?? $jcStart,
                    'period_end' => $parsed['period_end'] ?? $jcEnd,
                    'week_start' => $this->intOrNull($this->pick($row, ['qsz', 'QSZ'])) ?: $parsed['week_start'],
                    'week_end' => $this->intOrNull($this->pick($row, ['zzz', 'ZZZ'])) ?: $parsed['week_end'],
                    'week_pattern' => $this->weekPatternFromRow($this->pick($row, ['dsz', 'DSZ']), $scheduleText),
                    'tstamp' => $this->nullableString($this->pick($row, ['tstamp', 'TSTAMP'])),
                    'synced_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            }

            if ($payload === []) {
                return;
            }

            foreach (array_chunk($payload, $upsertBatchSize) as $batch) {
                DB::table('student_course_schedules')->upsert(
                    $batch,
                    ['xnxq', 'xh', 'pkbh'],
                    ['kkyxbm','kkzybm','kkbjbm','kcbm','zc','qsz','zzz','dsz','xqj','jc','sksj','jxdd','jslxm','xf','llxs','syxs','sjxs','zxs','skjsgh','skjsxm','kcxzm','kcsxm','kslbm','ksfsm','ksxzm','weekday_label','weekday','period_start','period_end','week_start','week_end','week_pattern','tstamp','synced_at','updated_at']
                );
            }

            $totalSchedules += count($payload);

            if ($totalSchedules - $lastSchedulesLogged >= $progressStep) {
                $lastSchedulesLogged = $totalSchedules;
                $this->info("已同步(学生课表): {$totalSchedules} 条...");
            }
        });

        $elapsed = round(microtime(true) - $startedAt, 2);
        if ($hbxxTrimmedCount > 0) {
            $sampleText = implode(', ', $hbxxTrimmedSamples);
            $this->warn("检测到 hbxx 超长并截断 {$hbxxTrimmedCount} 条，样例教学班ID: {$sampleText}");
        }
        $this->info("课程表同步完成，课表主数据 {$totalSections} 条，学生课表 {$totalSchedules} 条，耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }

    private function parseSemester(string $semester): array
    {
        if (preg_match('/^(\d{4}-\d{4})-(\d)$/', $semester, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        return [$semester, null];
    }

    private function parseScheduleString(string $scheduleText, string $weeksText): array
    {
        $weekday = null;
        if (preg_match('/星期([一二三四五六日天])/u', $scheduleText, $weekdayMatches) === 1) {
            $weekday = $this->weekdayToNumber($weekdayMatches[1]);
        }

        $periodStart = null;
        $periodEnd = null;
        if (preg_match('/第(\d+)(?:-(\d+))?节/u', $scheduleText, $periodMatches) === 1) {
            $periodStart = (int) $periodMatches[1];
            $periodEnd = isset($periodMatches[2]) && $periodMatches[2] !== '' ? (int) $periodMatches[2] : $periodStart;
        }

        $weekStart = null;
        $weekEnd = null;
        $weekPattern = null;
        $source = $weeksText !== '' ? $weeksText : $scheduleText;
        if (preg_match('/(\d+)-(\d+)周(?:\(([单双])\))?/u', $source, $weekMatches) === 1) {
            $weekStart = (int) $weekMatches[1];
            $weekEnd = (int) $weekMatches[2];
            $weekPattern = $this->normalizePattern($weekMatches[3] ?? null);
        } elseif (preg_match('/(\d+)周(?:\(([单双])\))?/u', $source, $weekMatches) === 1) {
            $weekStart = (int) $weekMatches[1];
            $weekEnd = (int) $weekMatches[1];
            $weekPattern = $this->normalizePattern($weekMatches[2] ?? null);
        }

        return [
            'weekday' => $weekday,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_pattern' => $weekPattern,
        ];
    }

    /**
     * @return array{0:?int, 1:?int}
     */
    private function parseJcRange(string $jc): array
    {
        $jc = trim($jc);
        if ($jc === '') {
            return [null, null];
        }

        if (preg_match('/^(\d+)$/', $jc, $matches) === 1) {
            $value = (int) $matches[1];

            return [$value, $value];
        }

        if (preg_match('/^(\d+)\s*[-~]\s*(\d+)$/', $jc, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [null, null];
    }

    private function weekdayToNumber(string $weekday): ?int
    {
        return match ($weekday) {
            '一' => 1,
            '二' => 2,
            '三' => 3,
            '四' => 4,
            '五' => 5,
            '六' => 6,
            '日', '天' => 7,
            default => null,
        };
    }

    private function weekdayLabel(?int $weekday): ?string
    {
        return match ($weekday) {
            1 => '周一',
            2 => '周二',
            3 => '周三',
            4 => '周四',
            5 => '周五',
            6 => '周六',
            7 => '周日',
            default => null,
        };
    }

    private function weekPatternFromRow(mixed $dsz, mixed $scheduleText): ?string
    {
        $normalized = trim((string) $dsz);
        if ($normalized !== '') {
            return $this->normalizePattern($normalized);
        }

        if (preg_match('/\(([单双])\)/u', (string) $scheduleText, $matches) === 1) {
            return $this->normalizePattern($matches[1]);
        }

        return null;
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

    private function normalizeWeekday(?int $weekday): ?int
    {
        if ($weekday === null) {
            return null;
        }

        if ($weekday === 0) {
            return 7;
        }

        return $weekday >= 1 && $weekday <= 7 ? $weekday : null;
    }

    private function normalizePattern(?string $pattern): ?string
    {
        $pattern = trim((string) $pattern);

        return match ($pattern) {
            '单', 'odd' => 'odd',
            '双', 'even' => 'even',
            default => $pattern === '' ? null : $pattern,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function limitedString(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
        }

        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        $normalized = trim((string) $value);

        return $normalized === '' || ! is_numeric($normalized) ? null : (int) $normalized;
    }
}
