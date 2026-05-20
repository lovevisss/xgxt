<script setup>
import { ref } from 'vue';

const file = ref(null);
const uploading = ref(false);
const annualYear = ref('2025');
const source = ref('国开行');
const notice = ref({ text: '', type: 'info' });
const result = ref(null);

function getCSRF() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.content : '';
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
    notice.value = { text: '正在导入助学贷款数据...', type: 'info' };

    const formData = new FormData();
    formData.append('file', file.value);
    formData.append('annual_year', annualYear.value);
    formData.append('source', source.value);

    const response = await fetch('/student-loans/import', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCSRF(),
            Accept: 'application/json',
        },
        body: formData,
    });

    uploading.value = false;

    if (!response.ok) {
        notice.value = { text: '导入失败，请确认文件格式为 .xlsx，且包含“学号”“金额”等表头。', type: 'error' };
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
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-950">学生助学贷款导入</h1>
                    <p class="mt-1 text-sm text-slate-500">支持导入生源地助学贷款 Excel，导入后会显示在学生主页。</p>
                </div>
                <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回首页</a>
            </div>
        </header>

        <section class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">上传文件</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm text-slate-600">
                        发生年度
                        <input v-model="annualYear" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="number" min="1900" max="2100">
                    </label>
                    <label class="text-sm text-slate-600">
                        贷款来源
                        <input v-model="source" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="text">
                    </label>
                </div>

                <div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6">
                    <input type="file" accept=".xlsx" class="block w-full text-sm text-slate-700 file:mr-4 file:rounded file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white" @change="chooseFile">
                    <p class="mt-3 text-xs text-slate-500">仅支持 .xlsx。系统会识别“序号、身份证号码、学号、姓名、二级学院、班级、金额、备注”格式。</p>
                </div>

                <div v-if="notice.text" class="mt-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : notice.type === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-sky-200 bg-sky-50 text-sky-700'">
                    {{ notice.text }}
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="uploading" @click="upload">{{ uploading ? '导入中...' : '开始导入' }}</button>
                    <a href="/student-loans/import/template" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">下载导入示例</a>
                </div>

                <div v-if="result" class="mt-6 rounded-lg border border-slate-200 p-4">
                    <div class="rounded bg-emerald-50 p-3">
                        <p class="text-xs text-emerald-700">导入记录</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-800">{{ result.imported || 0 }}</p>
                    </div>
                    <ul v-if="(result.errors || []).length" class="mt-4 space-y-1 text-sm text-rose-700">
                        <li v-for="error in result.errors" :key="error">{{ error }}</li>
                    </ul>
                </div>
            </div>

            <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">当前文件格式</h2>
                <div class="mt-4 space-y-4 text-sm text-slate-700">
                    <p>第 1 行可为标题，例如“2025年生源地贷款到款名单汇总”。</p>
                    <p>第 2 行为表头：序号、身份证号码、学号、姓名、二级学院、班级、金额、备注。</p>
                    <p class="text-xs text-slate-500">导入时按“学号 + 年度 + 来源”更新同一学生的贷款记录，避免重复导入。</p>
                </div>
            </aside>
        </section>
    </main>
</template>
