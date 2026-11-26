<?php

namespace App\Services;

use App\Support\CurrentUser;

class WorkspaceContext
{
    public function build(): array
    {
        $access = app(StudentAccess::class);
        $user = CurrentUser::get();
        $capabilities = $access->capabilities();
        $scope = $access->scope();
        $labels = ['super_admin' => '总管理员', 'admin' => '管理员', 'student_affairs' => '学工老师', 'counselor' => '辅导员', 'staff' => '普通人员'];
        $names = empty($scope['departments']) ? collect() : \App\Models\Student::query()->whereIn('dwbm', $scope['departments'])->select('dwbm', 'dwmc')->distinct()->get()->groupBy('dwbm');
        $scopeLabel = $scope['all'] ? '全校学生' : collect($scope['departments'])->map(fn ($code) => $names->get($code)?->first()?->dwmc ?: ($user && (string) $user->dwbm === $code ? ($user->dwmc ?: $code) : $code))->implode('、');
        if (! $scope['all'] && count($scope['classes'] ?? []) > 0) {
            $scopeLabel = trim($scopeLabel.'、代管 '.count($scope['classes']).' 班', '、');
        }
        $groups = [];
        $students = [];
        if ($capabilities['students']) {
            $students = [
                $this->entry('/students', '学生管理', '查看在校学生与监测状态', 'students'),
                $this->entry('/graduates', '毕业生', '查询毕业生档案', 'graduates'),
                $this->entry('/student-families', '家庭联系', '查看学生家庭联系人', 'families'),
            ];
            $groups[] = ['key' => 'students', 'label' => '学生业务', 'entries' => $students];
        }
        $management = [];
        if ($capabilities['businessAdmin']) {
            $management[] = $this->entry('/student-imports', '数据导入', '导入学生相关业务资料', 'import');
            $management[] = $this->entry('/sync-tasks', '同步中心', '管理数据同步任务', 'sync');
            $management[] = $this->entry('/student-access-permissions', '学生权限清单', '配置人员额外查看范围', 'permissions');
            $management[] = $this->entry('/snippets', 'Snippets', '管理已有内容记录', 'snippets');
        }
        if ($capabilities['counselors']) $management[] = $this->entry('/counselors', '辅导员带班', '查看或维护授权带班关系', 'counselors');
        if ($management) $groups[] = ['key' => 'management', 'label' => '业务管理', 'entries' => $management];
        if ($capabilities['systemAdmin']) {
            $groups[] = ['key' => 'system', 'label' => '系统管理', 'entries' => [
                $this->entry('/admin/users', '人员与权限', '分配角色及管理授权', 'users'),
                $this->entry('/admin/login-logs', '登录日志', '查看账号登录记录', 'logs'),
            ]];
        }
        $navigation = [$this->entry('/', '首页', '', 'home')];
        if ($capabilities['students']) {
            $navigation[] = $this->entry('/students', '学生', '', 'students');
            $navigation[] = $this->entry('/student-families', '家庭', '', 'families');
        }
        if ($management || $capabilities['systemAdmin']) $navigation[] = $this->entry('/#management', '管理', '', 'management');

        return [
            'authenticated' => (bool) $user || ! config('cas.enabled'),
            'user' => ['name' => $user?->name ?? (! config('cas.enabled') ? '本地开发' : '访客'), 'role' => $user?->role, 'roleLabel' => $labels[$user?->role] ?? (! config('cas.enabled') ? '开发模式' : '未登录')],
            'status' => $user?->isInactiveStaffMember() ? '账号已停用，请联系管理员。' : ($groups ? '' : '暂无业务访问权限，请联系管理员配置角色或学生范围。'),
            'scopeLabel' => $scopeLabel ?: ($user?->isCounselor() && blank($user->dwbm) ? '未配置所属分院，请联系管理员。' : '暂无学生查看范围'),
            'capabilities' => $capabilities, 'groups' => $groups, 'navigation' => $navigation,
        ];
    }

    private function entry(string $href, string $label, string $description, string $key): array
    {
        return compact('href', 'label', 'description', 'key');
    }
}
