<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentClassesFromMiddata extends Command
{
    protected $signature = 'sync:student-classes-from-middata';

    protected $description = '同步中间库 t_ejxyybt_bzksbjjbxx 到本地 student_classes';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $totalRead = 0;
        $totalUpserted = 0;
        $chunkSize = 2000;
        $upsertBatchSize = 500;

        DB::connection('middata')
            ->table('t_ejxyybt_bzksbjjbxx')
            ->select(['bjbm', 'bjmc', 'zybm', 'jbny', 'ssnj', 'bzxh', 'tstamp'])
            ->whereNotNull('bjbm')
            ->where('bjbm', '!=', '')
            ->orderBy('bjbm')
            ->chunk($chunkSize, function ($rows) use (&$totalRead, &$totalUpserted, $upsertBatchSize) {
                $totalRead += $rows->count();
                $now = now();
                $payload = [];

                foreach ($rows as $row) {
                    $classCode = trim((string) ($row->bjbm ?? ''));
                    if ($classCode === '') {
                        continue;
                    }

                    $payload[] = [
                        'class_code' => $classCode,
                        'class_name' => $this->nullableString($row->bjmc ?? null) ?? $classCode,
                        'major_code' => $this->nullableString($row->zybm ?? null),
                        'grade' => $this->nullableString($row->ssnj ?? null),
                        'built_at' => $this->nullableString($row->jbny ?? null),
                        'standard_student_number' => $this->nullableString($row->bzxh ?? null),
                        'source_updated_at' => $this->nullableString($row->tstamp ?? null),
                        'synced_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($payload, $upsertBatchSize) as $batch) {
                    DB::table('student_classes')->upsert(
                        $batch,
                        ['class_code'],
                        ['class_name', 'major_code', 'grade', 'built_at', 'standard_student_number', 'source_updated_at', 'synced_at', 'updated_at']
                    );
                }

                $totalUpserted += count($payload);
            });

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("班级信息同步完成，读取 {$totalRead} 条，写入 {$totalUpserted} 条，耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
