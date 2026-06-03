<script setup>
import { computed, onMounted, ref } from 'vue';

const groups = ref([]);
const selected = ref(null);
const detail = ref(null);
const classOptions = ref([]);
const loading = ref(false);
const saving = ref(false);
const notice = ref({ text: '', type: 'info' });
const selectedClassCode = ref('');
const form = ref(emptyForm());

const totalCounselors = computed(() => groups.value.reduce((sum, group) => sum + Number(group.count || 0), 0));

function emptyForm() {
    return {
        cas_username: '',
        name: '',
        dwmc: '',
        dwbm: '',
        phone: '',
        office_phone: '',
        office_location: '',
    };
}

function getCSRF() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function showNotice(text, type = 'info') {
    notice.value = { text, type };
}

async function fetchGroups() {
    const response = await fetch('/counselors/data', { headers: { Accept: 'application/json' } });
    const payload = await response.json();
    groups.value = payload.data || [];

    if (!selected.value && groups.value[0]?.counselors?.[0]) {
        await openDetail(groups.value[0].counselors[0]);
    }
}

async function openDetail(counselor) {
    selected.value = counselor;
    loading.value = true;
    const response = await fetch(`/counselors/${counselor.id}`, { headers: { Accept: 'application/json' } });
    loading.value = false;

    if (!response.ok) {
        showNotice('读取辅导员详情失败。', 'error');
        return;
    }

    detail.value = (await response.json()).data;
    form.value = {
        cas_username: detail.value.cas_username || '',
        name: detail.value.name || '',
        dwmc: detail.value.dwmc || '',
        dwbm: detail.value.dwbm || '',
        phone: detail.value.phone || '',
        office_phone: detail.value.office_phone || '',
        office_location: detail.value.office_location || '',
    };
    selectedClassCode.value = '';
    await searchClasses();
}

async function saveCounselor() {
    if (saving.value) return;
    saving.value = true;
    const isEdit = Boolean(detail.value?.id);
    const response = await fetch(isEdit ? `/counselors/${detail.value.id}` : '/counselors/data', {
        method: isEdit ? 'PUT' : 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRF(),
            Accept: 'application/json',
        },
        body: JSON.stringify(form.value),
    });
    saving.value = false;

    if (!response.ok) {
        showNotice('保存失败，请确认工号没有重复。', 'error');
        return;
    }

    showNotice('辅导员信息已保存。', 'success');
    await fetchGroups();
    const payload = await response.json();
    await openDetail(payload.data);
}

function newCounselor() {
    selected.value = null;
    detail.value = null;
    form.value = emptyForm();
    classOptions.value = [];
    selectedClassCode.value = '';
}

async function deleteCounselor() {
    if (!detail.value || !confirm(`确认删除辅导员 ${detail.value.name}？`)) return;

    const response = await fetch(`/counselors/${detail.value.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': getCSRF(), Accept: 'application/json' },
    });

    if (!response.ok) {
        showNotice('删除失败。', 'error');
        return;
    }

    showNotice('辅导员已删除。', 'success');
    detail.value = null;
    selected.value = null;
    await fetchGroups();
}

async function searchClasses() {
    const counselorId = detail.value?.id ? `&counselor_id=${encodeURIComponent(detail.value.id)}` : '';
    const response = await fetch(`/counselors/classes?q=${counselorId}`, { headers: { Accept: 'application/json' } });
    classOptions.value = (await response.json()).data || [];
}

async function addClass() {
    if (!detail.value) return;
    const picked = classOptions.value.find((item) => item.class_code === selectedClassCode.value);
    const className = picked?.class_name || '';

    if (!className) {
        showNotice('请先选择班级。', 'error');
        return;
    }

    const response = await fetch(`/counselors/${detail.value.id}/classes`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRF(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ class_code: picked?.class_code || '', class_name: className }),
    });

    if (!response.ok) {
        showNotice('添加班级失败。', 'error');
        return;
    }

    showNotice('带班关系已添加。', 'success');
    selectedClassCode.value = '';
    classOptions.value = [];
    await openDetail(detail.value);
    await fetchGroups();
}

async function removeClass(assignment) {
    if (!detail.value || !confirm(`确认移除 ${assignment.class_name}？`)) return;

    const response = await fetch(`/counselors/${detail.value.id}/classes/${assignment.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': getCSRF(), Accept: 'application/json' },
    });

    if (!response.ok) {
        showNotice('移除失败。', 'error');
        return;
    }

    showNotice('带班关系已移除。', 'success');
    await openDetail(detail.value);
    await fetchGroups();
}

