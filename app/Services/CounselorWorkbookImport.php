<?php

namespace App\Services;

use App\Models\CounselorClassAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CounselorWorkbookImport
{
    public function preview(string $path, bool $console = false): array
    {
        $management = app(CounselorManagement::class);
        $records = [];
        $errors = [];
        foreach (app(StudentImportWorkbook::class)->read($path) as $sheet => $rows) {
            $header = null;
            foreach ($rows as $offset => $row) {
                $row = array_map(fn ($value) => trim((string) $value), $row);
                if (in_array('工号', $row, true) && in_array('带班情况', $row, true)) {
                    $header = array_flip($row);

                    continue;
                }
                if ($header === null || ! array_filter($row)) {
                    continue;
                }
                $cell = fn ($name) => $row[$header[$name] ?? -1] ?? '';
                $employee = $cell('工号');
                $line = $sheet.' 第'.($offset + 1).'行';
                if ($employee === '' || $cell('姓名') === '') {
                    $errors[] = $line.'：工号、姓名不能为空';

                    continue;
                }
                $code = $management->collegeCode($cell('所属院系'));
                if (! $code) {
                    $errors[] = $line.'：无法确定学院';

                    continue;
                }
                $user = User::where('cas_username', $employee)->first();
                if (! $console && (! $management->allowed($code) || ($user && ! $management->allowed($user->dwbm)))) {
                    $errors[] = $line.'：超出授权学院';

                    continue;
                }
                if ($cell('带班情况') === '') {
                    $errors[] = $line.'：带班情况不能为空，无带班请填 /';

                    continue;
                }
                $names = collect(preg_split('/[、,，;；\r\n]+/u', $cell('带班情况')))->map(fn ($name) => trim($name))->filter(fn ($name) => $name !== '' && $name !== '/')->unique()->values()->all();
                $data = ['cas_username' => $employee, 'name' => $cell('姓名'), 'dwbm' => $code, 'dwmc' => $cell('所属院系'), 'phone' => $cell('手机') ?: null, 'office_phone' => $cell('电话') ?: null, 'office_location' => $cell('办公室') ?: null];
                if (isset($records[$employee]) && $records[$employee]['user'] !== $data) {
                    $errors[] = $line.'：重复工号资料冲突';

                    continue;
                }
                $records[$employee] = ['user' => $data, 'names' => array_unique(array_merge($records[$employee]['names'] ?? [], $names))];
            }
            if ($header === null) {
                $errors[] = $sheet.'：未找到工号、带班情况表头';
            }
        }
        $catalog = app(CounselorClassCatalog::class)->all();
        $result = [];
        foreach ($records as $employee => $record) {
            $assignments = [];
            foreach ($record['names'] as $name) {
                $normalized = CounselorClassAssignment::normalizeClassName($name);
                $match = $catalog->first(fn ($row) => $row['college_code'] === $record['user']['dwbm'] && $normalized === CounselorClassAssignment::normalizeClassName($row['class_name']));
                $assignments[$normalized] = ['class_name' => $match['class_name'] ?? $name, 'class_code' => $match['class_code'] ?? null, 'normalized_class_name' => $normalized, 'college_code' => $record['user']['dwbm'], 'college_name' => $record['user']['dwmc'], 'source' => 'excel'];
            }
            $old = CounselorClassAssignment::where('counselor_cas_username', (string) $employee)->get();
            $result[] = ['user' => $record['user'], 'assignments' => array_values($assignments), 'created_user' => ! User::where('cas_username', (string) $employee)->exists(), 'added' => array_values(array_diff(array_keys($assignments), $old->pluck('normalized_class_name')->all())), 'removed' => array_values(array_diff($old->pluck('normalized_class_name')->all(), array_keys($assignments)))];
        }
        if ($result === [] && $errors === []) {
            $errors[] = '文件没有可导入人员';
        }

        return ['records' => $result, 'errors' => $errors, 'unmatched' => collect($result)->sum(fn ($record) => collect($record['assignments'])->whereNull('class_code')->count())];
    }

    public function commit(string $path, bool $console = false): array
    {
        $result = DB::transaction(function () use ($path, $console) {
            $preview = $this->preview($path, $console);
            if ($preview['errors']) {
                throw ValidationException::withMessages(['file' => $preview['errors']]);
            }
            foreach ($preview['records'] as $record) {
                $data = $record['user'];
                $user = User::where('cas_username', $data['cas_username'])->lockForUpdate()->first();
                $before = $user ? ['user' => $user->only(array_keys($data)), 'assignments' => $user->classAssignments()->get()->toArray()] : null;
                if (! $user) {
                    $user = new User(['email' => $data['cas_username'].'@counselor.local', 'password' => Str::random(40), 'role' => User::ROLE_COUNSELOR]);
                }
                $user->fill($data)->save();
                $user->classAssignments()->whereNotIn('normalized_class_name', array_column($record['assignments'], 'normalized_class_name'))->delete();
                foreach ($record['assignments'] as $assignment) {
                    CounselorClassAssignment::updateOrCreate(['counselor_cas_username' => $user->cas_username, 'normalized_class_name' => $assignment['normalized_class_name']], $assignment + ['user_id' => $user->id]);
                }
                app(CounselorManagement::class)->log('import', $user->cas_username, $before, ['user' => $data, 'assignments' => $record['assignments']]);
            }

            return $preview;
        });

        app(CounselorClassCatalog::class)->forget();

        return $result;
    }
}
