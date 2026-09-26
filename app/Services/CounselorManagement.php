<?php

namespace App\Services;

use App\Models\CounselorManagementPermission;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\DB;

class CounselorManagement
{
    public function admin(): bool
    {
        return ! config('cas.enabled') || (bool) CurrentUser::get()?->isAdmin();
    }

    public function permission(): ?CounselorManagementPermission
    {
        $employee = CurrentUser::get()?->cas_username;

        return $employee ? CounselorManagementPermission::where('employee_no', $employee)->where('is_active', true)->first() : null;
    }

    public function allowed(?string $code): bool
    {
        if ($this->admin()) {
            return true;
        }
        $permission = $this->permission();

        return $permission && ($permission->all_colleges || (filled($code) && in_array($code, $permission->department_codes, true)));
    }

    public function authorize(?string $code): void
    {
        abort_unless($this->allowed($code), 403, '无权维护该学院的带班信息。');
    }

    public function colleges(): array
    {
        return DB::table('students')->where('student_category', 'current')->whereNotNull('dwbm')->where('dwbm', '!=', '')
            ->selectRaw('dwbm as code, MAX(dwmc) as name')->groupBy('dwbm')->orderBy('dwbm')->get()->map(fn ($row) => (array) $row)->all();
    }

    public function collegeCode(string $name): ?string
    {
        $matches = collect($this->colleges())->filter(fn ($college) => $college['name'] === $name || in_array($name, explode('、', $college['name']), true));

        return $matches->count() === 1 ? $matches->first()['code'] : null;
    }

    public function log(string $action, string $subject, mixed $before, mixed $after): void
    {
        DB::table('counselor_management_logs')->insert([
            'actor' => CurrentUser::get()?->cas_username ?? 'console', 'action' => $action, 'subject' => $subject,
            'before' => json_encode($before, JSON_UNESCAPED_UNICODE), 'after' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
