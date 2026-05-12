<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生主页</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-slate-500">学生主页</p>
                <h1 class="mt-1 text-2xl font-bold">{{ $student->xm }}（{{ $student->xgh }}）</h1>
                <p class="mt-1 text-sm text-slate-500">班级：{{ $student->bjmc ?: '-' }} / 联系电话：{{ $student->yddh ?: '-' }}</p>
            </div>
            <div class="flex gap-2">
                <a href="/students" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">返回学生管理</a>
                <a href="/student-families?q={{ urlencode($student->xm ?? '') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">打开家庭信息页</a>
            </div>
        </div>
    </header>

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-lg font-semibold">学生基础信息</h2>
        <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><span class="text-slate-500">姓名：</span>{{ $student->xm ?: '-' }}</div>
            <div><span class="text-slate-500">学号：</span>{{ $student->xgh ?: '-' }}</div>
            <div><span class="text-slate-500">性别码：</span>{{ $student->xbm ?: '-' }}</div>
            <div><span class="text-slate-500">班级编码：</span>{{ $student->bjbm ?: '-' }}</div>
            <div><span class="text-slate-500">班级名称：</span>{{ $student->bjmc ?: '-' }}</div>
            <div><span class="text-slate-500">联系电话：</span>{{ $student->yddh ?: '-' }}</div>
            <div><span class="text-slate-500">最近刷码：</span>{{ $student->last_smsj ?: '-' }}</div>
            <div><span class="text-slate-500">状态：</span>{{ $student->status ?: '-' }}</div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold">家庭信息</h2>
            <span class="text-xs text-slate-500">共 {{ $families->count() }} 条</span>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left">姓名</th>
                        <th class="px-3 py-2 text-left">关系</th>
                        <th class="px-3 py-2 text-left">具体关系</th>
                        <th class="px-3 py-2 text-left">工作单位</th>
                        <th class="px-3 py-2 text-left">职位</th>
                        <th class="px-3 py-2 text-left">手机</th>
                        <th class="px-3 py-2 text-left">紧急联系人</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($families as $family)
                        <tr>
                            <td class="px-3 py-2">{{ $family->name ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $family->relationship ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $family->specific_relationship ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $family->work_unit ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $family->position ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $family->phone ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $family->is_emergency_contact ? '是' : '否' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-slate-500">暂无家庭信息</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>

