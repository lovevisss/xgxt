<?php

namespace App\Console\Commands;

use App\Models\Pass;
use App\Models\Student;
use Illuminate\Console\Command;

class ReconcileStudentPasses extends Command
{
    protected $signature = 'sync:reconcile-student-passes';

    protected $description = '对照未记录Pass并更新Student刷码时间与失联状态';

    public function handle(): int
    {
        $processed = 0;

        Pass::query()
            ->where('is_recorded', false)
            ->orderBy('smsj')
            ->chunkById(500, function ($passes) use (&$processed) {
                foreach ($passes as $pass) {
                    $student = Student::where('xgh', $pass->gh)->first();
                    if (! $student) {
                        continue;
                    }

                    if (! $student->last_smsj || $pass->smsj->gt($student->last_smsj)) {
                        $student->last_smsj = $pass->smsj;
                    }
                    $student->status = 'normal';
                    $student->updated_at = now();
                    $student->save();

                    $pass->is_recorded = true;
                    $pass->recorded_at = now();
                    $pass->save();

                    $processed++;
                }
            });

        $lostThreshold = now()->subDays(7);
        Student::query()
            ->where(function ($query) use ($lostThreshold) {
                $query->whereNull('last_smsj')
                    ->orWhere('last_smsj', '<', $lostThreshold);
            })
            ->update([
                'status' => 'lost',
                'updated_at' => now(),
            ]);

        Student::query()
            ->whereNotNull('last_smsj')
            ->where('last_smsj', '>=', $lostThreshold)
            ->update([
                'status' => 'normal',
                'updated_at' => now(),
            ]);

        $this->info("对照完成，处理未记录Pass {$processed} 条");

        return self::SUCCESS;
    }
}
