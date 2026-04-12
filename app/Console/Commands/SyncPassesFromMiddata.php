<?php

namespace App\Console\Commands;

use App\Models\Pass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPassesFromMiddata extends Command
{
    protected $signature = 'sync:passes-from-middata {--days=3 : 同步最近N天数据}';

    protected $description = '同步只读业务中间库 t_ejxyybt_xssmsljl 到本地 Pass';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $startTime = now()->subDays($days)->startOfDay();
        $chunkSize = 500;
        $total = 0;

        DB::connection('middata')
            ->table('t_ejxyybt_xssmsljl')
            ->where('smsj', '>=', $startTime)
            ->orderBy('smsj')
            ->chunk($chunkSize, function ($rows) use (&$total) {
                foreach ($rows as $row) {
                    Pass::updateOrCreate(
                        [
                            'gh' => $row->gh,
                            'smsj' => $row->smsj,
                            'device' => $row->device,
                        ],
                        [
                            'xm' => $row->xm,
                            'smdd' => $row->smdd,
                            'sblx' => $row->sblx,
                            'crlx' => $row->crlx,
                            'synced_at' => now(),
                        ]
                    );
                    $total++;
                }
            });

        $this->info("Pass同步完成，共处理 {$total} 条记录");

        return self::SUCCESS;
    }
}
