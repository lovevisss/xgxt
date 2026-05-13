<script setup>
import { onMounted, ref } from 'vue';

const records = ref([]);
const pagination = ref({});
const page = ref(1);
const keyword = ref('');
const emergency = ref('');
const editing = ref(null);
const notice = ref({ text: '', type: 'info' });
const form = ref({ id: '', name: '', relationship: '', specific_relationship: '', work_unit: '', position: '', phone: '', is_emergency_contact: '0' });

function getCSRF() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

function showStatus(text, type = 'info') {
    notice.value = { text, type };
}

function pageRange() {
    const total = pagination.value.last_page || 1;
    const curr = pagination.value.current_page || 1;
    const pages = [];
    for (let i = Math.max(1, curr - 2); i <= Math.min(total, curr + 2); i += 1) pages.push(i);
    return pages;
}

async function fetchRecords(target = 1) {
    showStatus('正在加载家庭信息...');
    const q = encodeURIComponent(keyword.value);
    const e = encodeURIComponent(emergency.value);
    const res = await fetch(`/student-families/data?page=${target}&q=${q}&emergency=${e}`);
    if (!res.ok) {
        showStatus('加载失败，请稍后重试。', 'error');
        return;
    }
    const data = await res.json();
    records.value = data.data || [];
    pagination.value = data;
    page.value = data.current_page || target;
    notice.value = { text: '', type: 'info' };
}

function openEdit(record) {
    editing.value = record;
    form.value = {
        id: record.id,
        name: record.name || '',
        relationship: record.relationship || '',
        specific_relationship: record.specific_relationship || '',
        work_unit: record.work_unit || '',
        position: record.position || '',
        phone: record.phone || '',
        is_emergency_contact: record.is_emergency_contact ? '1' : '0',
    };
}

async function saveEdit() {
    const res = await fetch(`/student-families/data/${form.value.id}`, {
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
    await fetchRecords(page.value);
}

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    keyword.value = params.get('q') || '';
    emergency.value = params.get('emergency') || '';
    await fetchRecords();
});
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-3xl font-bold">学生家庭基本信息</h1>
                    <p class="mt-1 text-sm text-slate-500">按学生维度查看和维护家庭联系人。</p>
                </div>
                <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">返回首页</a>
            </div>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-5">
                <input v-model="keyword" type="text" placeholder="按学号/学生姓名/手机/工作单位检索" class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2">
                <select v-model="emergency" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">全部联系人</option>
                    <option value="1">仅紧急联系人</option>
                    <option value="0">非紧急联系人</option>
                </select>
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white" @click="fetchRecords(1)">查询</button>
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @click="keyword=''; emergency=''; fetchRecords(1)">重置</button>
            </div>

            <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700'">{{ notice.text }}</div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">学号</th>
                            <th class="px-3 py-2 text-left">学生姓名</th>
                            <th class="px-3 py-2 text-left">联系人</th>
                            <th class="px-3 py-2 text-left">关系</th>
                            <th class="px-3 py-2 text-left">工作单位</th>
                            <th class="px-3 py-2 text-left">职位</th>
                            <th class="px-3 py-2 text-left">手机</th>
                            <th class="px-3 py-2 text-left">紧急联系人</th>
                            <th class="px-3 py-2 text-left">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in records" :key="row.id">
                            <td class="px-3 py-2">{{ row.stu_no }}</td>
                            <td class="px-3 py-2">{{ row.student_name || '-' }}</td>
                            <td class="px-3 py-2">{{ row.name || '-' }}</td>
                            <td class="px-3 py-2">{{ row.relationship || '-' }}</td>
                            <td class="px-3 py-2">{{ row.work_unit || '-' }}</td>
                            <td class="px-3 py-2">{{ row.position || '-' }}</td>
                            <td class="px-3 py-2">{{ row.phone || '-' }}</td>
                            <td class="px-3 py-2">{{ row.is_emergency_contact ? '是' : '否' }}</td>
                            <td class="px-3 py-2"><button class="rounded border border-slate-300 px-2 py-1 text-xs" @click="openEdit(row)">编辑</button></td>
                        </tr>
                        <tr v-if="!records.length"><td colspan="9" class="px-3 py-6 text-center text-slate-500">暂无数据</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <p class="text-xs text-slate-500">当前显示 {{ pagination.from || 0 }}-{{ pagination.to || 0 }} 条，共 {{ pagination.total || 0 }} 条</p>
                <div class="flex gap-2">
                    <button class="rounded border px-2 py-1 text-sm" :disabled="(pagination.current_page || 1) <= 1" @click="fetchRecords((pagination.current_page || 1) - 1)">上一页</button>
                    <button v-for="p in pageRange()" :key="p" class="rounded border px-2 py-1 text-sm" :class="p === (pagination.current_page || 1) ? 'bg-slate-900 text-white' : 'bg-white'" @click="fetchRecords(p)">{{ p }}</button>
                    <button class="rounded border px-2 py-1 text-sm" :disabled="(pagination.current_page || 1) >= (pagination.last_page || 1)" @click="fetchRecords((pagination.current_page || 1) + 1)">下一页</button>
                </div>
            </div>
        </section>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="editing = null">
            <form class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl" @submit.prevent="saveEdit">
                <h2 class="mb-3 text-xl font-bold">编辑家庭联系人</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <input v-model="form.name" class="rounded border border-slate-300 px-3 py-2 text-sm" placeholder="姓名">
                    <input v-model="form.relationship" class="rounded border border-slate-300 px-3 py-2 text-sm" placeholder="关系">
                    <input v-model="form.specific_relationship" class="rounded border border-slate-300 px-3 py-2 text-sm" placeholder="具体关系">
                    <input v-model="form.work_unit" class="rounded border border-slate-300 px-3 py-2 text-sm" placeholder="工作单位">
                    <input v-model="form.position" class="rounded border border-slate-300 px-3 py-2 text-sm" placeholder="职位">
                    <input v-model="form.phone" class="rounded border border-slate-300 px-3 py-2 text-sm" placeholder="手机">
                    <select v-model="form.is_emergency_contact" class="rounded border border-slate-300 px-3 py-2 text-sm sm:col-span-2"><option value="0">否</option><option value="1">是</option></select>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded border border-slate-300 px-3 py-2 text-sm" @click="editing = null">取消</button>
                    <button class="rounded bg-slate-900 px-3 py-2 text-sm font-semibold text-white">保存</button>
                </div>
            </form>
        </div>
    </main>
</template>

