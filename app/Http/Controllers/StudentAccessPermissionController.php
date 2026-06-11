<?php

namespace App\Http\Controllers;

use App\Models\StudentAccessPermission;
use App\Services\StudentImportWorkbook;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class StudentAccessPermissionController extends Controller
{
    public function page()
    {
        $this->authorizeManager();

        return view('student-access-permissions');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManager();

        $keyword = trim((string) $request->query('q', ''));
        $permissions = StudentAccessPermission::query()
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('employee_no', 'like', "%{$keyword}%")
                        ->orWhere('teacher_name', 'like', "%{$keyword}%")
                        ->orWhere('unit_name', 'like', "%{$keyword}%")
                        ->orWhere('scope_name', 'like', "%{$keyword}%")
                        ->orWhere('department_code', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('scope_type')
            ->orderBy('department_code')
            ->orderBy('employee_no')
            ->paginate((int) $request->query('per_page', 100));

        return response()->json([
            'data' => $permissions->items(),
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManager();

        $validated = $this->validated($request);
        $permission = StudentAccessPermission::query()->create($this->payload($validated));

        return response()->json(['data' => $permission], 201);
    }

    public function update(Request $request, StudentAccessPermission $permission): JsonResponse
    {
        $this->authorizeManager();

        $validated = $this->validated($request, $permission);
        $permission->fill($this->payload($validated))->save();

        return response()->json(['data' => $permission->refresh()]);
    }

    public function destroy(StudentAccessPermission $permission): JsonResponse
    {
        $this->authorizeManager();

        $permission->delete();

        return response()->json(['message' => '权限记录已删除']);
    }

    public function template(StudentImportWorkbook $workbook)
    {
        $this->authorizeManager();

        $path = storage_path('app/student-access-permissions-template.xlsx');
        $workbook->write($path, [
            '权限清单' => [
                ['', '', '', '', '', ''],
                ['序号', '单位', '工号', '姓名', '权限', '分院代码'],
                ['1', '金融与经贸学院', '20060017', '郭小蕾', '金融与经贸学院', '100301'],
                ['2', '学工', '20250065', '胡叶帅', '最高', '1003'],
            ],
        ]);

        return Response::download($path, '学生权限清单导入模板.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request, StudentImportWorkbook $workbook): JsonResponse
    {
        $this->authorizeManager();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:20480'],
        ]);

        $sheets = $workbook->read($request->file('file')->getRealPath());
        $rows = collect($sheets)->first() ?? [];
        $headerIndex = $this->headerIndex($rows);
        abort_if($headerIndex === null, 422, '未找到表头，请确认包含：序号、单位、工号、姓名、权限、分院代码');

        $indexes = $this->indexes($rows[$headerIndex]);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        DB::transaction(function () use ($rows, $headerIndex, $indexes, &$result): void {
            foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
                $line = $headerIndex + $offset + 2;
                $employeeNo = $this->cell($row, $indexes['employee_no'] ?? null);

                if ($employeeNo === '') {
                    $result['skipped']++;
                    continue;
                }

                $data = [
                    'employee_no' => $employeeNo,
                    'teacher_name' => $this->nullableString($this->cell($row, $indexes['teacher_name'] ?? null)),
                    'unit_name' => $this->nullableString($this->cell($row, $indexes['unit_name'] ?? null)),
                    'scope_name' => $this->nullableString($this->cell($row, $indexes['scope_name'] ?? null)),
                    'department_code' => $this->nullableString($this->cell($row, $indexes['department_code'] ?? null)),
                    'is_active' => true,
                ];
                $data['scope_type'] = StudentAccessPermission::normalizeScopeType($data['scope_name'], $data['department_code']);
                $data['imported_at'] = now();

                if ($data['scope_type'] === StudentAccessPermission::SCOPE_COLLEGE && blank($data['department_code'])) {
                    $result['errors'][] = "第 {$line} 行：分院权限必须填写分院代码";
                    continue;
                }

                $permission = StudentAccessPermission::query()->where('employee_no', $employeeNo)->first();
                if ($permission) {
                    $permission->fill($data)->save();
                    $result['updated']++;
                } else {
                    StudentAccessPermission::query()->create($data);
                    $result['created']++;
                }
            }
        });

        return response()->json($result);
    }

    private function authorizeManager(): void
    {
        abort_unless(! config('cas.enabled') || (bool) CurrentUser::get()?->isAdmin(), 403);
    }

    private function validated(Request $request, ?StudentAccessPermission $permission = null): array
    {
        return $request->validate([
            'employee_no' => ['required', 'string', 'max:255', Rule::unique('student_access_permissions', 'employee_no')->ignore($permission?->id)],
            'teacher_name' => ['nullable', 'string', 'max:255'],
            'unit_name' => ['nullable', 'string', 'max:255'],
            'scope_name' => ['nullable', 'string', 'max:255'],
            'department_code' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    private function payload(array $validated): array
    {
        $payload = [
            'employee_no' => trim((string) $validated['employee_no']),
            'teacher_name' => $this->nullableString($validated['teacher_name'] ?? ''),
            'unit_name' => $this->nullableString($validated['unit_name'] ?? ''),
            'scope_name' => $this->nullableString($validated['scope_name'] ?? ''),
            'department_code' => $this->nullableString($validated['department_code'] ?? ''),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
        $payload['scope_type'] = StudentAccessPermission::normalizeScopeType($payload['scope_name'], $payload['department_code']);

        return $payload;
    }

    private function headerIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $joined = implode('|', $row);
            if (str_contains($joined, '工号') && str_contains($joined, '权限') && str_contains($joined, '分院代码')) {
                return $index;
            }
        }

        return null;
    }

    private function indexes(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            match (trim((string) $header)) {
                '单位' => $map['unit_name'] = $index,
                '工号' => $map['employee_no'] = $index,
                '姓名' => $map['teacher_name'] = $index,
                '权限' => $map['scope_name'] = $index,
                '分院代码' => $map['department_code'] = $index,
                default => null,
            };
        }

        return $map;
    }

    private function cell(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
