<script setup>
import { onMounted, ref } from 'vue';
import { formatDatePart, formatTimePart } from '../utils/dateTime';

const props = defineProps({ mode: { type: String, default: 'current' } });
const isGraduates = props.mode === 'graduates';

const studentsData = ref([]);
const paginationInfo = ref({});
const currentPage = ref(1);
const keyword = ref('');
const status = ref('');
const risk = ref('');
const grade = ref('');
const classCode = ref('');
const grades = ref([]);
const classes = ref([]);

const summary = ref({ total: 0, lost_total: 0, lost_today: 0 });
const notice = ref({ text: '', type: 'info' });
const editing = ref(null);
const form = ref({
    xgh: '', xm: '', xbm: '', bjmc: '', bjbm: '', yddh: '', exclude_reason: '', exclude_until: '',
});

function showStatus(text, type = 'info') {
    notice.value = { text, type };
}

function hideStatus() {
    notice.value = { text: '', type: 'info' };
}

function getCSRF() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

async function fetchFilters() {
    const res = await fetch(`/students/filters?grade=${encodeURIComponent(grade.value)}`);
    if (!res.ok) return;
    const data = await res.json();
    grades.value = data.grades || [];
    classes.value = data.classes || [];
}

async function fetchStudents(page = 1) {
    showStatus('正在加载数据...');
    const q = encodeURIComponent(keyword.value);
    if (isGraduates) {
        const res = await fetch(`/graduates/data?page=${page}&q=${q}`);
        if (!res.ok) {
            showStatus('加载失败，请稍后重试。', 'error');
            return;
        }
        const data = await res.json();
        studentsData.value = data.data || [];
        paginationInfo.value = data;
        currentPage.value = data.current_page || page;
        hideStatus();
        return;
    }
    const s = encodeURIComponent(status.value);
    const r = encodeURIComponent(risk.value);
    const g = encodeURIComponent(grade.value);
    const c = encodeURIComponent(classCode.value);

    const res = await fetch(`/students/data?page=${page}&q=${q}&status=${s}&risk=${r}&grade=${g}&class_code=${c}`);
    if (!res.ok) {
        showStatus('加载失败，请稍后重试。', 'error');
        return;
    }

    const data = await res.json();
    studentsData.value = data.data || [];
    paginationInfo.value = data;
    currentPage.value = data.current_page || page;
    summary.value = data.summary || {};
    hideStatus();
}

function openEditModal(student) {
    editing.value = student;
    form.value = {
        xgh: student.xgh || '',
        xm: student.xm || '',
        xbm: student.xbm || '',
        bjmc: student.bjmc || '',
        bjbm: student.bjbm || '',
        yddh: student.yddh || '',
        exclude_reason: student.exclude_reason || '',
        exclude_until: student.exclude_until ? String(student.exclude_until).slice(0, 10) : '',
    };
}

