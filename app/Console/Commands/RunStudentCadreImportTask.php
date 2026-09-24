<?php

namespace App\Console\Commands;

use App\Jobs\ImportStudentCadreAssessments;
use App\Services\StudentCadreAssessmentImportService;
use Illuminate\Console\Command;

class RunStudentCadreImportTask extends Command
{
    protected $signature = 'student-import:run-cadre {task : Import task ID}';

    protected $description = 'Run a student cadre assessment import task';

    public function handle(StudentCadreAssessmentImportService $importer): int
    {
        (new ImportStudentCadreAssessments((int) $this->argument('task')))->handle($importer);

        return self::SUCCESS;
    }
}
