<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

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
    {
        key: 'family',
        title: '家长信息',
        eyebrow: '历史家校联系单',
        accept: '.xlsx,.xls',
        endpoint: '/student-imports/family',
        template: '/student-imports/template/family',
        fields: '学号、姓名、家庭成员称谓1-4、姓名1-4、工作单位1-4、职务1-4、联系电话1-4',
        note: '一行学生内的多个联系人会拆成多条家长记录，写入同一张 student_families 表。',
        resultLabels: { imported: '联系人', students: '涉及学生', skipped: '跳过行' },
    },
    {
        key: 'medical_insurance',
        title: '大学生医保',
        eyebrow: '年度参保缴费',
        accept: '.xlsx,.xls',
        endpoint: '/student-imports/medical_insurance',
        template: '/student-imports/template/medical_insurance',
        fields: '姓名、学号、参保地、参保日期、险种、参保状态、年度、年度是否缴费、缴费起止年月、缴费类型',
        note: '按“学号 + 年度”更新，导入后学生主页会显示当年是否参保。',
        resultLabels: { imported: '医保记录' },
    },
    {
        key: 'safety_insurance',
        title: '大学生学平险',
        eyebrow: '年度参保',
        accept: '.xlsx,.xls',
        endpoint: '/student-imports/safety_insurance',
        template: '/student-imports/template/safety_insurance',
        fields: '年级、学制、学院、专业、班级、学号、姓名、是否参保',
        note: '按“学号 + 年度”更新，导入后学生主页会显示当年学平险是否参保。',
        resultLabels: { imported: '学平险记录' },
    },
    {
        key: 'physical_test',
        title: '体测成绩',
        eyebrow: '学年总分',
        accept: '.xlsx,.xls',
        endpoint: '/student-imports/physical_test',
        template: '/student-imports/template/physical_test',
        fields: '学年、姓名、学号、性别、院系、班级、总分、备注',
        note: '按“学号 + 学年”更新，导入后按学年显示在学生主页。',
        resultLabels: { imported: '体测成绩' },
    },
    {
        key: 'comprehensive_assessment',
        title: '综测成绩',
        eyebrow: '综合测评汇总',
        accept: '.xlsx,.xls',
        endpoint: '/student-imports/comprehensive_assessment',
        template: '/student-imports/template/comprehensive_assessment',
        fields: '名次、姓名、学号、综合测评成绩，以及德育、智育、体育、美育、劳育总分。支持每个年级一个工作表。',
        note: '按“学号 + 学年”更新，导入后会展示在学生主页的综测成绩模块。',
        resultLabels: { imported: '综测成绩' },
    },
    {
        key: 'cadre_assessment',
        title: '团学干部任职考核',
        eyebrow: '任职与考核等级',
        accept: '.pdf',
        endpoint: '/student-imports/cadre_assessment',
        template: '/student-imports/template/cadre_assessment',
        fields: 'PDF 汇总表：姓名、团学机构、部门、任职、总分、考核等级',
        note: '按学年导入 PDF。系统会按姓名自动匹配学生，同名无法区分的记录会进入待确认。',
        resultLabels: { imported: '已匹配', pending: '待确认', skipped: '跳过' },
    },
];

const selectedKey = ref('support');
const file = ref(null);
const uploading = ref(false);
const annualYear = ref(String(new Date().getFullYear()));
const academicYear = ref('2025-2026');
const source = ref('国开行');
const notice = ref({ text: '', type: 'info' });
const result = ref(null);
const taskId = ref(null);
const pollingTimer = ref(null);
const resolvingMatchId = ref(null);

const selectedType = computed(() => importTypes.find((type) => type.key === selectedKey.value) || importTypes[0]);
const showLoanOptions = computed(() => selectedKey.value === 'loan');
const showSupportOptions = computed(() => ['support', 'cadre_assessment', 'comprehensive_assessment'].includes(selectedKey.value));
const showAnnualYearOptions = computed(() => ['loan', 'medical_insurance', 'safety_insurance'].includes(selectedKey.value));

function getCSRF() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.content : '';
}

function selectType(key) {
    selectedKey.value = key;
    file.value = null;
    result.value = null;
    taskId.value = null;
    notice.value = { text: '', type: 'info' };
    stopPolling();
}

