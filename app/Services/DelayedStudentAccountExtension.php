<?php

namespace App\Services;

use App\Models\Student;
use App\Models\SyncAccountChange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DelayedStudentAccountExtension
{
    public function run(int $taskId): array
    {
        $lock = Cache::lock('delayed-student-account-extension', 3600);
        if (! $lock->get()) {
            throw new RuntimeException('已有延毕生账号延期任务正在执行。');
        }

        try {
            return $this->extend($taskId);
        } finally {
            $lock->release();
        }
    }

    private function extend(int $taskId): array
    {
        $today = now()->startOfDay();
        $targetDate = $today->copy()->addYear()->toDateString();
        $academicYear = $today->month >= 9 ? $today->year : $today->year - 1;
        $cohortCutoff = ($academicYear % 100) - 4;

        $candidates = Student::query()
            ->where('rylx', '0')
            ->where('student_category', Student::CATEGORY_CURRENT)
            ->get(['xgh', 'xm', 'dwmc'])
            ->filter(fn (Student $student) => preg_match('/^[0-9]{10}$/D', (string) $student->xgh)
                && (int) substr($student->xgh, 0, 2) <= $cohortCutoff)
            ->keyBy('xgh');

        if ($candidates->isEmpty()) {
            return ['cutoff' => $cohortCutoff, 'target_date' => $targetDate, 'candidates' => 0, 'matched' => 0, 'updated' => 0, 'unfrozen' => 0, 'missing' => 0, 'skipped' => 0];
        }

        $currentNumbers = collect();
        foreach ($candidates->keys()->chunk(500) as $numbers) {
            $currentNumbers = $currentNumbers->concat(
                DB::connection('middata')->table('t_cx_zzqxryxx')
                    ->where('rylx', '0')->whereIn('xgh', $numbers->all())->pluck('xgh')
            );
        }
        $currentNumbers = $currentNumbers->unique()->values();
        $eligible = $candidates->only($currentNumbers->all());
        $result = [
            'cutoff' => $cohortCutoff,
            'target_date' => $targetDate,
            'candidates' => $candidates->count(),
            'matched' => $eligible->count(),
            'updated' => 0,
            'unfrozen' => 0,
            'missing' => 0,
            'skipped' => $candidates->count() - $eligible->count(),
        ];

        if ($eligible->isEmpty()) {
            return $result;
        }

        $status = DB::connection('status');
        try {
            $status->beginTransaction();
            DB::beginTransaction();

            foreach ($eligible->values()->chunk(500) as $students) {
                $accounts = $status->table('tb_b_account')
                    ->whereIn('ACCOUNT_NAME', $students->pluck('xgh')->all())
                    ->lockForUpdate()
                    ->get(['ID', 'ACCOUNT_NAME', 'ACCOUNT_EXPIRY_DATE', 'STATE', 'DELETED', 'ACCOUNT_LOCKED'])
                    ->keyBy('ACCOUNT_NAME');

                foreach ($students as $student) {
                    $studentNumber = $student->xgh;
                    $account = $accounts->get($studentNumber);
                    if (! $account) {
                        $result['missing']++;
                        continue;
                    }
                    if ((int) $account->DELETED !== 0 || ! in_array($account->ACCOUNT_LOCKED, [null, 0, '0'], true)
                        || ! in_array($account->STATE, ['NORMAL', 'FREEZE'], true)) {
                        $result['skipped']++;
                        continue;
                    }

                    $oldDate = $account->ACCOUNT_EXPIRY_DATE;
                    $newDate = $oldDate === null || $oldDate < $targetDate ? $targetDate : $oldDate;
                    $newState = $account->STATE === 'FREEZE' ? 'NORMAL' : $account->STATE;
                    if ($newDate === $oldDate && $newState === $account->STATE) {
                        continue;
                    }

                    $status->table('tb_b_account')->where('ID', $account->ID)->update([
                        'ACCOUNT_EXPIRY_DATE' => $newDate,
                        'STATE' => $newState,
                    ]);
                    SyncAccountChange::query()->create([
                        'sync_task_id' => $taskId,
                        'student_xgh' => $studentNumber,
                        'student_name' => $student->xm,
                        'college_name' => $student->dwmc,
                        'previous_expiry_date' => $oldDate,
                        'new_expiry_date' => $newDate,
                        'previous_state' => $account->STATE,
                        'new_state' => $newState,
                        'changed_at' => now(),
                    ]);
                    $result['updated']++;
                    if ($newState !== $account->STATE) {
                        $result['unfrozen']++;
                    }
                }
            }

            $status->commit();
            DB::commit();
        } catch (Throwable $exception) {
            if ($status->transactionLevel() > 0) {
                $status->rollBack();
            }
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $result;
    }
}
