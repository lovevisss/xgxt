<?php

namespace App\Console\Commands;

use App\Models\CounselorClassAssignment;
use App\Models\CounselorCollegeDelegation;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use App\Services\CounselorClassCatalog;
use App\Services\CounselorClassOwnership;
use App\Services\CounselorManagement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RestoreWuChengchengDelegation extends Command
{
    protected $signature = 'counselors:restore-wu-delegation {--preview}';

    protected $description = '核对并恢复吴晨程代管法律学院的四个社工班';

    private const CLASSES = [
        '24206201' => '20250205',
        '24206202' => '20250205',
        '26206201' => null,
        '26206202' => null,
    ];

    public function handle(CounselorClassCatalog $catalog, CounselorClassOwnership $ownership, CounselorManagement $management): int
    {
        try {
            $result = DB::transaction(function () use ($catalog, $ownership, $management) {
                $user = User::where('cas_username', '20250903')->lockForUpdate()->firstOrFail();
                $staff = StaffMember::where('employee_no', '20250903')->where('is_active', true)->firstOrFail();
                if ($user->name !== '吴晨程' || $staff->name !== '吴晨程' || $staff->department_code !== '100405') {
                    throw new RuntimeException('在职身份或所属部门已变化，请人工核对。');
                }
                $classes = $catalog->all()->filter(fn ($row) => $row['college_code'] === '100306'
                    && array_key_exists((string) $row['class_code'], self::CLASSES))->keyBy('class_code');
                if ($classes->count() !== 4) throw new RuntimeException('四个正式社工班未全部找到。');
                $expected = [];
                $alreadyRestored = true;
                foreach (self::CLASSES as $code => $previous) {
                    if (! Student::where('student_category', Student::CATEGORY_CURRENT)->where('rylx', '0')
                        ->where('dwbm', '100306')->where('bjbm', $code)->exists()) {
                        throw new RuntimeException($code.' 当前没有在校学生，请人工核对。');
                    }
                    $owners = $ownership->owners($classes[$code], true);
                    $numbers = $owners->pluck('counselor_cas_username')->unique()->values()->all();
                    if ($numbers !== ['20250903']) $alreadyRestored = false;
                    if ($numbers !== [$previous] && $numbers !== ['20250903']) {
                        if (! ($previous === null && $numbers === [])) {
                            throw new RuntimeException($code.' 的负责人已变化，请重新核对。');
                        }
                    }
                    $expected[$code] = $owners->pluck('id')->all();
                }
                if ($this->option('preview')) {
                    return ['status' => $alreadyRestored ? 'already_restored' : 'ready', 'classes' => $classes->map(fn ($row) => $row['class_name'])->all(),
                        'current_assignment_ids' => $expected];
                }
                $delegation = CounselorCollegeDelegation::where('employee_no', '20250903')->where('college_code', '100306')->lockForUpdate()->first();
                if ($alreadyRestored && $delegation?->is_active) return ['status' => 'already_restored'];
                $before = $delegation?->toArray();
                $delegation = CounselorCollegeDelegation::updateOrCreate(['employee_no' => '20250903', 'college_code' => '100306'],
                    ['college_name' => '法律与社会工作学院', 'reason' => '学工部临时代管社工班', 'is_active' => true, 'ended_at' => null]);
                if (! $before || ! $before['is_active']) $management->log('delegation.grant', '20250903', $before, $delegation->toArray());
                foreach (array_keys(self::CLASSES) as $code) {
                    $ownership->assign($user, $classes[$code], true, $expected[$code], 'manual');
                }
                $management->log('delegation.restore', '20250903', ['previous_owners' => $expected],
                    ['delegation' => $delegation->toArray(), 'assignments' => $user->classAssignments()->where('college_code', '100306')->get()->toArray()]);

                return ['status' => 'restored', 'classes' => $user->classAssignments()->where('college_code', '100306')->pluck('class_code')->all()];
            });
            if (! $this->option('preview')) $catalog->forget();
            $this->info(json_encode($result, JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
