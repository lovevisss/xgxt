<script setup>
import { ref } from 'vue';

const file = ref(null);
const uploading = ref(false);
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
    notice.value = { text: '正在导入奖惩数据...', type: 'info' };

    const formData = new FormData();
    formData.append('file', file.value);

    const response = await fetch('/student-award-punishment-import', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCSRF(),
            Accept: 'application/json',
        },
        body: formData,
    });

    uploading.value = false;

    if (!response.ok) {
        notice.value = { text: '导入失败，请确认文件格式为 .xlsx 并使用示例模板。', type: 'error' };
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
                    <h1 class="text-2xl font-bold text-slate-950">学生奖惩导入</h1>
                    <p class="mt-1 text-sm text-slate-500">通过 Excel 一次导入奖励和惩罚记录，导入后会显示在学生主页。</p>
                </div>
                <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回首页</a>
            </div>
        </header>

        <section class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">上传文件</h2>
                <div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6">
                    <input type="file" accept=".xlsx" class="block w-full text-sm text-slate-700 file:mr-4 file:rounded file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white" @change="chooseFile">
                    <p class="mt-3 text-xs text-slate-500">仅支持 .xlsx。请保留“奖励”“惩罚”两个工作表及表头。</p>
                </div>

                <div v-if="notice.text" class="mt-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : notice.type === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-sky-200 bg-sky-50 text-sky-700'">
                    {{ notice.text }}
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="uploading" @click="upload">{{ uploading ? '导入中...' : '开始导入' }}</button>
                    <a href="/student-award-punishment-import/template" class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">下载导入示例</a>
                </div>

                <div v-if="result" class="mt-6 rounded-lg border border-slate-200 p-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded bg-emerald-50 p-3">
                            <p class="text-xs text-emerald-700">奖励导入</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-800">{{ result.reward_imported || 0 }}</p>
                        </div>
                        <div class="rounded bg-amber-50 p-3">
                            <p class="text-xs text-amber-700">惩罚导入</p>
                            <p class="mt-1 text-2xl font-bold text-amber-800">{{ result.punishment_imported || 0 }}</p>
                        </div>
                    </div>
                    <ul v-if="(result.errors || []).length" class="mt-4 space-y-1 text-sm text-rose-700">
                        <li v-for="error in result.errors" :key="error">{{ error }}</li>
                    </ul>
                </div>
            </div>

            <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">模板字段</h2>
                <div class="mt-4 space-y-4 text-sm text-slate-700">
                    <div>
                        <p class="font-semibold text-slate-950">奖励工作表</p>
                        <p class="mt-1">学号、姓名、奖励名称、年度、等级</p>
                        <p class="mt-1 text-xs text-slate-500">等级可填写国家级、省级、校级、院级等。</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-950">惩罚工作表</p>
                        <p class="mt-1">学号、姓名、惩罚原因、惩罚时间、发生年度</p>
                        <p class="mt-1 text-xs text-slate-500">惩罚时间建议使用 yyyy-mm-dd。</p>
                    </div>
                </div>
            </aside>
        </section>
    </main>
</template>
