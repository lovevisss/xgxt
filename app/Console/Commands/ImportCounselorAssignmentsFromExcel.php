<?php

namespace App\Console\Commands;

use App\Models\CounselorClassAssignment;
use App\Models\Student;
use App\Models\StudentClass;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportCounselorAssignmentsFromExcel extends Command
{
    protected $signature = 'import:counselor-assignments {path : 辅导员带班通讯录 Excel 路径}';

    protected $description = '从辅导员带班通讯录导入辅导员用户和带班关系';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("文件不存在：{$path}");

            return self::FAILURE;
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $rows = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

            for ($row = 1; $row <= $highestRow; $row++) {
                $values = [];
                for ($column = 1; $column <= $highestColumn; $column++) {
                    $values[] = trim((string) $sheet->getCell([$column, $row])->getFormattedValue());
                }
                $rows[] = $values;
            }
        }

        $spreadsheet->disconnectWorksheets();

        $headerIndex = $this->headerIndex($rows);
        if ($headerIndex === null) {
            $this->error('未找到表头，需要包含：工号、姓名、所属院系、手机、电话、办公室、带班情况');

            return self::FAILURE;
        }

        $headers = $rows[$headerIndex];
        $indexes = $this->indexes($headers);
        $importedCounselors = 0;
        $importedAssignments = 0;

        DB::transaction(function () use ($rows, $headerIndex, $indexes, &$importedCounselors, &$importedAssignments): void {
            foreach (array_slice($rows, $headerIndex + 1) as $row) {
                $employeeNo = $this->cell($row, $indexes['employee_no'] ?? null);
                $name = $this->cell($row, $indexes['name'] ?? null);
                $collegeName = $this->cell($row, $indexes['college_name'] ?? null);

                if ($employeeNo === '' || $name === '') {
                    continue;
                }

                $collegeCode = $this->collegeCode($collegeName);
                $user = User::query()->updateOrCreate(
                    ['cas_username' => $employeeNo],
                    [
                        'name' => $name,
                        'email' => "{$employeeNo}@counselor.local",
                        'password' => Hash::make(Str::random(32)),
                        'role' => User::ROLE_COUNSELOR,
                        'dwbm' => $collegeCode,
                        'dwmc' => $collegeName !== '' ? $collegeName : null,
                        'phone' => $this->nullableString($this->cell($row, $indexes['phone'] ?? null)),
                        'office_phone' => $this->nullableString($this->cell($row, $indexes['office_phone'] ?? null)),
                        'office_location' => $this->nullableString($this->cell($row, $indexes['office_location'] ?? null)),
                    ]
                );
                $importedCounselors++;

                $classes = $this->splitClasses($this->cell($row, $indexes['classes'] ?? null));
                if ($classes === []) {
                    continue;
                }

                foreach ($classes as $className) {
                    $match = $this->matchClass($className);
                    CounselorClassAssignment::query()->updateOrCreate(
                        [
                            'counselor_cas_username' => $user->cas_username,
                            'normalized_class_name' => CounselorClassAssignment::normalizeClassName($className),
                        ],
                        [
                            'user_id' => $user->id,
                            'class_code' => $match['class_code'],
                            'class_name' => $match['class_name'] ?: $className,
                            'college_code' => $collegeCode,
                            'college_name' => $collegeName !== '' ? $collegeName : null,
                            'source' => 'excel',
                        ]
                    );
                    $importedAssignments++;
                }
            }
        });

        $this->info("辅导员导入完成：{$importedCounselors} 人，带班关系 {$importedAssignments} 条");

        return self::SUCCESS;
    }

    private function headerIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $joined = implode('|', $row);
            if (str_contains($joined, '工号') && str_contains($joined, '带班情况')) {
                return $index;
            }
        }

        return null;
    }

    private function indexes(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $normalized = trim((string) $header);
            match ($normalized) {
                '工号' => $map['employee_no'] = $index,
                '姓名' => $map['name'] = $index,
                '所属院系' => $map['college_name'] = $index,
                '手机' => $map['phone'] = $index,
                '电话' => $map['office_phone'] = $index,
                '办公室' => $map['office_location'] = $index,
                '带班情况' => $map['classes'] = $index,
                default => null,
            };
        }

        return $map;
    }

    private function splitClasses(string $value): array
    {
        $normalized = str_replace(['，', ';', '；', "\n", "\r"], '、', $value);

        return collect(explode('、', $normalized))
            ->map(fn (string $item) => trim($item))
            ->filter(fn (string $item) => $item !== '' && $item !== '/')
            ->unique()
            ->values()
            ->all();
    }

    private function matchClass(string $className): array
    {
        $normalized = CounselorClassAssignment::normalizeClassName($className);

        $class = StudentClass::query()
            ->get(['class_code', 'class_name'])
            ->first(fn (StudentClass $class) => CounselorClassAssignment::normalizeClassName($class->class_name) === $normalized);

        if ($class) {
            return ['class_code' => $class->class_code, 'class_name' => $class->class_name];
        }

        $studentClass = Student::query()
            ->whereNotNull('bjmc')
            ->get(['bjbm', 'bjmc'])
            ->first(fn (Student $student) => CounselorClassAssignment::normalizeClassName($student->bjmc) === $normalized);

        return [
            'class_code' => $studentClass?->bjbm,
            'class_name' => $studentClass?->bjmc ?: $className,
        ];
    }

    private function collegeCode(string $collegeName): ?string
    {
        if ($collegeName === '') {
            return null;
        }

        $exactCode = Student::query()
            ->where('dwmc', $collegeName)
            ->whereNotNull('dwbm')
            ->value('dwbm');

        if ($exactCode) {
            return $exactCode;
        }

        $containedCode = Student::query()
            ->where('dwmc', 'like', "%{$collegeName}%")
            ->whereNotNull('dwbm')
            ->value('dwbm');

        if ($containedCode) {
            return $containedCode;
        }

        return match ($collegeName) {
            '法律与社会工作学院' => '100306',
            default => null,
        };
    }

    private function cell(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
    }

    private function nullableString(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
