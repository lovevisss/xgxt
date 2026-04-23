<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPassesFromMiddata extends Command
{
    protected $signature = 'sync:passes-from-middata {--days=3 : 同步最近N天数据}';
    protected $description = '同步只读业务中间库 t_ejxyybt_xssmsljl 到本地 Pass';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $days = max(1, (int) $this->option('days'));
        $startTime = now()->subDays($days)->startOfDay();
        $chunkSize = 2000;
        $total = 0;

        // 🔥 游标分页起点（无ID表最优方案：基于 smsj 游标）
        $lastSmsj = $startTime;
        $lastGh = '';

        while (true) {
            // 查询下一批数据（无offset，性能爆炸）
            $rows = DB::connection('middata')
                ->table('t_ejxyybt_xssmsljl')
                ->select(['gh', 'xm', 'device', 'smdd', 'smsj', 'sblx', 'crlx'])
                ->where('smsj', '>=', $startTime)
                // 游标条件：避免重复，超快查询
                ->where(function ($query) use ($lastSmsj, $lastGh) {
                    $query->where('smsj', '>', $lastSmsj)
                        ->orWhere(function ($q) use ($lastSmsj, $lastGh) {
                            $q->where('smsj', '=', $lastSmsj)
                                ->where('gh', '>', $lastGh);
                        });
                })
                ->orderBy('smsj')
                ->orderBy('gh')
                ->limit($chunkSize)
                ->get();

            // 无数据，退出循环
            if ($rows->isEmpty()) {
                break;
            }

            $now = now();
            $batch = [];

            foreach ($rows as $row) {
                if (! $row->gh || ! $row->smsj) {
                    continue;
                }
                $batch[] = [
                    'gh' => $row->gh,
                    'xm' => $row->xm,
                    'device' => $row->device,
                    'smdd' => $row->smdd,
                    'smsj' => $row->smsj,
                    'sblx' => $row->sblx,
                    'crlx' => $row->crlx,
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 批量写入 + 事务（核心提速）
            if (!empty($batch)) {
                DB::transaction(function () use ($batch) {
                    DB::table('passes')->upsert(
                        $batch,
                        ['gh', 'smsj', 'device'],
                        ['xm', 'smdd', 'sblx', 'crlx', 'synced_at', 'updated_at']
                    );
                });
                $total += count($batch);
            }

            // 更新游标位置
            $lastRow = $rows->last();
            $lastSmsj = $lastRow->smsj;
            $lastGh = $lastRow->gh;

            // 减少日志打印（避免IO卡顿）
            if ($total % 20000 === 0) {
                $this->info("已同步: $total 条...");
            }
        }

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("✅ Pass同步完成，共处理 {$total} 条记录，耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }
}
