<script setup>
import { computed, onMounted, ref } from 'vue';

const permissions = ref([]);
const meta = ref({ total: 0 });
const q = ref('');
const file = ref(null);
const editing = ref(null);
const saving = ref(false);
const notice = ref({ text: '', type: 'info' });
const result = ref(null);
const form = ref(emptyForm());

const activeCount = computed(() => permissions.value.filter((item) => item.is_active).length);

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

    const body = new FormData();
    body.append('file', file.value);
    const response = await fetch('/student-access-permissions/import', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body,
    });
    const payload = await response.json().catch(() => ({}));

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
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-500">学生权限 / 分院与全院范围</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">学生权限清单</h1>
                    <p class="mt-1 text-sm text-slate-500">按教师工号统一管理学生查看范围，清单授权仅提供只读访问。</p>
                </div>
                <div class="flex gap-2">
                    <a href="/student-access-permissions/template" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">下载模板</a>
                    <a href="/" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回首页</a>
                </div>
            </div>
        </header>

        <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700'">
            {{ notice.text }}
        </div>

        <section class="grid gap-5 lg:grid-cols-[360px_1fr]">
            <aside class="space-y-5">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">{{ editing ? '编辑权限' : '新增权限' }}</h2>
                    <div class="mt-4 space-y-3">
                        <label class="block text-sm text-slate-600">工号<input v-model="form.employee_no" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="block text-sm text-slate-600">姓名<input v-model="form.teacher_name" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="block text-sm text-slate-600">单位<input v-model="form.unit_name" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950"></label>
                        <label class="block text-sm text-slate-600">权限<input v-model="form.scope_name" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="如 金融与经贸学院 / 最高"></label>
                        <label class="block text-sm text-slate-600">分院代码<input v-model="form.department_code" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="如 100301 / 1003"></label>
                        <label class="flex items-center gap-2 text-sm text-slate-600"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">启用</label>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="saving" @click="savePermission">{{ saving ? '保存中...' : '保存' }}</button>
                        <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700" @click="resetForm">清空</button>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Excel 导入</h2>
                    <p class="mt-1 text-sm text-slate-500">第 2 行表头：序号、单位、工号、姓名、权限、分院代码。</p>
                    <input class="mt-4 block w-full text-sm" type="file" accept=".xls,.xlsx" @change="file = $event.target.files?.[0] || null">
                    <button type="button" class="mt-3 rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="importExcel">开始导入</button>
                    <div v-if="result" class="mt-3 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        新增 {{ result.created || 0 }}，更新 {{ result.updated || 0 }}，跳过 {{ result.skipped || 0 }}
                        <div v-if="result.errors?.length" class="mt-2 text-rose-700">{{ result.errors.join('；') }}</div>
                    </div>
                </div>
            </aside>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">权限列表</h2>
                        <p class="mt-1 text-sm text-slate-500">共 {{ meta.total || permissions.length }} 条，当前页启用 {{ activeCount }} 条</p>
                    </div>
                    <div class="flex gap-2">
                        <input v-model="q" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="搜索工号、姓名、单位、权限" @keydown.enter="fetchPermissions">
                        <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700" @click="fetchPermissions">搜索</button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">工号</th>
                                <th class="px-3 py-2 text-left">姓名</th>
                                <th class="px-3 py-2 text-left">单位</th>
                                <th class="px-3 py-2 text-left">权限</th>
                                <th class="px-3 py-2 text-left">分院代码</th>
                                <th class="px-3 py-2 text-left">范围</th>
                                <th class="px-3 py-2 text-left">状态</th>
                                <th class="px-3 py-2 text-left">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="permission in permissions" :key="permission.id">
                                <td class="px-3 py-2 font-medium text-slate-950">{{ permission.employee_no }}</td>
                                <td class="px-3 py-2">{{ permission.teacher_name || '-' }}</td>
                                <td class="px-3 py-2">{{ permission.unit_name || '-' }}</td>
                                <td class="px-3 py-2">{{ permission.scope_name || '-' }}</td>
                                <td class="px-3 py-2">{{ permission.department_code || '-' }}</td>
                                <td class="px-3 py-2">{{ permission.scope_type === 'all' ? '全院' : '分院' }}</td>
                                <td class="px-3 py-2">{{ permission.is_active ? '启用' : '停用' }}</td>
                                <td class="px-3 py-2">
                                    <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" @click="edit(permission)">编辑</button>
                                    <button type="button" class="ml-2 rounded border border-rose-200 px-2 py-1 text-xs text-rose-700" @click="removePermission(permission)">删除</button>
                                </td>
                            </tr>
                            <tr v-if="!permissions.length">
                                <td colspan="8" class="px-3 py-8 text-center text-slate-500">暂无权限记录</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</template>
