<script setup>
const props = defineProps({
    ssh: { type: String, required: true },
    residents: { type: Array, default: () => [] },
    dormitorySummary: { type: Object, default: () => ({}) },
});

function statusText(status) {
    return status === 'lost' ? '失联' : '正常';
}
</script>

<template>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">宿舍详情</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ props.ssh }}</h1>
                </div>
                <a href="/students" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回学生管理</a>
            </div>
        </header>

        <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">住宿人数</p>
                <p class="mt-2 text-2xl font-bold text-slate-950">{{ props.dormitorySummary.resident_total || 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">失联人数</p>
                <p class="mt-2 text-2xl font-bold text-rose-700">{{ props.dormitorySummary.lost_roommate_count || 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">高风险人数</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ props.dormitorySummary.high_risk_roommate_count || 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">寝室类型</p>
                <p class="mt-2 text-lg font-semibold text-slate-950">{{ props.residents[0]?.qslx || '-' }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">学号</th>
                            <th class="px-3 py-2 text-left">姓名</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">班级</th>
                            <th class="px-3 py-2 text-left">床位</th>
                            <th class="px-3 py-2 text-left">最近刷码</th>
                            <th class="px-3 py-2 text-left">状态</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="resident in props.residents" :key="resident.xh">
                            <td class="px-3 py-2"><a class="text-sky-700 hover:underline" :href="`/students/profile/${encodeURIComponent(resident.xh)}`">{{ resident.xh }}</a></td>
                            <td class="px-3 py-2"><a class="text-sky-700 hover:underline" :href="`/students/profile/${encodeURIComponent(resident.xh)}`">{{ resident.xm || '-' }}</a></td>
                            <td class="px-3 py-2">{{ resident.xy || '-' }}</td>
                            <td class="px-3 py-2">{{ resident.bj || '-' }}</td>
                            <td class="px-3 py-2">{{ resident.ch || '-' }}</td>
                            <td class="px-3 py-2">{{ resident.last_smsj || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="resident.status === 'lost' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'">
                                    {{ statusText(resident.status) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!props.residents.length">
                            <td colspan="7" class="px-3 py-6 text-center text-slate-500">暂无住宿学生</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>

