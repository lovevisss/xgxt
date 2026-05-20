<script setup>
import { ref } from 'vue';

const props = defineProps({
    student: { type: Object, required: true },
    families: { type: Array, default: () => [] },
    awards: { type: Array, default: () => [] },
    punishments: { type: Array, default: () => [] },
    loans: { type: Array, default: () => [] },
    supportRecipients: { type: Array, default: () => [] },
    canUpdateFamilies: { type: Boolean, default: false },
});

const familyRows = ref([...props.families]);
const editing = ref(null);
const saving = ref(false);
const notice = ref({ text: '', type: 'info' });
const form = ref({
    id: '',
    name: '',
    relationship: '',
    specific_relationship: '',
    work_unit: '',
    position: '',
    phone: '',
    is_emergency_contact: '0',
});

function getCSRF() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.content : '';
}

function showNotice(text, type = 'info') {
    notice.value = { text, type };
}

function openEdit(family) {
    editing.value = family;
    notice.value = { text: '', type: 'info' };
    form.value = {
        id: family.id,
        name: family.name || '',
        relationship: family.relationship || '',
        specific_relationship: family.specific_relationship || '',
        work_unit: family.work_unit || '',
        position: family.position || '',
        phone: family.phone || '',
        is_emergency_contact: family.is_emergency_contact ? '1' : '0',
    };
}

function closeEdit() {
    editing.value = null;
    saving.value = false;
}

