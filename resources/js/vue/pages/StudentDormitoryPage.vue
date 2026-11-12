<script setup>
import { formatDatePart, formatTimePart } from '../utils/dateTime';

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
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-6 rounded-lg border border-line bg-surface p-6 shadow-sm ui-card">
            <div class="flex flex-wrap items-center justify-between gap-3 mobile-wrap">
                <div>
                    <p class="text-sm text-muted">宿舍详情</p>
                    <h1 class="mt-1 text-2xl font-bold text-main">{{ props.ssh }}</h1>
                </div>
                <a href="/students" class="rounded-lg border border-line px-4 py-2 text-sm text-muted hover:bg-surface-raised ui-button">返回学生管理</a>
            </div>
        </header>

        <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 mobile-stack">
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm ui-card">
                <p class="text-xs text-muted">住宿人数</p>
                <p class="mt-2 text-2xl font-bold text-main">{{ props.dormitorySummary.resident_total || 0 }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm ui-card">
                <p class="text-xs text-muted">失联人数</p>
                <p class="mt-2 text-2xl font-bold text-danger">{{ props.dormitorySummary.lost_roommate_count || 0 }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm ui-card">
                <p class="text-xs text-muted">高风险人数</p>
                <p class="mt-2 text-2xl font-bold text-warning">{{ props.dormitorySummary.high_risk_roommate_count || 0 }}</p>
            </div>
            <div class="rounded-xl border border-line bg-surface p-4 shadow-sm ui-card">
                <p class="text-xs text-muted">寝室类型</p>
                <p class="mt-2 text-lg font-semibold text-main">{{ props.residents[0]?.qslx || '-' }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
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
                    <tbody class="divide-y divide-line">
                        <tr v-for="resident in props.residents" :key="resident.xh">
                            <td class="px-3 py-2"><a class="text-info hover:underline" :href="`/students/profile/${encodeURIComponent(resident.xh)}`">{{ resident.xh }}</a></td>
                            <td class="px-3 py-2"><a class="text-info hover:underline" :href="`/students/profile/${encodeURIComponent(resident.xh)}`">{{ resident.xm || '-' }}</a></td>
                            <td class="px-3 py-2">{{ resident.xy || '-' }}</td>
                            <td class="px-3 py-2">{{ resident.bj || '-' }}</td>
                            <td class="px-3 py-2">{{ resident.ch || '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums">
                                <time v-if="resident.last_smsj" :datetime="resident.last_smsj">
                                    <span class="block font-medium text-main">{{ formatDatePart(resident.last_smsj) }}</span>
                                    <span class="block text-xs text-muted">{{ formatTimePart(resident.last_smsj) }}</span>
                                </time>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="resident.status === 'lost' ? 'bg-danger-surface text-danger' : 'bg-success-surface text-success'">
                                    {{ statusText(resident.status) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!props.residents.length">
                            <td colspan="7" class="px-3 py-6 text-center text-muted">暂无住宿学生</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
