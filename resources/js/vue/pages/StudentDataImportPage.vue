<script setup>
import { computed, ref } from 'vue';

const importTypes = [
    {
        key: 'award_punishment',
        title: '奖惩记录',
        eyebrow: '荣誉与处分',
        accept: '.xlsx,.xls',
        endpoint: '/student-imports/award_punishment',
        template: '/student-imports/template/award_punishment',
        fields: '奖励：学号、姓名、奖励名称、年度、等级；惩罚：学号、姓名、惩罚原因、惩罚时间、发生年度',
        note: '使用两个工作表：奖励、惩罚。',
        resultLabels: { reward_imported: '奖励', punishment_imported: '惩罚' },
    },
    {
        key: 'loan',
        title: '助学贷款',
        eyebrow: '生源地贷款',
        accept: '.xlsx,.xls',
        endpoint: '/student-imports/loan',
        template: '/student-imports/template/loan',
        fields: '序号、身份证号码、学号、姓名、二级学院、班级、金额、备注',
        note: '按“学号 + 年度 + 来源”更新，适合国开行、招商银行等来源。',
        resultLabels: { imported: '贷款记录' },
    },
    {
        key: 'support',
        title: '资助对象',
        eyebrow: '需要帮助状况',
        accept: '.xlsx,.xls',
        endpoint: '/student-imports/support',
        template: '/student-imports/template/support',
        fields: '序号、学号、姓名、性别、二级学院、专业、资助等级',
        note: '按“学号 + 学年”更新，资助等级会显示在学生主页。',
        resultLabels: { imported: '资助对象' },
    },
];

const selectedKey = ref('support');
const file = ref(null);
const uploading = ref(false);
const annualYear = ref('2025');
const academicYear = ref('2025-2026');
const source = ref('国开行');
const notice = ref({ text: '', type: 'info' });
const result = ref(null);

const selectedType = computed(() => importTypes.find((type) => type.key === selectedKey.value) || importTypes[0]);
const showLoanOptions = computed(() => selectedKey.value === 'loan');
const showSupportOptions = computed(() => selectedKey.value === 'support');

function getCSRF() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.content : '';
}

function selectType(key) {
    selectedKey.value = key;
    file.value = null;
    result.value = null;
    notice.value = { text: '', type: 'info' };
}

function chooseFile(event) {
    file.value = event.target.files?.[0] || null;
    result.value = null;
    notice.value = file.value
        ? { text: `已选择：${file.value.name}`, type: 'info' }
        : { text: '', type: 'info' };
}

async function upload() {
    if (!file.value || uploading.value) {
        notice.value = { text: '请先选择 Excel 文件。', type: 'error' };
        return;
    }

    uploading.value = true;
    notice.value = { text: `正在导入${selectedType.value.title}...`, type: 'info' };

    const formData = new FormData();
    formData.append('file', file.value);
    if (showLoanOptions.value) {
        formData.append('annual_year', annualYear.value);
        formData.append('source', source.value);
    }
    if (showSupportOptions.value) {
        formData.append('academic_year', academicYear.value);
    }

    const response = await fetch(selectedType.value.endpoint, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCSRF(),
            Accept: 'application/json',
        },
        body: formData,
    });

    uploading.value = false;

    if (!response.ok) {
        notice.value = { text: '导入失败，请确认文件格式和表头后重试。', type: 'error' };
        return;
    }

    result.value = await response.json();
    const hasErrors = (result.value.errors || []).length > 0;
    notice.value = {
        text: hasErrors ? '导入完成，但有部分行未通过校验。' : '导入完成。',
        type: hasErrors ? 'warning' : 'success',
    };
}
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-950">学生数据导入</h1>
                    <p class="mt-1 text-sm text-slate-500">集中导入奖惩、助学贷款和资助对象数据，导入后统一展示在学生主页。</p>
                </div>
                <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回首页</a>
            </div>
        </header>

        <section class="grid gap-6 lg:grid-cols-[320px_1fr]">
            <aside class="space-y-3">
                <button
                    v-for="type in importTypes"
                    :key="type.key"
                    type="button"
                    class="w-full rounded-lg border bg-white p-4 text-left shadow-sm transition hover:border-slate-400"
                    :class="selectedKey === type.key ? 'border-slate-900 ring-1 ring-slate-900' : 'border-slate-200'"
                    @click="selectType(type.key)"
                >
                    <p class="text-xs font-semibold text-slate-500">{{ type.eyebrow }}</p>
                    <p class="mt-1 text-lg font-bold text-slate-950">{{ type.title }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ type.note }}</p>
                </button>
            </aside>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500">{{ selectedType.eyebrow }}</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-950">{{ selectedType.title }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ selectedType.fields }}</p>
                    </div>
                    <a :href="selectedType.template" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">下载示例模板</a>
                </div>

                <div v-if="showLoanOptions" class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm text-slate-600">
                        发生年度
                        <input v-model="annualYear" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="number" min="1900" max="2100">
                    </label>
                    <label class="text-sm text-slate-600">
                        贷款来源
                        <input v-model="source" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text">
                    </label>
                </div>

                <label v-if="showSupportOptions" class="mt-5 block text-sm text-slate-600">
                    学年
                    <input v-model="academicYear" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text" placeholder="2025-2026">
                </label>

                <div class="mt-5 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6">
                    <input :accept="selectedType.accept" type="file" class="block w-full text-sm text-slate-700 file:mr-4 file:rounded file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white" @change="chooseFile">
                    <p class="mt-3 text-xs text-slate-500">支持 .xls / .xlsx。请保留模板表头，标题行可以存在。</p>
                </div>

                <div v-if="notice.text" class="mt-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : notice.type === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-sky-200 bg-sky-50 text-sky-700'">
                    {{ notice.text }}
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="uploading" @click="upload">{{ uploading ? '导入中...' : '开始导入' }}</button>
                </div>

                <div v-if="result" class="mt-6 rounded-lg border border-slate-200 p-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div v-for="(label, key) in selectedType.resultLabels" :key="key" class="rounded bg-emerald-50 p-3">
                            <p class="text-xs text-emerald-700">{{ label }}</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-800">{{ result[key] || 0 }}</p>
                        </div>
                    </div>
                    <ul v-if="(result.errors || []).length" class="mt-4 space-y-1 text-sm text-rose-700">
                        <li v-for="error in result.errors" :key="error">{{ error }}</li>
                    </ul>
                </div>
            </div>
        </section>
    </main>
</template>
