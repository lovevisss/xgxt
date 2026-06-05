<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentCadreAssessment;
use App\Models\StudentCadreAssessmentMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

class StudentCadreAssessmentImportService
{
    private const GRADES = ['优秀', '良好', '中等', '合格', '不合格'];

    public function import(UploadedFile $file, string $academicYear, ?string $semester = null): array
    {
        $text = $this->extractText($file);
        $records = $this->parseText($text, $academicYear, $semester, $file->getClientOriginalName());
        $result = ['imported' => 0, 'pending' => 0, 'skipped' => 0, 'errors' => [], 'pending_records' => []];

        DB::transaction(function () use ($records, &$result): void {
            foreach ($records as $index => $record) {
                $match = $this->matchStudent($record);

                if ($match['student'] instanceof Student) {
                    $this->storeAssessment($record, $match['student']);
                    $result['imported']++;
                    continue;
                }

                $pending = StudentCadreAssessmentMatch::query()->create([
                    ...$record,
                    'candidate_students' => $match['candidates'],
                    'status' => StudentCadreAssessmentMatch::STATUS_PENDING,
                ]);

                $result['pending']++;
                $result['pending_records'][] = [
                    'id' => $pending->id,
                    'student_name' => $pending->student_name,
                    'organization' => $pending->organization,
                    'department' => $pending->department,
                    'position' => $pending->position,
                    'grade' => $pending->grade,
                    'candidates' => $match['candidates'],
                ];

                if ($match['candidates'] === []) {
                    $result['errors'][] = '第'.($index + 1).'条 '.$record['student_name'].' 未找到学生，请人工确认。';
                } else {
                    $result['errors'][] = '第'.($index + 1).'条 '.$record['student_name'].' 匹配到多名学生，请人工确认。';
                }
            }
        });

        return $result;
    }

    public function resolve(StudentCadreAssessmentMatch $pending, string $studentNumber): StudentCadreAssessment
    {
        $student = Student::query()->where('xgh', $studentNumber)->firstOrFail();
        $assessment = $this->storeAssessment($pending->toArray(), $student);

        $pending->update([
            'status' => StudentCadreAssessmentMatch::STATUS_RESOLVED,
            'resolved_student_xgh' => $student->xgh,
            'resolved_at' => now(),
        ]);

        return $assessment;
    }

