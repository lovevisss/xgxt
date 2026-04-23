<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentsFromMiddata extends Command
{
    protected $signature = 'sync:students-from-middata';

    protected $description = '同步只读业务中间库 t_cx_zzqxryxx rylx=0 内容到本地 Student';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $total = 0;
        $chunkSize = 2000;

        DB::connection('middata')
            ->table('t_cx_zzqxryxx')
            ->select([
                'xgh', 'xm', 'xbm', 'rylx', 'dwmc', 'dwbm', 'bjbm', 'bjmc', 'dzyx', 'yddh',
                'csrq', 'jg', 'mzm', 'sfzjh', 'politicalcode', 'zgxl', 'wlkh', 'zhbz',
            ])
            ->where('rylx', '0')
            ->orderBy('xgh')
            ->chunk($chunkSize, function ($students) use (&$total) {
                $now = now();
                $rows = [];

                foreach ($students as $s) {
                    if (! $s->xgh) {
                        continue;
                    }

                    $rows[] = [
                        'xgh' => $s->xgh,
                        'xm' => $s->xm,
                        'xbm' => $s->xbm,
                        'rylx' => $s->rylx,
                        'dwmc' => $s->dwmc,
                        'dwbm' => $s->dwbm,
                        'bjbm' => $s->bjbm,
                        'bjmc' => $s->bjmc,
                        'dzyx' => $s->dzyx,
                        'yddh' => $s->yddh,
                        'csrq' => $s->csrq,
                        'jg' => $s->jg,
                        'mzm' => $s->mzm,
                        'sfzjh' => $s->sfzjh,
                        'politicalcode' => $s->politicalcode,
                        'zgxl' => $s->zgxl,
                        'wlkh' => $s->wlkh,
                        'zhbz' => $s->zhbz,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('students')->upsert(
                        $rows,
                        ['xgh'],
                        [
                            'xm', 'xbm', 'rylx', 'dwmc', 'dwbm', 'bjbm', 'bjmc', 'dzyx', 'yddh',
                            'csrq', 'jg', 'mzm', 'sfzjh', 'politicalcode', 'zgxl', 'wlkh', 'zhbz',
                            'updated_at',
                        ]
                    );
                    $total += count($rows);
                }

                $this->info("已同步: $total 条...");
            });

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("同步完成, 总共同步 {$total} 条, 耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }
}
