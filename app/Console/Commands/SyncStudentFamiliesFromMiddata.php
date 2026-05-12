<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentFamiliesFromMiddata extends Command
{
    protected $signature = 'sync:student-families-from-middata {--overwrite-local : 覆盖本地已修改记录}';

    protected $description = '同步中间库 t_ejxyybt_xsjtxxb 到本地 student_families';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $totalRead = 0;
        $totalUpserted = 0;
        $chunkSize = 2000;
        $overwriteLocal = (bool) $this->option('overwrite-local');

        DB::connection('middata')
            ->table('t_ejxyybt_xsjtxxb')
            ->select([
                'stu_no',
                'name',
                'relationship',
                'work_unit',
                'position',
                'phone',
                'specific_relationship',
                'is_emergency_contact',
            ])
            ->whereNotNull('stu_no')
            ->where('stu_no', '!=', '')
            ->orderBy('stu_no')
            ->orderBy('name')
            ->chunk($chunkSize, function ($rows) use (&$totalRead, &$totalUpserted, $overwriteLocal) {
                $totalRead += $rows->count();
                $now = now();

                $prepared = collect($rows)
                    ->map(function ($row) use ($now) {
                        $relationship = (string) ($row->relationship ?? '');
                        $specificRelationship = (string) ($row->specific_relationship ?? '');

                        return [
                            'sync_key' => md5(implode('|', [
                                (string) $row->stu_no,
                                (string) ($row->name ?? ''),
                                $relationship,
                                $specificRelationship,
                            ])),
                            'stu_no' => (string) $row->stu_no,
                            'name' => (string) ($row->name ?? ''),
                            'relationship' => $relationship !== '' ? $relationship : null,
                            'specific_relationship' => $specificRelationship !== '' ? $specificRelationship : null,
                            'work_unit' => filled($row->work_unit ?? null) ? (string) $row->work_unit : null,
                            'position' => filled($row->position ?? null) ? (string) $row->position : null,
                            'phone' => filled($row->phone ?? null) ? (string) $row->phone : null,
                            'is_emergency_contact' => (int) ($row->is_emergency_contact ?? 0) === 1,
                            'synced_at' => $now,
                            'is_local_modified' => false,
                            'local_modified_at' => null,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ];
                    })
                    ->values();

                if ($prepared->isEmpty()) {
                    return;
                }

                $existingLocalMap = DB::table('student_families')
                    ->whereIn('sync_key', $prepared->pluck('sync_key')->all())
                    ->pluck('is_local_modified', 'sync_key');

                $upsertRows = $prepared
                    ->filter(function (array $row) use ($existingLocalMap, $overwriteLocal) {
                        if ($overwriteLocal) {
                            return true;
                        }

                        if (! $existingLocalMap->has($row['sync_key'])) {
                            return true;
                        }

                        return ! (bool) $existingLocalMap->get($row['sync_key']);
                    })
                    ->values()
                    ->all();

                if ($upsertRows === []) {
                    return;
                }

                DB::table('student_families')->upsert(
                    $upsertRows,
                    ['sync_key'],
                    [
                        'stu_no',
                        'name',
                        'relationship',
                        'specific_relationship',
                        'work_unit',
                        'position',
                        'phone',
                        'is_emergency_contact',
                        'synced_at',
                        'is_local_modified',
                        'local_modified_at',
                        'updated_at',
                    ]
                );

                $totalUpserted += count($upsertRows);
            });

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("家庭信息同步完成，读取 {$totalRead} 条，写入 {$totalUpserted} 条，耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }
}

