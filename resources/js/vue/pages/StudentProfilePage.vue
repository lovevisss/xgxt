<script setup>
const props = defineProps({
    student: {
        type: Object,
        required: true,
    },
    families: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">学生主页</p>
                    <h1 class="mt-1 text-2xl font-bold">{{ props.student.xm }}（{{ props.student.xgh }}）</h1>
                    <p class="mt-1 text-sm text-slate-500">班级：{{ props.student.bjmc || '-' }} / 联系电话：{{ props.student.yddh || '-' }}</p>
                </div>
                <a href="/students" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">返回学生管理</a>
            </div>
        </header>

        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold">基础信息</h2>
            <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><span class="text-slate-500">学号：</span>{{ props.student.xgh || '-' }}</div>
                <div><span class="text-slate-500">姓名：</span>{{ props.student.xm || '-' }}</div>
                <div><span class="text-slate-500">班级：</span>{{ props.student.bjmc || '-' }}</div>
                <div><span class="text-slate-500">联系电话：</span>{{ props.student.yddh || '-' }}</div>
                <div><span class="text-slate-500">最近刷码：</span>{{ props.student.last_smsj || '-' }}</div>
                <div><span class="text-slate-500">状态：</span>{{ props.student.status || '-' }}</div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold">家庭信息</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">姓名</th>
                            <th class="px-3 py-2 text-left">关系</th>
                            <th class="px-3 py-2 text-left">单位</th>
                            <th class="px-3 py-2 text-left">职位</th>
                            <th class="px-3 py-2 text-left">手机</th>
                            <th class="px-3 py-2 text-left">紧急联系人</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="family in props.families" :key="family.id">
                            <td class="px-3 py-2">{{ family.name || '-' }}</td>
                            <td class="px-3 py-2">{{ family.relationship || '-' }}</td>
                            <td class="px-3 py-2">{{ family.work_unit || '-' }}</td>
                            <td class="px-3 py-2">{{ family.position || '-' }}</td>
                            <td class="px-3 py-2">{{ family.phone || '-' }}</td>
                            <td class="px-3 py-2">{{ family.is_emergency_contact ? '是' : '否' }}</td>
                        </tr>
                        <tr v-if="!props.families.length">
                            <td colspan="6" class="px-3 py-6 text-center text-slate-500">暂无家庭信息</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>