onMounted(fetchGroups);
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-500">统一认证 / 辅导员权限</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">辅导员带班管理</h1>
                <p class="mt-1 text-sm text-slate-500">按分院查看辅导员与所带班级，辅导员登录后仅能查看自己管理班级的学生。</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="newCounselor">新增辅导员</button>
                <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回首页</a>
            </div>
        </header>

        <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700'">
            {{ notice.text }}
        </div>

        <section class="grid gap-6 lg:grid-cols-[420px_1fr]">
            <aside class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">辅导员人数</p>
                    <p class="mt-1 text-3xl font-bold text-slate-950">{{ totalCounselors }}</p>
                </div>

                <div v-for="group in groups" :key="group.college" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between bg-slate-900 px-4 py-3 text-white">
                        <h2 class="text-sm font-semibold">{{ group.college }}</h2>
                        <span class="text-xs">辅导员人数：{{ group.count }}</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <button
                            v-for="counselor in group.counselors"
                            :key="counselor.id"
                            type="button"
                            class="grid w-full grid-cols-[1fr_120px_64px] items-center gap-3 px-4 py-3 text-left text-sm hover:bg-slate-50"
                            :class="detail?.id === counselor.id ? 'bg-sky-50' : ''"
                            @click="openDetail(counselor)"
                        >
                            <span class="font-semibold text-slate-950">{{ counselor.name }}</span>
                            <span class="text-slate-600">{{ counselor.phone || '-' }}</span>
                            <span class="text-slate-500">{{ counselor.class_assignments_count || 0 }} 班</span>
                        </button>
                    </div>
                </div>
            </aside>

            <section class="space-y-5">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-950">{{ detail ? '辅导员详情' : '新增辅导员' }}</h2>
                        <button v-if="detail" type="button" class="rounded border border-rose-200 px-3 py-1.5 text-sm text-rose-700 hover:bg-rose-50" @click="deleteCounselor">删除</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm text-slate-600">工号 / CAS 用户名<input v-model="form.cas_username" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="text-sm text-slate-600">姓名<input v-model="form.name" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="text-sm text-slate-600">所属分院<input v-model="form.dwmc" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="text-sm text-slate-600">分院编码<input v-model="form.dwbm" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="text-sm text-slate-600">手机<input v-model="form.phone" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="text-sm text-slate-600">办公电话<input v-model="form.office_phone" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="text-sm text-slate-600 sm:col-span-2">办公室<input v-model="form.office_location" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="saving" @click="saveCounselor">{{ saving ? '保存中...' : '保存辅导员' }}</button>
                    </div>
                </div>

                <div v-if="detail" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">带班信息</h2>
                            <p class="mt-1 text-sm text-slate-500">共 {{ detail.assignments?.length || 0 }} 个班级</p>
                        </div>
                    </div>

                    <div class="mb-5 grid gap-3 sm:grid-cols-[1fr_auto]">
                        <select v-model="selectedClassCode" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            <option value="">请选择班级</option>
                            <option v-for="item in classOptions" :key="item.class_code" :value="item.class_code">{{ item.class_name }}（{{ item.student_count || 0 }}人）</option>
                        </select>
                        <button type="button" class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="addClass">添加班级</button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="assignment in detail.assignments" :key="assignment.id" class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <span class="text-sm font-semibold text-slate-900">{{ assignment.class_name }}</span>
                            <button type="button" class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50" @click="removeClass(assignment)">移除</button>
                        </div>
                        <div v-if="!detail.assignments?.length" class="rounded-lg border border-dashed border-slate-300 px-3 py-6 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">暂无带班信息</div>
                    </div>
                </div>

                <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm">正在读取详情...</div>
            </section>
        </section>
    </main>
</template>