async function saveEdit() {
    const res = await fetch(`/students/data/${form.value.xgh}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRF(),
        },
        body: JSON.stringify(form.value),
    });

    if (!res.ok) {
        showStatus('保存失败，请重试。', 'error');
        return;
    }

    editing.value = null;
    showStatus('保存成功。', 'success');
    await fetchStudents(currentPage.value);
}

function pageRange() {
    const total = paginationInfo.value.last_page || 1;
    const curr = paginationInfo.value.current_page || 1;
    const pages = [];
    for (let i = Math.max(1, curr - 2); i <= Math.min(total, curr + 2); i += 1) pages.push(i);
    return pages;
}

async function resetFilter() {
    keyword.value = '';
    if (isGraduates) {
        await fetchStudents(1);
        return;
    }
    status.value = '';
    risk.value = '';
    grade.value = '';
    classCode.value = '';
    await fetchFilters();
    await fetchStudents(1);
}

onMounted(async () => {
    if (!isGraduates) await fetchFilters();
    await fetchStudents();
});
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-6 rounded-2xl border border-line bg-surface p-6 shadow-sm ui-card">
            <div class="flex flex-wrap items-center justify-between gap-3 mobile-wrap">
                <div>
                    <h1 class="text-3xl font-bold">{{ isGraduates ? '毕业生' : '学生信息管理' }}</h1>
                </div>
                <div class="flex gap-2 mobile-wrap">
                    <a :href="isGraduates ? '/students' : '/graduates'" class="rounded-lg border border-line px-4 py-2 text-sm ui-button">{{ isGraduates ? '在校学生' : '毕业生' }}</a>
                    <a href="/" class="rounded-lg border border-line px-4 py-2 text-sm ui-button">返回首页</a>
                </div>
            </div>
        </header>

        <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm ui-card">
            <div v-if="!isGraduates" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3 mobile-stack">
                <div class="rounded-xl border border-line bg-surface-raised p-3"><p class="text-xs text-muted">学生总数</p><p class="text-xl font-bold">{{ summary.total || 0 }}</p></div>
                <div class="rounded-xl border border-danger-line bg-danger-surface p-3"><p class="text-xs text-danger">当前失联人数</p><p class="text-xl font-bold text-danger">{{ summary.lost_total || 0 }}</p></div>
                <div class="rounded-xl border border-warning-line bg-warning-surface p-3"><p class="text-xs text-warning">今日新增失联</p><p class="text-xl font-bold text-warning">{{ summary.lost_today || 0 }}</p></div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-2 mobile-stack" :class="isGraduates ? 'sm:grid-cols-[minmax(0,1fr)_auto_auto]' : 'sm:grid-cols-6'">
                <input v-model="keyword" type="text" :placeholder="isGraduates ? '按学号、姓名、学院或班级搜索' : '按学号/姓名/班级搜索'" class="rounded-lg border border-line px-3 py-2 text-sm ui-control" @keyup.enter="fetchStudents(1)">
                <select v-if="!isGraduates" v-model="grade" class="rounded-lg border border-line px-3 py-2 text-sm ui-control" @change="fetchFilters(); fetchStudents(1)">
                    <option value="">全部年级</option>
                    <option v-for="g in grades" :key="g.grade_code" :value="g.grade_code">{{ g.grade_code }}级（失联 {{ g.lost_count }} / {{ g.total_count }}）</option>
                </select>
                <select v-if="!isGraduates" v-model="classCode" class="rounded-lg border border-line px-3 py-2 text-sm ui-control" :disabled="!grade" @change="fetchStudents(1)">
                    <option value="">{{ grade ? '全部班级' : '请先选择年级' }}</option>
                    <option v-for="c in classes" :key="c.class_code" :value="c.class_code">{{ c.class_name || c.class_code }}</option>
                </select>
                <select v-if="!isGraduates" v-model="status" class="rounded-lg border border-line px-3 py-2 text-sm ui-control">
                    <option value="">全部状态</option>
                    <option value="normal">正常</option>
                    <option value="lost">失联</option>
                </select>
                <button class="rounded-lg bg-accent px-3 py-2 text-sm font-semibold text-on-accent ui-button ui-button-primary" @click="fetchStudents(1)">查询</button>
                <button class="rounded-lg border border-line px-3 py-2 text-sm ui-button" @click="resetFilter">重置</button>
            </div>

            <div v-if="!isGraduates" class="mb-4 flex gap-2 mobile-wrap">
                <button class="rounded-lg border border-danger-line bg-danger-surface px-3 py-2 text-sm text-danger ui-button" @click="risk = 'high'; fetchStudents(1)">一键只看高风险</button>
                <button class="rounded-lg border border-line bg-surface px-3 py-2 text-sm ui-button" @click="risk = ''; fetchStudents(1)">取消高风险</button>
            </div>

            <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-danger-line bg-danger-surface text-danger' : notice.type === 'success' ? 'border-success-line bg-success-surface text-success' : 'border-info-line bg-info-surface text-info'">{{ notice.text }}</div>

            <div class="overflow-x-auto rounded-xl border border-line">
                <table class="min-w-full text-sm ui-table ui-students-table">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">学号</th>
                            <th class="px-3 py-2 text-left">姓名</th>
                            <th v-if="isGraduates" class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">班级</th>
                            <th class="px-3 py-2 text-left">电话</th>
                            <th v-if="!isGraduates" class="px-3 py-2 text-left">最近刷码</th>
                            <th v-if="!isGraduates" class="px-3 py-2 text-left">状态</th>
                            <th v-if="!isGraduates" class="px-3 py-2 text-left">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="student in studentsData" :key="student.xgh">
                            <td class="px-3 py-2"><a class="text-info hover:underline" :href="`/students/profile/${encodeURIComponent(student.xgh)}`">{{ student.xgh }}</a></td>
                            <td class="px-3 py-2"><a class="text-info hover:underline" :href="`/students/profile/${encodeURIComponent(student.xgh)}`">{{ student.xm }}</a></td>
                            <td v-if="isGraduates" class="px-3 py-2">{{ student.dwmc || '-' }}</td>
                            <td class="px-3 py-2">{{ student.bjmc || '-' }}</td>
                            <td class="px-3 py-2">{{ student.yddh || '-' }}</td>
                            <td v-if="!isGraduates" class="whitespace-nowrap px-3 py-2 tabular-nums">
                                <time v-if="student.last_smsj" :datetime="student.last_smsj">
                                    <span class="block font-medium text-main">{{ formatDatePart(student.last_smsj) }}</span>
                                    <span class="block text-xs text-muted">{{ formatTimePart(student.last_smsj) }}</span>
                                </time>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td v-if="!isGraduates" class="px-3 py-2">{{ student.status === 'lost' ? '失联' : '正常' }}</td>
                            <td v-if="!isGraduates" class="px-3 py-2"><button class="rounded border border-line px-2 py-1 text-xs ui-button" @click="openEditModal(student)">编辑</button></td>
                        </tr>
                        <tr v-if="!studentsData.length"><td :colspan="isGraduates ? 5 : 7" class="px-3 py-6 text-center text-muted">暂无数据</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between mobile-wrap">
                <p class="text-xs text-muted">当前显示 {{ paginationInfo.from || 0 }}-{{ paginationInfo.to || 0 }} 条，共 {{ paginationInfo.total || 0 }} 条</p>
                <div class="flex gap-2 mobile-wrap">
                    <button class="rounded border px-2 py-1 text-sm ui-button" :disabled="(paginationInfo.current_page || 1) <= 1" @click="fetchStudents((paginationInfo.current_page || 1) - 1)">上一页</button>
                    <button v-for="p in pageRange()" :key="p" class="rounded border px-2 py-1 text-sm ui-button" :class="p === (paginationInfo.current_page || 1) ? 'bg-accent text-on-accent' : 'bg-surface'" @click="fetchStudents(p)">{{ p }}</button>
                    <button class="rounded border px-2 py-1 text-sm ui-button" :disabled="(paginationInfo.current_page || 1) >= (paginationInfo.last_page || 1)" @click="fetchStudents((paginationInfo.current_page || 1) + 1)">下一页</button>
                </div>
            </div>
        </section>

        <div v-if="editing && !isGraduates" class="fixed inset-0 z-50 flex items-center justify-center bg-canvas/80 p-4 mobile-modal" @click.self="editing = null">
            <form class="w-full max-w-lg rounded-xl bg-surface p-5 shadow-xl ui-card" @submit.prevent="saveEdit">
                <div class="mb-3 flex items-center justify-between mobile-wrap">
                    <h2 class="text-xl font-bold">编辑学生信息</h2>
                    <button type="button" class="text-sm text-muted ui-button" @click="editing = null">关闭</button>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mobile-stack">
                    <input v-model="form.xm" class="rounded border border-line px-3 py-2 text-sm ui-control" placeholder="姓名">
                    <input v-model="form.xbm" class="rounded border border-line px-3 py-2 text-sm ui-control" placeholder="性别码">
                    <input v-model="form.bjmc" class="rounded border border-line px-3 py-2 text-sm ui-control" placeholder="班级名称">
                    <input v-model="form.bjbm" class="rounded border border-line px-3 py-2 text-sm ui-control" placeholder="班级编码">
                    <input v-model="form.yddh" class="rounded border border-line px-3 py-2 text-sm sm:col-span-2 ui-control" placeholder="电话">
                    <input v-model="form.exclude_reason" class="rounded border border-line px-3 py-2 text-sm sm:col-span-2 ui-control" placeholder="暂不计入统计原因">
                    <input v-model="form.exclude_until" type="date" class="rounded border border-line px-3 py-2 text-sm sm:col-span-2 ui-control">
                </div>
                <div class="mt-4 flex justify-end gap-2 mobile-wrap">
                    <button type="button" class="rounded border border-line px-3 py-2 text-sm ui-button" @click="editing = null">取消</button>
                    <button class="rounded bg-accent px-3 py-2 text-sm font-semibold text-on-accent ui-button ui-button-primary">保存</button>
                </div>
            </form>
        </div>
    </main>
</template>
