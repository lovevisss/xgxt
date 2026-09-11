<?php

namespace App\Console\Commands;

use App\Services\CounselorWorkbookImport;
use Illuminate\Console\Command;

class ImportCounselorAssignmentsFromExcel extends Command
{
    protected $signature = 'import:counselor-assignments {path} {--preview}';

    protected $description = '预览或替换 Excel 内辅导员的带班关系';

    public function handle(CounselorWorkbookImport $import): int
    {
        if (! is_file($this->argument('path'))) {
            $this->error('文件不存在');

            return self::FAILURE;
        }
        $result = $import->preview($this->argument('path'), true);
        foreach ($result['errors'] as $error) {
            $this->error($error);
        }
        if ($result['errors']) {
            return self::FAILURE;
        }
        if (! $this->option('preview')) {
            $result = $import->commit($this->argument('path'), true);
        }
        $records = collect($result['records']);
        $this->info(json_encode(['preview' => (bool) $this->option('preview'), 'teachers' => $records->count(), 'new_teachers' => $records->where('created_user', true)->count(),
            'added' => $records->sum(fn ($r) => count($r['added'])), 'removed' => $records->sum(fn ($r) => count($r['removed'])), 'unmatched' => $result['unmatched']], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
