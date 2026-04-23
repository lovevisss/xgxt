<?php

namespace App\Console\Commands;

use App\Models\Pass;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileStudentPasses extends Command
{
    protected $signature = 'sync:reconcile-student-passes';
    protected $description = '对照未记录Pass并更新Student刷码时间与失联状态';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $now = now();
        $processedPasses = 0;
        $updatedStudents = $this->refreshStudentsFromPendingPasses($now);

        // 仅将能匹配到学生学号的Pass标记为已对照，保持原有语义。
        $processedPasses = Pass::query()
            ->where('is_recorded', false)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.xgh', 'passes.gh');
            })
            ->update([
                'is_recorded' => true,
                'recorded_at' => $now,
            ]);

        // 失联状态批量刷新
        $lostThreshold = now()->subDays(7);

        $countableScope = function ($query) use ($now) {
            $query->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('exclude_until')
                    ->orWhere('exclude_until', '<=', $now);
            });
        };

        Student::query()
            ->where($countableScope)
            ->where(function ($query) use ($lostThreshold) {
                $query->whereNull('last_smsj')
                    ->orWhere('last_smsj', '<', $lostThreshold);
            })
            ->where('status', '!=', 'lost')
            ->update([
                'status' => 'lost',
                'updated_at' => $now,
            ]);

        Student::query()
            ->where($countableScope)
            ->whereNotNull('last_smsj')
            ->where('last_smsj', '>=', $lostThreshold)
            ->where('status', '!=', 'normal')
            ->update([
                'status' => 'normal',
                'updated_at' => $now,
            ]);

        Student::query()
            ->whereNotNull('exclude_until')
            ->where('exclude_until', '>', $now)
            ->where('status', '!=', 'normal')
            ->update([
                'status' => 'normal',
                'updated_at' => $now,
            ]);

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("对照完成，Pass处理 {$processedPasses} 条，学生更新 {$updatedStudents} 条，耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }

    private function refreshStudentsFromPendingPasses($now): int
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $latestPassSub = DB::table('passes')
                ->selectRaw('gh, MAX(smsj) as max_smsj')
                ->where('is_recorded', false)
                ->groupBy('gh');

            return DB::table('students as s')
                ->joinSub($latestPassSub, 'p', function ($join) {
                    $join->on('s.xgh', '=', 'p.gh');
                })
                ->where(function ($query) {
                    $query->whereNull('s.last_smsj')
                        ->orWhereColumn('s.last_smsj', '<', 'p.max_smsj');
                })
                ->update([
                    's.last_smsj' => DB::raw('p.max_smsj'),
                    's.status' => 'normal',
                    's.updated_at' => $now,
                ]);
        }

        $updated = 0;
        DB::table('passes')
            ->selectRaw('gh, MAX(smsj) as max_smsj')
            ->where('is_recorded', false)
            ->groupBy('gh')
            ->orderBy('gh')
            ->chunk(2000, function ($groups) use (&$updated, $now) {
                $ghs = collect($groups)->pluck('gh')->filter()->values()->all();
                if ($ghs === []) {
                    return;
                }

                $existing = DB::table('students')
                    ->whereIn('xgh', $ghs)
                    ->pluck('last_smsj', 'xgh');

                $rows = [];
                foreach ($groups as $group) {
                    $gh = (string) $group->gh;
                    if (! $existing->has($gh)) {
                        continue;
                    }

                    $incoming = (string) $group->max_smsj;
                    $current = $existing->get($gh);
                    if ($current && $current >= $incoming) {
                        continue;
                    }

                    $rows[] = [
                        'xgh' => $gh,
                        'last_smsj' => $incoming,
                        'status' => 'normal',
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('students')->upsert(
                        $rows,
                        ['xgh'],
                        ['last_smsj', 'status', 'updated_at']
                    );
                    $updated += count($rows);
                }
            });

        return $updated;
    }
}
