<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentsFromMiddata extends Command
{
    protected $signature = 'sync:students-from-middata';

    protected $description = '同步只读业务中间库 t_cx_zzqxryxx rylx=0 内容到本地 Student';

    public function handle()
    {
        $total = 0;
        $chunkSize = 500; // 一批处理500条，可按量调整

        DB::connection('middata')
            ->table('t_cx_zzqxryxx')
            ->where('rylx', '0')
            ->orderBy('xgh')
            ->chunk($chunkSize, function ($students) use (&$total) {
                foreach ($students as $s) {
                    Student::updateOrCreate(
                        ['xgh' => $s->xgh],
                        [
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
                            'updated_at' => now(),
                        ]
                    );
                    $total++;
                }
                $this->info("已同步: $total 条...");
            });

        $this->info("同步完成, 总共同步 $total 条");
    }
}
