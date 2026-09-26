<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\CounselorClassCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncStudentsFromMiddata extends Command
{
    private const MINIMUM_SOURCE_RATIO = 0.70;

    protected $signature = 'sync:students-from-middata';

    protected $description = '同步只读业务中间库 t_cx_zzqxryxx rylx=0 内容到本地 Student';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $total = 0;
        $chunkSize = 2000;
        $progressStep = 20000;
        $lastLogged = 0;
        $created = 0;
        $updated = 0;
        $restored = 0;
        $syncId = (string) Str::uuid();
        $existing = Student::query()->pluck('student_category', 'xgh');
        $currentBefore = $existing->filter(fn ($category) => $category === Student::CATEGORY_CURRENT)->count();
        $sourceQuery = DB::connection('middata')->table('t_cx_zzqxryxx')
            ->where('rylx', '0')
            ->whereNotNull('xgh')
            ->where('xgh', '!=', '');
        $sourceCount = (clone $sourceQuery)->count();

        if ($sourceCount === 0 || ($currentBefore > 0 && $sourceCount < (int) ceil($currentBefore * self::MINIMUM_SOURCE_RATIO))) {
            $this->error("中间库学生人数异常：来源 {$sourceCount}，原在校 {$currentBefore}。已停止同步，未调整毕业生分类。");

            return self::FAILURE;
        }

        $sourceQuery
            ->select([
                'xgh', 'xm', 'xbm', 'rylx', 'dwmc', 'dwbm', 'bjbm', 'bjmc', 'dzyx', 'yddh',
                'csrq', 'jg', 'mzm', 'sfzjh', 'politicalcode', 'zgxl', 'wlkh', 'zhbz',
            ])
            ->orderBy('xgh')
            ->chunk($chunkSize, function ($students) use (&$total, &$lastLogged, &$created, &$updated, &$restored, $progressStep, $syncId, $existing) {
                $now = now();
                $rows = [];

                foreach ($students as $s) {
                    if (! $s->xgh) {
                        continue;
                    }

                    if (! $existing->has($s->xgh)) {
                        $created++;
                    } elseif ($existing->get($s->xgh) === Student::CATEGORY_GRADUATED) {
                        $restored++;
                    } else {
                        $updated++;
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
                        'student_category' => Student::CATEGORY_CURRENT,
                        'graduated_at' => null,
                        'source_sync_id' => $syncId,
                    ];
                }

                if ($rows !== []) {
                    DB::table('students')->upsert(
                        $rows,
                        ['xgh'],
                        [
                            'xm', 'xbm', 'rylx', 'dwmc', 'dwbm', 'bjbm', 'bjmc', 'dzyx', 'yddh',
                            'csrq', 'jg', 'mzm', 'sfzjh', 'politicalcode', 'zgxl', 'wlkh', 'zhbz',
                            'updated_at', 'student_category', 'graduated_at', 'source_sync_id',
                        ]
                    );
                    $total += count($rows);
                }

                if ($progressStep <= $total - $lastLogged) {
                    $lastLogged = $total;
                    $this->info("已同步: {$total} 条...");
                }
            });

        if ($total !== $sourceCount) {
            $this->error("中间库读取数量由 {$sourceCount} 变为 {$total}，未调整毕业生分类，请重试。");

            return self::FAILURE;
        }

        $graduated = Student::query()
            ->where('rylx', '0')
            ->where('student_category', Student::CATEGORY_CURRENT)
            ->where(function ($query) use ($syncId) {
                $query->whereNull('source_sync_id')->orWhere('source_sync_id', '!=', $syncId);
            })
            ->update(['student_category' => Student::CATEGORY_GRADUATED, 'graduated_at' => now()]);

        $elapsed = round(microtime(true) - $startedAt, 2);
        app(CounselorClassCatalog::class)->forget();
        $this->info("同步完成：来源 {$total}，新增 {$created}，更新 {$updated}，转为毕业 {$graduated}，恢复在校 {$restored}，耗时 {$elapsed} 秒");

        return self::SUCCESS;
    }
}
