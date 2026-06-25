<script setup>
import { computed, onMounted, ref } from 'vue';

const permissions = ref([]);
const meta = ref({ total: 0 });
const q = ref('');
const file = ref(null);
const editing = ref(null);
const saving = ref(false);
const importing = ref(false);
const notice = ref({ text: '', type: 'info' });
const result = ref(null);
const form = ref(emptyForm());

const activeCount = computed(() => permissions.value.filter((item) => item.is_active).length);
const allScopeCount = computed(() => permissions.value.filter((item) => item.scope_type === 'all').length);

function emptyForm() {
    return {
        employee_no: '',
        teacher_name: '',
        unit_name: '',
        scope_name: '',
        department_code: '',
        is_active: true,
    };
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function showNotice(text, type = 'info') {
    notice.value = { text, type };
}

async function fetchPermissions() {
    const response = await fetch(`/student-access-permissions/data?q=${encodeURIComponent(q.value)}`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        showNotice('权限清单加载失败。', 'error');
        return;
    }

    const payload = await response.json();
    permissions.value = payload.data || [];
    meta.value = payload.meta || { total: permissions.value.length };
}

function edit(permission) {
    editing.value = permission;
    form.value = {
        employee_no: permission.employee_no || '',
        teacher_name: permission.teacher_name || '',
        unit_name: permission.unit_name || '',
        scope_name: permission.scope_name || '',
        department_code: permission.department_code || '',
        is_active: Boolean(permission.is_active),
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    editing.value = null;
    form.value = emptyForm();
}

async function savePermission() {
    if (saving.value) return;
    saving.value = true;
    const url = editing.value ? `/student-access-permissions/${editing.value.id}` : '/student-access-permissions/data';
    const response = await fetch(url, {
        method: editing.value ? 'PUT' : 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            Accept: 'application/json',
        },
        body: JSON.stringify(form.value),
    });
    saving.value = false;

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        showNotice(payload.message || '保存失败，请检查工号是否重复。', 'error');
        return;
    }

    showNotice('权限记录已保存。', 'success');
    resetForm();
    await fetchPermissions();
}