function money(value) {
    const number = Number(value || 0);

    return number.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function saveFamily() {
    if (!editing.value || saving.value) {
        return;
    }

    saving.value = true;
    const response = await fetch(`/student-families/data/${form.value.id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRF(),
            Accept: 'application/json',
        },
        body: JSON.stringify(form.value),
    });

    if (!response.ok) {
        saving.value = false;
        showNotice(response.status === 403 ? '当前账号无权修改该学生的家长信息。' : '保存失败，请稍后重试。', 'error');

        return;
    }

    const updated = await response.json();
    familyRows.value = familyRows.value.map((row) => (row.id === updated.id ? updated : row));
    closeEdit();
    showNotice('家长信息已更新。', 'success');
}
</script>

<template>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">学生主页</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ props.student.xm }}（{{ props.student.xgh }}）</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        分院：{{ props.student.dwmc || '-' }} / 班级：{{ props.student.bjmc || '-' }} / 联系电话：{{ props.student.yddh || '-' }}
                    </p>
                </div>
                <a href="/students" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回学生管理</a>
            </div>
        </header>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold text-slate-950">基础信息</h2>
            <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><span class="text-slate-500">学号：</span>{{ props.student.xgh || '-' }}</div>
                <div><span class="text-slate-500">姓名：</span>{{ props.student.xm || '-' }}</div>
                <div><span class="text-slate-500">分院：</span>{{ props.student.dwmc || '-' }}</div>
                <div><span class="text-slate-500">班级：</span>{{ props.student.bjmc || '-' }}</div>
                <div><span class="text-slate-500">联系电话：</span>{{ props.student.yddh || '-' }}</div>
                <div><span class="text-slate-500">最近刷码：</span>{{ props.student.last_smsj || '-' }}</div>
                <div><span class="text-slate-500">状态：</span>{{ props.student.status || '-' }}</div>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-950">资助对象记录</h2>
                <a href="/student-support/import" class="text-sm text-sky-700 hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">学年</th>
                            <th class="px-3 py-2 text-left">资助等级</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">专业</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in props.supportRecipients" :key="item.id">
                            <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">{{ item.support_level || '-' }}</span>
                            </td>
                            <td class="px-3 py-2">{{ item.college || '-' }}</td>
                            <td class="px-3 py-2">{{ item.major || '-' }}</td>
                        </tr>
                        <tr v-if="!props.supportRecipients.length">
                            <td colspan="4" class="px-3 py-6 text-center text-slate-500">暂无资助对象记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-950">助学贷款记录</h2>
                <a href="/student-loans/import" class="text-sm text-sky-700 hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">年度</th>
                            <th class="px-3 py-2 text-left">来源</th>
                            <th class="px-3 py-2 text-left">金额</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">班级</th>
                            <th class="px-3 py-2 text-left">备注</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="loan in props.loans" :key="loan.id">
                            <td class="px-3 py-2">{{ loan.annual_year || '-' }}</td>
                            <td class="px-3 py-2">{{ loan.source || '-' }}</td>
                            <td class="px-3 py-2">{{ money(loan.amount) }}</td>
                            <td class="px-3 py-2">{{ loan.college || '-' }}</td>
                            <td class="px-3 py-2">{{ loan.class_name || '-' }}</td>
                            <td class="px-3 py-2">{{ loan.remark || '-' }}</td>
                        </tr>
                        <tr v-if="!props.loans.length">
                            <td colspan="6" class="px-3 py-6 text-center text-slate-500">暂无助学贷款记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-slate-950">奖励记录</h2>
                    <a href="/student-award-punishment-import" class="text-sm text-sky-700 hover:underline">导入</a>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">年度</th>
                                <th class="px-3 py-2 text-left">奖励名称</th>
                                <th class="px-3 py-2 text-left">等级</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="award in props.awards" :key="award.id">
                                <td class="px-3 py-2">{{ award.annual_year || '-' }}</td>
                                <td class="px-3 py-2">{{ award.award_name || '-' }}</td>
                                <td class="px-3 py-2">{{ award.level || '-' }}</td>
                            </tr>
                            <tr v-if="!props.awards.length">
                                <td colspan="3" class="px-3 py-6 text-center text-slate-500">暂无奖励记录</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-slate-950">惩罚记录</h2>
                    <a href="/student-award-punishment-import" class="text-sm text-sky-700 hover:underline">导入</a>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">发生年度</th>
                                <th class="px-3 py-2 text-left">惩罚时间</th>
                                <th class="px-3 py-2 text-left">惩罚原因</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="punishment in props.punishments" :key="punishment.id">
                                <td class="px-3 py-2">{{ punishment.annual_year || '-' }}</td>
                                <td class="px-3 py-2">{{ punishment.punished_at || '-' }}</td>
                                <td class="px-3 py-2">{{ punishment.reason || '-' }}</td>
                            </tr>
                            <tr v-if="!props.punishments.length">
                                <td colspan="3" class="px-3 py-6 text-center text-slate-500">暂无惩罚记录</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">家长信息</h2>
                    <p v-if="!props.canUpdateFamilies" class="mt-1 text-xs text-slate-500">仅该学生当前分院的辅导员或超管可修改。</p>
                </div>
            </div>

            <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'">
                {{ notice.text }}
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">姓名</th>
                            <th class="px-3 py-2 text-left">关系</th>
                            <th class="px-3 py-2 text-left">单位</th>
                            <th class="px-3 py-2 text-left">职务</th>
                            <th class="px-3 py-2 text-left">手机</th>
                            <th class="px-3 py-2 text-left">紧急联系人</th>
                            <th v-if="props.canUpdateFamilies" class="px-3 py-2 text-left">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="family in familyRows" :key="family.id" class="align-top">
                            <td class="px-3 py-2">{{ family.name || '-' }}</td>
                            <td class="px-3 py-2">{{ family.relationship || family.specific_relationship || '-' }}</td>
                            <td class="px-3 py-2">{{ family.work_unit || '-' }}</td>
                            <td class="px-3 py-2">{{ family.position || '-' }}</td>
                            <td class="px-3 py-2">{{ family.phone || '-' }}</td>
                            <td class="px-3 py-2">{{ family.is_emergency_contact ? '是' : '否' }}</td>
                            <td v-if="props.canUpdateFamilies" class="px-3 py-2">
                                <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="openEdit(family)">编辑</button>
                            </td>
                        </tr>
                        <tr v-if="!familyRows.length">
                            <td :colspan="props.canUpdateFamilies ? 7 : 6" class="px-3 py-6 text-center text-slate-500">暂无家长信息</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="closeEdit">
            <form class="w-full max-w-2xl rounded-lg bg-white p-5 shadow-xl" @submit.prevent="saveFamily">
                <h2 class="mb-4 text-xl font-bold text-slate-950">编辑家长信息</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="text-sm text-slate-600">
                        姓名
                        <input v-model="form.name" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text">
                    </label>
                    <label class="text-sm text-slate-600">
                        关系
                        <input v-model="form.relationship" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text">
                    </label>
                    <label class="text-sm text-slate-600">
                        具体关系
                        <input v-model="form.specific_relationship" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text">
                    </label>
                    <label class="text-sm text-slate-600">
                        工作单位
                        <input v-model="form.work_unit" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text">
                    </label>
                    <label class="text-sm text-slate-600">
                        职务
                        <input v-model="form.position" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text">
                    </label>
                    <label class="text-sm text-slate-600">
                        手机
                        <input v-model="form.phone" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text">
                    </label>
                    <label class="text-sm text-slate-600 sm:col-span-2">
                        紧急联系人
                        <select v-model="form.is_emergency_contact" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            <option value="0">否</option>
                            <option value="1">是</option>
                        </select>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="closeEdit">取消</button>
                    <button class="rounded bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="saving">{{ saving ? '保存中...' : '保存' }}</button>
                </div>
            </form>
        </div>
    </main>
</template>