    public function parseText(string $text, string $academicYear, ?string $semester, ?string $sourceFile = null): array
    {
        $records = [];
        $semester ??= $this->inferSemester($text);

        foreach (preg_split('/\R/u', $text) as $line) {
            $record = $this->parseLine($line, $academicYear, $semester, $sourceFile);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function extractText(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension !== 'pdf') {
            throw new \RuntimeException('团学干部考核导入目前请上传 PDF 文件。');
        }

        $text = $this->extractTextWithPython($file->getRealPath());

        if (trim($text) === '') {
            throw new \RuntimeException('PDF 未能解析出文字，请确认不是扫描图片版 PDF。');
        }

        return $text;
    }

    private function extractTextWithPython(string $path): string
    {
        $code = <<<'PY'
from pypdf import PdfReader
import sys
reader = PdfReader(sys.argv[1])
for page in reader.pages:
    text = page.extract_text() or ""
    sys.stdout.buffer.write(text.encode("utf-8", errors="ignore"))
    sys.stdout.buffer.write(b"\n")
PY;

        $errors = [];

        foreach ($this->pythonCandidates() as $python) {
            try {
                $process = new Process([$python, '-c', $code, $path]);
                $process->setEnv($this->pythonEnvironment($python));
                $process->setTimeout(600);
                $process->run();

                if ($process->isSuccessful()) {
                    return $process->getOutput();
                }

                $errors[] = $python.': '.trim($process->getErrorOutput() ?: $process->getOutput());
            } catch (\Throwable $exception) {
                $errors[] = $python.': '.$exception->getMessage();
            }
        }

        throw new \RuntimeException('无法解析 PDF：未找到可用的 Python+pypdf 环境。'.implode(' | ', array_filter($errors)));
    }

    private function pythonCandidates(): array
    {
        $bundledPython = 'C:\\Users\\Administrator\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe';

        return array_values(array_unique(array_filter([
            is_file($bundledPython) ? $bundledPython : null,
            env('PYTHON_BINARY'),
            'python',
            'python3',
            'py',
        ])));
    }

    private function pythonEnvironment(string $python): array
    {
        $pythonHome = dirname($python);
        $sitePackages = $pythonHome.'\\Lib\\site-packages';

        $environment = ['PYTHONIOENCODING' => 'utf-8'];

        if (is_dir($sitePackages)) {
            $environment['PYTHONPATH'] = $sitePackages;
        }

        return $environment;
    }

    private function parseLine(string $line, string $academicYear, ?string $semester, ?string $sourceFile): ?array
    {
        $line = trim(preg_replace('/\s+/u', ' ', $line));

        if ($line === '' || str_contains($line, '姓名') || str_contains($line, '附件') || str_contains($line, '注：')) {
            return null;
        }

        $tokens = preg_split('/\s+/u', $line);
        if (count($tokens) < 10 || ! in_array(end($tokens), self::GRADES, true)) {
            return null;
        }

        $grade = array_pop($tokens);
        $totalScore = array_pop($tokens);
        $departmentScore = array_pop($tokens);
        $advisorScore = array_pop($tokens);
        $peerScore = array_pop($tokens);
        $selfScore = array_pop($tokens);

        if (! $this->isNumericScore($selfScore) || ! $this->isNumericScore($totalScore) || count($tokens) < 4) {
            return null;
        }

        $name = array_shift($tokens);
        if ($tokens !== [] && mb_strlen($name) === 1 && mb_strlen($tokens[0]) === 1) {
            $name .= array_shift($tokens);
        }

        if (count($tokens) < 3) {
            return null;
        }

        $organization = array_shift($tokens);
        $department = array_shift($tokens);
        $position = implode('', $tokens);

        return [
            'student_name' => $this->normalizeName($name),
            'academic_year' => $academicYear,
            'semester' => $semester,
            'organization' => $organization,
            'department' => $department,
            'position' => $position,
            'self_score' => (float) $selfScore,
            'peer_score' => (float) $peerScore,
            'advisor_score' => (float) $advisorScore,
            'department_score' => (float) $departmentScore,
            'total_score' => (float) $totalScore,
            'grade' => $grade,
            'source_file' => $sourceFile,
        ];
    }

    private function matchStudent(array $record): array
    {
        $name = $this->normalizeName($record['student_name']);
        $students = Student::query()
            ->where('rylx', '0')
            ->where('xm', 'like', mb_substr($name, 0, 1).'%')
            ->get(['xgh', 'xm', 'dwmc', 'bjmc'])
            ->filter(fn (Student $student) => $this->normalizeName((string) $student->xm) === $name)
            ->values();

        if ($students->count() === 1) {
            return ['student' => $students->first(), 'candidates' => []];
        }

        $hinted = $students->filter(fn (Student $student) => $this->studentMatchesHints($student, $record))->values();

        if ($hinted->count() === 1) {
            return ['student' => $hinted->first(), 'candidates' => []];
        }

        $candidates = ($hinted->isNotEmpty() ? $hinted : $students)
            ->take(20)
            ->map(fn (Student $student) => [
                'xgh' => $student->xgh,
                'xm' => $student->xm,
                'dwmc' => $student->dwmc,
                'bjmc' => $student->bjmc,
            ])
            ->values()
            ->all();

        return ['student' => null, 'candidates' => $candidates];
    }

    private function studentMatchesHints(Student $student, array $record): bool
    {
        $haystack = $this->normalizeText(implode('|', array_filter([
            $record['organization'] ?? null,
            $record['department'] ?? null,
        ])));

        foreach ([$student->dwmc, $student->bjmc] as $value) {
            $needle = $this->normalizeText((string) $value);
            if ($needle !== '' && (str_contains($haystack, $needle) || str_contains($needle, $haystack))) {
                return true;
            }
        }

        return false;
    }

    private function storeAssessment(array $record, Student $student): StudentCadreAssessment
    {
        return StudentCadreAssessment::query()->updateOrCreate(
            ['sync_key' => $this->syncKey($record, $student)],
            [
                'student_xgh' => $student->xgh,
                'student_name' => $student->xm ?: $record['student_name'],
                'academic_year' => $record['academic_year'],
                'semester' => $record['semester'] ?? null,
                'organization' => $record['organization'] ?? null,
                'department' => $record['department'] ?? null,
                'position' => $record['position'],
                'self_score' => $record['self_score'] ?? null,
                'peer_score' => $record['peer_score'] ?? null,
                'advisor_score' => $record['advisor_score'] ?? null,
                'department_score' => $record['department_score'] ?? null,
                'total_score' => $record['total_score'] ?? null,
                'grade' => $record['grade'] ?? null,
                'source_file' => $record['source_file'] ?? null,
                'imported_at' => now(),
            ]
        );
    }

    private function syncKey(array $record, Student $student): string
    {
        return sha1(implode('|', [
            $student->xgh,
            $record['academic_year'] ?? '',
            $record['semester'] ?? '',
            $record['organization'] ?? '',
            $record['department'] ?? '',
            $record['position'] ?? '',
        ]));
    }

    private function inferSemester(string $text): ?string
    {
        if (preg_match('/第\s*([一二三四1-4])\s*学期/u', $text, $matches)) {
            return match ($matches[1]) {
                '一', '1' => '1',
                '二', '2' => '2',
                '三', '3' => '3',
                '四', '4' => '4',
                default => null,
            };
        }

        return null;
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/\s+/u', '', trim($value));
    }

    private function normalizeText(string $value): string
    {
        return preg_replace('/\s+/u', '', trim($value));
    }

    private function isNumericScore(mixed $value): bool
    {
        return is_numeric((string) $value);
    }
}
