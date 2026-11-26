<?php

namespace App\Console\Commands;

use App\Services\CounselorStaffIdentityMerge;
use Illuminate\Console\Command;
use Throwable;

class MergeCounselorStaffIdentity extends Command
{
    protected $signature = 'counselors:merge-staff-identity {old} {current} {--preview}';

    protected $description = '核实并合并辅导员旧工号到当前在职工号';

    public function handle(CounselorStaffIdentityMerge $merge): int
    {
        try {
            $result = $this->option('preview')
                ? $merge->inspect($this->argument('old'), $this->argument('current'))
                : $merge->merge($this->argument('old'), $this->argument('current'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info(json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
