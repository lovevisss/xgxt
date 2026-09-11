<?php

namespace App\Console\Commands;

use App\Services\StaffDirectorySync;
use Illuminate\Console\Command;
use Throwable;

class SyncStaffFromMiddata extends Command
{
    protected $signature = 'sync:staff-from-middata';

    protected $description = '同步中间库在职教职工目录，并停用已离职人员权限';

    public function handle(StaffDirectorySync $sync): int
    {
        try {
            $result = $sync->run();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '教职工同步完成：源数据 %d 人，新增 %d，更新 %d，恢复在职 %d，停用 %d，当前在职 %d 人 / %d 个单位。',
            $result['source'],
            $result['created'],
            $result['updated'],
            $result['reactivated'],
            $result['deactivated'],
            $result['active'],
            $result['units'],
        ));

        return self::SUCCESS;
    }
}
