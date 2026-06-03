<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentEducationHistoriesFromMiddata extends Command
{
    protected $signature = 'sync:student-education-histories-from-middata {--student= : 只同步指定学号}';

    protected $description = '同步中间库 t_ejxyybt_xsjyjlxx 到本地 student_education_histories';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $totalRead = 0;
        $totalUpserted = 0;
        $chunkSize = 2000;
        $upsertBatchSize = 500;
        $progressStep = 5000;
        $lastLogged = 0;
        $student = trim((string) $this->option('student'));

        DB::connection('middata')
            ->table('t_ejxyybt_xsjyjlxx')
            ->select([
                'id',
                'stu_no',
                'qualifications',
                'start_year',
                'end_year',
                'school_name',
                'update_time',
                'create_time',
                'sort',
            ])
            ->whereNotNull('stu_no')
            ->where('stu_no', '!=', '')
            ->when($student !== '', fn ($query) => $query->where('stu_no', $student))
            ->orderBy('stu_no')
            ->orderBy('sort')
            ->orderBy('id')
            ->chunk($chunkSize, function ($rows) use (&$totalRead, &$totalUpserted, &$lastLogged, $progressStep, $upsertBatchSize) {
                $totalRead += $rows->count();
                $now = now();
                $payload = [];

                foreach ($rows as $row) {
                    $sourceId = trim((string) ($row->id ?? ''));
                    $studentNumber = trim((string) ($row->stu_no ?? ''));

                    if ($sourceId === '' || $studentNumber === '') {
                        continue;
                    }

                    $payload[] = [
                        'source_id' => $sourceId,
                        'stu_no' => $studentNumber,
                        'qualifications' => $this->nullableString($row->qualifications ?? null),
                        'start_year' => $this->nullableString($row->start_year ?? null),
                        'end_year' => $this->nullableString($row->end_year ?? null),
                        'school_name' => $this->nullableString($row->school_name ?? null),
                        'sort' => is_numeric($row->sort ?? null) ? (int) $row->sort : null,
                        'source_created_at' => $this->nullableString($row->create_time ?? null),
                        'source_updated_at' => $this->nullableString($row->update_time ?? null),
                        'synced_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($payload, $upsertBatchSize) as $batch) {
                    DB::table('student_education_histories')->upsert(
                        $batch,
                        ['source_id'],
                        [
                            'stu_no',
                            'qualifications',
                            'start_year',
                            'end_year',
                            'school_name',
                            'sort',
                            'source_created_at',
                            'source_updated_at',
                            'synced_at',
                            'updated_at',
                        ]
                    );
                }

                $totalUpserted += count($payload);

                if ($totalUpserted - $lastLogged >= $progressStep) {
                    $lastLogged = $totalUpserted;
                    $this->info("已同步教育经历 {$totalUpserted} 条...");
                }
            });

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("教育经历同步完成，读取 {$totalRead} 条，写入 {$totalUpserted} 条，耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