function chooseFile(event) {
    file.value = event.target.files?.[0] || null;
    result.value = null;
    if (file.value && /综测|综合测评/.test(file.value.name) && selectedKey.value !== 'comprehensive_assessment') {
        selectedKey.value = 'comprehensive_assessment';
        taskId.value = null;
        stopPolling();
    }
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
    if (selectedKey.value === 'family') {
        formData.append('async', '1');
    }
    if (showAnnualYearOptions.value) {
        formData.append('annual_year', annualYear.value);
    }
    if (showLoanOptions.value) {
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
        let message = '导入失败，请确认文件格式和表头后重试。';
        try {
            const error = await response.json();
            message = error.message || error.error || message;
        } catch (e) {
            if (response.status >= 500) {
                message = '导入失败，服务器处理时出错，请查看日志或稍后重试。';
            }
        }
        notice.value = { text: message, type: 'error' };
        return;
    }

    const payload = await response.json();
    if (payload.queued) {
        taskId.value = payload.task_id;
        result.value = payload.result || { imported: 0, students: 0, skipped: 0, errors: [] };
        notice.value = { text: '导入任务已提交，正在后台处理...', type: 'info' };
        startPolling();
        return;
    }

    result.value = payload;
    const hasErrors = (result.value.errors || []).length > 0;
    notice.value = {
        text: hasErrors ? '导入完成，但有部分行未通过校验。' : '导入完成。',
        type: hasErrors ? 'warning' : 'success',
    };
}

function stopPolling() {
    if (pollingTimer.value) {
        window.clearInterval(pollingTimer.value);
        pollingTimer.value = null;
    }
}

function startPolling() {
    stopPolling();
    pollingTimer.value = window.setInterval(fetchTaskStatus, 2000);
    fetchTaskStatus();
}

async function fetchTaskStatus() {
    if (!taskId.value) {
        stopPolling();
        return;
    }

    const response = await fetch(`/student-imports/status/${taskId.value}`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        return;
    }

    const payload = await response.json();
    result.value = payload.result || result.value || { imported: 0, students: 0, skipped: 0, errors: [] };

    if (payload.status === 'queued') {
        notice.value = { text: '导入任务排队中，请保持队列进程运行。', type: 'info' };
    } else if (payload.status === 'running') {
        notice.value = { text: `正在后台导入，已写入 ${result.value.imported || 0} 条联系人...`, type: 'info' };
    } else if (payload.status === 'succeeded') {
        stopPolling();
        const hasErrors = (result.value.errors || []).length > 0;
        notice.value = {
            text: hasErrors ? '导入完成，但有部分行未通过校验。' : '导入完成。',
            type: hasErrors ? 'warning' : 'success',
        };
    } else if (payload.status === 'failed') {
        stopPolling();
        notice.value = { text: payload.error || '导入任务失败，请查看日志。', type: 'error' };
    }
}

async function resolveCadreMatch(match, candidate) {
    if (!match?.id || !candidate?.xgh || resolvingMatchId.value) {
        return;
    }

    resolvingMatchId.value = match.id;

    const response = await fetch(`/student-imports/cadre-assessment-matches/${match.id}/resolve`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCSRF(),
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ student_xgh: candidate.xgh }),
    });

    resolvingMatchId.value = null;

    if (!response.ok) {
        notice.value = { text: '确认失败，请刷新后重试。', type: 'error' };
        return;
    }

    result.value.pending_records = (result.value.pending_records || []).filter((item) => item.id !== match.id);
    result.value.pending = Math.max(0, (result.value.pending || 0) - 1);
    result.value.imported = (result.value.imported || 0) + 1;
    notice.value = { text: `已确认 ${match.student_name} -> ${candidate.xm || candidate.xgh}`, type: 'success' };
}

onBeforeUnmount(stopPolling);
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-950">学生数据导入</h1>
                    <p class="mt-1 text-sm text-slate-500">集中导入奖惩、助学贷款、资助对象、家长信息、医保、学平险、体测和综测数据，导入后统一展示在学生主页。</p>
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

                <div v-if="showAnnualYearOptions" class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm text-slate-600">
                        {{ ['medical_insurance', 'safety_insurance'].includes(selectedKey) ? '参保年度' : '发生年度' }}
                        <input v-model="annualYear" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-950" type="number" min="1900" max="2100">
                    </label>
                    <label v-if="showLoanOptions" class="text-sm text-slate-600">
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
                    <div v-if="(result.pending_records || []).length" class="mt-4 space-y-3">
                        <h3 class="text-sm font-semibold text-slate-900">待人工确认</h3>
                        <div v-for="match in result.pending_records" :key="match.id" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <div class="text-sm text-slate-800">
                                <span class="font-semibold">{{ match.student_name }}</span>
                                <span class="ml-2">{{ match.organization || '-' }} / {{ match.department || '-' }} / {{ match.position || '-' }}</span>
                                <span class="ml-2 text-slate-500">{{ match.grade || '-' }}</span>
                            </div>
                            <div v-if="(match.candidates || []).length" class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="candidate in match.candidates"
                                    :key="candidate.xgh"
                                    type="button"
                                    class="rounded border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                                    :disabled="resolvingMatchId === match.id"
                                    @click="resolveCadreMatch(match, candidate)"
                                >
                                    {{ candidate.xm || '-' }} {{ candidate.xgh }} {{ candidate.dwmc || '' }} {{ candidate.bjmc || '' }}
                                </button>
                            </div>
                            <p v-else class="mt-2 text-xs text-amber-700">没有找到候选学生，请先核对学生基础信息后重新导入或补充匹配。</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