async function removePermission(permission) {
    if (!confirm(`确认删除 ${permission.teacher_name || permission.employee_no} 的学生权限？`)) return;

    const response = await fetch(`/student-access-permissions/${permission.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    });

    if (!response.ok) {
        showNotice('删除失败。', 'error');
        return;
    }

    showNotice('权限记录已删除。', 'success');
    await fetchPermissions();
}

async function importExcel() {
    if (!file.value) {
        showNotice('请先选择 Excel 文件。', 'error');
        return;
    }

    importing.value = true;
    const body = new FormData();
    body.append('file', file.value);
    const response = await fetch('/student-access-permissions/import', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body,
    });
    const payload = await response.json().catch(() => ({}));
    importing.value = false;

    if (!response.ok) {
        showNotice(payload.message || '导入失败。', 'error');
        return;
    }

    result.value = payload;
    showNotice('导入完成。', 'success');
    file.value = null;
    await fetchPermissions();
}

onMounted(fetchPermissions);
</script>

<template>
    <main class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-5 rounded-lg border border-slate-200 bg-white px-6 py-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-slate-500">学生权限 / 分院与全院范围</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">学生权限清单</h1>
                    <p class="mt-1 text-sm text-slate-500">按教师工号统一管理学生只读查看范围。</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="/student-access-permissions/template" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">下载模板</a>
                    <a href="/" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回首页</a>
                </div>
            </div>
        </header>

        <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700'">
            {{ notice.text }}
        </div>

        <section class="grid gap-5 xl:grid-cols-[330px_minmax(0,1fr)]">
            <aside class="space-y-4 xl:sticky xl:top-4 xl:self-start">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">{{ editing ? '编辑权限' : '新增权限' }}</h2>
                        <span v-if="editing" class="rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">正在编辑</span>
                    </div>

                    <div class="space-y-3">
                        <label class="block text-sm text-slate-600">工号<input v-model="form.employee_no" class="mt-1 h-9 w-full rounded border border-slate-300 px-3 text-sm text-slate-950"></label>
                        <label class="block text-sm text-slate-600">姓名<input v-model="form.teacher_name" class="mt-1 h-9 w-full rounded border border-slate-300 px-3 text-sm text-slate-950"></label>
                        <label class="block text-sm text-slate-600">单位<input v-model="form.unit_name" class="mt-1 h-9 w-full rounded border border-slate-300 px-3 text-sm text-slate-950"></label>
                        <label class="block text-sm text-slate-600">权限<input v-model="form.scope_name" class="mt-1 h-9 w-full rounded border border-slate-300 px-3 text-sm text-slate-950" placeholder="金融与经贸学院 / 最高"></label>
                        <label class="block text-sm text-slate-600">分院代码<input v-model="form.department_code" class="mt-1 h-9 w-full rounded border border-slate-300 px-3 text-sm text-slate-950" placeholder="100301 / 1003"></label>
                        <label class="flex items-center gap-2 text-sm text-slate-600"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">启用</label>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="button" class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="saving" @click="savePermission">{{ saving ? '保存中...' : '保存' }}</button>
                        <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="resetForm">清空</button>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">Excel 导入</h2>
                    <p class="mt-1 text-sm leading-5 text-slate-500">第 2 行表头：序号、单位、工号、姓名、权限、分院代码。</p>
                    <label class="mt-3 block rounded border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-sm text-slate-600">
                        <input class="block w-full text-sm" type="file" accept=".xls,.xlsx" @change="file = $event.target.files?.[0] || null">
                    </label>
                    <button type="button" class="mt-3 rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="importing" @click="importExcel">{{ importing ? '导入中...' : '开始导入' }}</button>
                    <div v-if="result" class="mt-3 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        新增 {{ result.created || 0 }}，更新 {{ result.updated || 0 }}，跳过 {{ result.skipped || 0 }}
                        <div v-if="result.errors?.length" class="mt-2 text-rose-700">{{ result.errors.join('；') }}</div>
                    </div>
                </div>
            </aside>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">权限列表</h2>
                            <div class="mt-1 flex flex-wrap gap-2 text-sm text-slate-500">
                                <span>总数 {{ meta.total || permissions.length }}</span>
                                <span>启用 {{ activeCount }}</span>
                                <span>全院 {{ allScopeCount }}</span>
                            </div>
                        </div>
                        <div class="flex min-w-[360px] max-w-xl flex-1 justify-end gap-2">
                            <input v-model="q" class="h-9 min-w-0 flex-1 rounded border border-slate-300 px-3 text-sm text-slate-950" placeholder="搜索工号、姓名、单位、权限、代码" @keydown.enter="fetchPermissions">
                            <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="fetchPermissions">搜索</button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[980px] table-fixed text-sm">
                        <colgroup>
                            <col class="w-[110px]">
                            <col class="w-[100px]">
                            <col class="w-[190px]">
                            <col class="w-[220px]">
                            <col class="w-[100px]">
                            <col class="w-[76px]">
                            <col class="w-[76px]">
                            <col class="w-[120px]">
                        </colgroup>
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">工号</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">姓名</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">单位</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">权限</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">分院代码</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">范围</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">状态</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="permission in permissions" :key="permission.id" class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 font-medium tabular-nums text-slate-950">{{ permission.employee_no }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-900">{{ permission.teacher_name || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="block truncate text-slate-700" :title="permission.unit_name || '-'">{{ permission.unit_name || '-' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="block truncate text-slate-900" :title="permission.scope_name || '-'">{{ permission.scope_name || '-' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium tabular-nums text-slate-950">{{ permission.department_code || '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="permission.scope_type === 'all' ? 'bg-slate-900 text-white' : 'bg-sky-50 text-sky-700'">
                                        {{ permission.scope_type === 'all' ? '全院' : '分院' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="permission.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                        {{ permission.is_active ? '启用' : '停用' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <button type="button" class="rounded border border-slate-300 px-2.5 py-1 text-xs text-slate-700 hover:bg-white" @click="edit(permission)">编辑</button>
                                    <button type="button" class="ml-2 rounded border border-rose-200 px-2.5 py-1 text-xs text-rose-700 hover:bg-rose-50" @click="removePermission(permission)">删除</button>
                                </td>
                            </tr>
                            <tr v-if="!permissions.length">
                                <td colspan="8" class="px-4 py-10 text-center text-slate-500">暂无权限记录</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</template>
