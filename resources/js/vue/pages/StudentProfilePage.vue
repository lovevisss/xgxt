<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    student: { type: Object, required: true },
    families: { type: Array, default: () => [] },
    awards: { type: Array, default: () => [] },
    punishments: { type: Array, default: () => [] },
    loans: { type: Array, default: () => [] },
    supportRecipients: { type: Array, default: () => [] },
    technologyCompetitionAwards: { type: Array, default: () => [] },
    educationHistories: { type: Array, default: () => [] },
    medicalInsurances: { type: Array, default: () => [] },
    currentMedicalInsurance: { type: Object, default: null },
    safetyInsurances: { type: Array, default: () => [] },
    currentSafetyInsurance: { type: Object, default: null },
    physicalTests: { type: Array, default: () => [] },
    comprehensiveAssessments: { type: Array, default: () => [] },
    cadreAssessments: { type: Array, default: () => [] },
    currentYear: { type: Number, default: () => new Date().getFullYear() },
    dormitory: { type: Object, default: null },
    dormitorySummary: { type: Object, default: () => ({}) },
    roommates: { type: Array, default: () => [] },
    selectedSemester: { type: String, default: '' },
    semesterLabel: { type: String, default: '' },
    selectedWeek: { type: Number, default: 1 },
    weekLabel: { type: String, default: '' },
    prevWeekUrl: { type: String, default: null },
    nextWeekUrl: { type: String, default: null },
    weeklySchedule: { type: Array, default: () => [] },
    gradesBySemester: { type: Array, default: () => [] },
    academicYearAverages: { type: Array, default: () => [] },
    earnedCreditsTotal: { type: Number, default: 0 },
    averageGpa: { type: Number, default: null },
    recentPasses: { type: Array, default: () => [] },
    companionInsights: { type: Array, default: () => [] },
    canUpdateFamilies: { type: Boolean, default: false },
});

const familyRows = ref([...props.families]);
const editing = ref(null);
const saving = ref(false);
const notice = ref({ text: '', type: 'info' });
const activeInsuranceTab = ref('medical');
const activeProfileSection = ref('basic');
const scheduleVisible = ref(true);
const gradeVisible = ref(true);
const latestAcademicYearAverage = computed(() => props.academicYearAverages?.[0] || null);
const averageDetailVisible = ref({});
const profileSections = [
    { key: 'basic', label: '基础档案', description: '基础、教育、住宿' },
    { key: 'academic', label: '学业表现', description: '排名、综测、课表、成绩' },
    { key: 'activity', label: '在校动态', description: '刷码、随行人员' },
    { key: 'support', label: '资助保障', description: '保险、资助、贷款' },
    { key: 'honor', label: '荣誉奖惩', description: '竞赛、干部、奖惩' },
    { key: 'family', label: '家庭联系', description: '家长联系人' },
];
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

function selectProfileSection(key) {
    activeProfileSection.value = key;
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

function scoreText(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return Number(value).toLocaleString('zh-CN', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function medicalCovered(record) {
    if (!record) {
        return false;
    }

    return Boolean(record.has_paid) && String(record.insurance_status || '').includes('参保');
}

function medicalStatusText(record) {
    if (!record) {
        return '未导入';
    }

    return medicalCovered(record) ? '已参保' : '未参保';
}

function safetyCovered(record) {
    return Boolean(record?.is_insured);
}

function safetyStatusText(record) {
    if (!record) {
        return '未导入';
    }

    return safetyCovered(record) ? '已参保' : '未参保';
}

function directionText(direction) {
    if (direction === 'in') {
        return '进校';
    }
    if (direction === 'out') {
        return '出校';
    }

    return direction || '-';
}

const latestPass = computed(() => props.recentPasses?.[0] || null);
const possibleFriendCount = computed(() => props.companionInsights.filter((item) => item.is_possible_friend).length);
const strongestCompanion = computed(() => props.companionInsights?.[0] || null);

function dateTimeText(value) {
    if (!value) {
        return '-';
    }

    return String(value).replace('T', ' ').replace(/\.\d+Z?$/, '').replace(/Z$/, '');
}

function passDatePart(value) {
    const text = dateTimeText(value);

    return text === '-' ? '-' : text.slice(0, 10);
}

function passTimePart(value) {
    const text = dateTimeText(value);

    return text === '-' ? '-' : text.slice(11, 19) || '-';
}

function directionTone(direction) {
    if (direction === 'in') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }
    if (direction === 'out') {
        return 'border-orange-200 bg-orange-50 text-orange-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600';
}

function companionTone(item) {
    return item?.is_possible_friend
        ? 'border-amber-200 bg-amber-50 text-amber-800'
        : 'border-slate-200 bg-slate-50 text-slate-600';
}

function studentStatusText(status) {
    return status === 'lost' ? '失联' : '正常';
}

function toFixedText(value) {
    const num = Number(value);

    return Number.isFinite(num) ? num.toFixed(2) : '-';
}

function averageDetailKey(item) {
    return item ? String(item.id || item.academic_year || '') : '';
}

function toggleAverageDetail(item) {
    const key = averageDetailKey(item);
    if (!key) {
        return;
    }

    averageDetailVisible.value = {
        ...averageDetailVisible.value,
        [key]: !averageDetailVisible.value[key],
    };
}

function isAverageDetailVisible(item) {
    return Boolean(averageDetailVisible.value[averageDetailKey(item)]);
}

function gpaText(value) {
    const num = Number(value);

    return Number.isFinite(num) ? num.toFixed(2) : '-';
}

function dateText(value) {
    if (!value) {
        return '-';
    }

    return String(value).slice(0, 10);
}

const weekDays = [
    { value: 1, label: '周一' },
    { value: 2, label: '周二' },
    { value: 3, label: '周三' },
    { value: 4, label: '周四' },
    { value: 5, label: '周五' },
    { value: 6, label: '周六' },
    { value: 7, label: '周日' },
];

const periodTimeMap = {
    1: '08:30-09:15',
    2: '09:15-10:00',
    3: '--:--:--',
    4: '--:--:--',
    5: '11:45-12:30',
    6: '--:--:--',
    7: '--:--:--',
    8: '--:--:--',
    9: '--:--:--',
    10: '--:--:--',
    11: '--:--:--',
    12: '--:--:--',
};

function weekItems(dayValue) {
    return props.weeklySchedule.filter((item) => Number(item.weekday) === Number(dayValue));
}

function weekRangeText(item) {
    const start = Number(item.week_start || 0);
    const end = Number(item.week_end || 0);

    if (!start) {
        return '不限周次';
    }

    if (start === end) {
        return `${start}周`;
    }

    return `${start}-${end}周`;
}

function normalizePeriod(value) {
    const period = Number(value || 0);

    return Number.isFinite(period) ? period : 0;
}

function periodTimeText(period) {
    return periodTimeMap[period] || '--:--:--';
}

const weeklyCalendar = computed(() => weekDays.map((day) => {
    const items = weekItems(day.value)
        .map((item) => {
            const start = Math.max(1, Math.min(12, normalizePeriod(item.period_start)));
            const endRaw = normalizePeriod(item.period_end) || start;
            const end = Math.max(start, Math.min(12, endRaw));

            return {
                ...item,
                _start: start,
                _end: end,
                _span: end - start + 1,
            };
        })
        .sort((a, b) => a._start - b._start || a._end - b._end);

    const periodCourses = new Map();
    items.forEach((item) => {
        for (let period = item._start; period <= item._end; period += 1) {
            if (!periodCourses.has(period)) {
                periodCourses.set(period, []);
            }
            periodCourses.get(period).push(item);
        }
    });

    const slots = [];
    for (let period = 1; period <= 12; period += 1) {
        const courses = (periodCourses.get(period) || []).sort((a, b) => a._start - b._start || b._span - a._span);
        if (courses.length > 0) {
            slots.push({
                period,
                type: 'course',
                primary: courses[0],
                isStart: courses[0]._start === period,
                extras: Math.max(0, courses.length - 1),
            });
            continue;
        }

        slots.push({ period, type: 'empty' });
    }

    return {
        ...day,
        slots,
    };
}));

const scheduleScrollKey = 'students.profile.schedule.scrollY';

function rememberScrollPosition() {
    sessionStorage.setItem(scheduleScrollKey, String(window.scrollY || 0));
}

onMounted(() => {
    const raw = sessionStorage.getItem(scheduleScrollKey);
    if (!raw) {
        return;
    }

    const y = Number(raw);
    sessionStorage.removeItem(scheduleScrollKey);

    if (Number.isFinite(y) && y > 0) {
        nextTick(() => window.scrollTo(0, y));
    }
});

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
    <main class="mx-auto max-w-[1800px] px-4 py-8 sm:px-6 lg:px-8">
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

        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <nav class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 shadow-sm lg:overflow-visible">
                    <div class="flex min-w-max gap-2 lg:min-w-0 lg:flex-col">
                        <button
                            v-for="section in profileSections"
                            :key="section.key"
                            type="button"
                            class="w-full rounded-md border px-4 py-3 text-left transition"
                            :class="activeProfileSection === section.key ? 'border-slate-900 bg-slate-50 text-slate-950 shadow-sm' : 'border-transparent bg-white text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-950'"
                            @click="selectProfileSection(section.key)"
                        >
                            <span class="block text-sm font-semibold">{{ section.label }}</span>
                            <span class="mt-1 block whitespace-nowrap text-xs text-slate-500 lg:whitespace-normal">{{ section.description }}</span>
                        </button>
                    </div>
                </nav>
            </aside>

            <div class="min-w-0">
        <section v-if="activeProfileSection === 'academic'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">学习排名</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ latestAcademicYearAverage?.academic_year || '暂无学年排名数据' }}
                    </p>
                </div>
                <button
                    v-if="latestAcademicYearAverage"
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    @click="toggleAverageDetail(latestAcademicYearAverage)"
                >
                    {{ isAverageDetailVisible(latestAcademicYearAverage) ? '收起计算过程' : '查看计算过程' }}
                </button>
            </div>

            <div v-if="latestAcademicYearAverage" class="space-y-3">
                <div class="grid gap-3 md:grid-cols-3">
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-500">学习平均成绩</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ scoreText(latestAcademicYearAverage.average_score) }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            计入 {{ latestAcademicYearAverage.course_count || 0 }} 门 / {{ scoreText(latestAcademicYearAverage.total_credits) }} 学分
                        </p>
                    </article>
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-500">班级排名</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">
                            {{ latestAcademicYearAverage.class_rank || '-' }}
                            <span class="text-base font-medium text-slate-500">/ {{ latestAcademicYearAverage.class_size || '-' }}</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">{{ latestAcademicYearAverage.class_name || props.student.bjmc || '-' }}</p>
                    </article>
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-500">专业排名</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">
                            {{ latestAcademicYearAverage.major_rank || '-' }}
                            <span class="text-base font-medium text-slate-500">/ {{ latestAcademicYearAverage.major_size || '-' }}</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">{{ latestAcademicYearAverage.major_code || '-' }}</p>
                    </article>
                </div>

                <div v-if="isAverageDetailVisible(latestAcademicYearAverage)" class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">学期</th>
                                <th class="px-3 py-2 text-left">课程</th>
                                <th class="px-3 py-2 text-left">原始成绩</th>
                                <th class="px-3 py-2 text-left">采用成绩</th>
                                <th class="px-3 py-2 text-left">学分</th>
                                <th class="px-3 py-2 text-left">加权分</th>
                                <th class="px-3 py-2 text-left">说明</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="course in latestAcademicYearAverage.calculation_courses || []" :key="`${course.semester}-${course.course_code}`">
                                <td class="px-3 py-2">{{ course.semester || '-' }}</td>
                                <td class="px-3 py-2">{{ course.course_name || course.course_code || '-' }}</td>
                                <td class="px-3 py-2">{{ course.original_score ?? '-' }}</td>
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ toFixedText(course.score) }}</td>
                                <td class="px-3 py-2">{{ toFixedText(course.credits) }}</td>
                                <td class="px-3 py-2">{{ toFixedText(course.weighted_score) }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ course.calculation_note || '-' }}</td>
                            </tr>
                            <tr v-if="!(latestAcademicYearAverage.calculation_courses || []).length">
                                <td colspan="7" class="px-3 py-6 text-center text-slate-500">暂无可展示的计算明细</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-else class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                暂无学习平均成绩和排名数据。
            </div>
        </section>

        <section v-if="activeProfileSection === 'academic'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-950">综测成绩</h2>
                <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">学年</th>
                            <th class="px-3 py-2 text-left">名次</th>
                            <th class="px-3 py-2 text-left">综合测评</th>
                            <th class="px-3 py-2 text-left">德育</th>
                            <th class="px-3 py-2 text-left">智育</th>
                            <th class="px-3 py-2 text-left">体育</th>
                            <th class="px-3 py-2 text-left">美育</th>
                            <th class="px-3 py-2 text-left">劳育</th>
                            <th class="px-3 py-2 text-left">学院 / 班级</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in props.comprehensiveAssessments" :key="item.id">
                            <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                            <td class="px-3 py-2">{{ item.rank || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">{{ scoreText(item.total_score) }}</span>
                            </td>
                            <td class="px-3 py-2">{{ scoreText(item.moral_score) }}</td>
                            <td class="px-3 py-2">{{ scoreText(item.intellectual_score) }}</td>
                            <td class="px-3 py-2">{{ scoreText(item.physical_score) }}</td>
                            <td class="px-3 py-2">{{ scoreText(item.aesthetic_score) }}</td>
                            <td class="px-3 py-2">{{ scoreText(item.labor_score) }}</td>
                            <td class="px-3 py-2">{{ item.college || '-' }} / {{ item.class_name || '-' }}</td>
                        </tr>
                        <tr v-if="!props.comprehensiveAssessments.length">
                            <td colspan="9" class="px-3 py-6 text-center text-slate-500">暂无综测成绩</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'academic'" id="schedule-panel" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">课表信息</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ props.semesterLabel || '暂无学期' }} / {{ props.weekLabel || `第${props.selectedWeek || 1}周` }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="scheduleVisible = !scheduleVisible">
                            {{ scheduleVisible ? '收起课表' : '展开课表' }}
                        </button>
                        <template v-if="scheduleVisible">
                            <a v-if="props.prevWeekUrl" :href="props.prevWeekUrl" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="rememberScrollPosition">上周</a>
                            <button v-else type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-400" disabled>上周</button>
                            <a v-if="props.nextWeekUrl" :href="props.nextWeekUrl" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="rememberScrollPosition">下周</a>
                            <button v-else type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-400" disabled>下周</button>
                        </template>
                    </div>
                </div>

                <div v-if="scheduleVisible" class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 grid grid-cols-[96px_repeat(7,minmax(0,1fr))] gap-2">
                        <div class="text-xs font-semibold text-slate-500">节次 / 时间</div>
                        <div v-for="day in weeklyCalendar" :key="`header-${day.value}`" class="text-sm font-semibold text-slate-900 text-center">{{ day.label }}</div>
                    </div>

                    <div class="grid grid-cols-[96px_repeat(7,minmax(0,1fr))] gap-2">
                        <div class="grid gap-1" style="grid-template-rows: repeat(12, 88px);">
                            <div v-for="period in 12" :key="`time-${period}`" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-600">
                                <p>第{{ period }}节</p>
                                <p>{{ periodTimeText(period) }}</p>
                            </div>
                        </div>

                        <div v-for="day in weeklyCalendar" :key="`day-${day.value}`" class="grid gap-1" style="grid-template-rows: repeat(12, 88px);">
                            <template v-for="slot in day.slots" :key="`${day.value}-${slot.period}`">
                                <article
                                    v-if="slot.type === 'course' && slot.isStart"
                                    class="rounded-md border border-sky-200 bg-sky-50 px-2 py-1.5 shadow-sm"
                                    :style="{ gridRow: `${slot.period} / span ${slot.primary._span}` }"
                                >
                                    <h4 class="text-xs font-semibold text-slate-900">{{ slot.primary.course_name || '-' }}</h4>
                                    <p class="mt-0.5 text-[11px] text-slate-600">{{ slot.primary.period_label || '-' }} / {{ weekRangeText(slot.primary) }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-600">教师：{{ slot.primary.teacher_name || '-' }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-600">地点：{{ slot.primary.location || '-' }}</p>
                                    <p v-if="slot.extras > 0" class="mt-0.5 text-[11px] text-amber-700">同节次另有 {{ slot.extras }} 门课</p>
                                </article>
                                <div
                                    v-else-if="slot.type === 'empty'"
                                    class="rounded-md border border-dashed border-slate-300 bg-white"
                                    :style="{ gridRow: `${slot.period} / span 1` }"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="activeProfileSection === 'academic'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">成绩信息</h2>
                        <p class="mt-1 text-sm text-slate-500">学业统计</p>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="gradeVisible = !gradeVisible">
                        {{ gradeVisible ? '收起成绩' : '展开成绩' }}
                    </button>
                </div>

                <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">已获得学分</p>
                        <p class="text-xl font-bold text-slate-950">{{ toFixedText(props.earnedCreditsTotal) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">平均绩点（GPA）</p>
                        <p class="text-xl font-bold text-slate-950">{{ gpaText(props.averageGpa) }}</p>
                    </div>
                </div>

                <div class="mb-4 overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">学年</th>
                                <th class="px-3 py-2 text-left">学习平均成绩</th>
                                <th class="px-3 py-2 text-left">计入学分</th>
                                <th class="px-3 py-2 text-left">课程数</th>
                                <th class="px-3 py-2 text-left">班级排名</th>
                                <th class="px-3 py-2 text-left">专业排名</th>
                                <th class="px-3 py-2 text-left">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="item in props.academicYearAverages" :key="item.id">
                                <tr>
                                    <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                                    <td class="px-3 py-2 font-semibold text-slate-900">{{ toFixedText(item.average_score) }}</td>
                                    <td class="px-3 py-2">{{ toFixedText(item.total_credits) }}</td>
                                    <td class="px-3 py-2">{{ item.course_count || 0 }}</td>
                                    <td class="px-3 py-2">{{ item.class_rank ? `${item.class_rank}/${item.class_size || '-'}` : '-' }}</td>
                                    <td class="px-3 py-2">{{ item.major_rank ? `${item.major_rank}/${item.major_size || '-'}` : '-' }}</td>
                                    <td class="px-3 py-2">
                                        <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="toggleAverageDetail(item)">
                                            {{ isAverageDetailVisible(item) ? '收起' : '查看计算过程' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="isAverageDetailVisible(item)">
                                    <td colspan="7" class="bg-slate-50 p-0">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-xs">
                                                <thead class="bg-slate-100 text-slate-600">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left">学期</th>
                                                        <th class="px-3 py-2 text-left">课程</th>
                                                        <th class="px-3 py-2 text-left">原始成绩</th>
                                                        <th class="px-3 py-2 text-left">采用成绩</th>
                                                        <th class="px-3 py-2 text-left">学分</th>
                                                        <th class="px-3 py-2 text-left">加权分</th>
                                                        <th class="px-3 py-2 text-left">考试性质</th>
                                                        <th class="px-3 py-2 text-left">说明</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200 bg-white">
                                                    <tr v-for="course in item.calculation_courses || []" :key="`${item.id}-${course.semester}-${course.course_code}`">
                                                        <td class="px-3 py-2">{{ course.semester || '-' }}</td>
                                                        <td class="px-3 py-2">{{ course.course_name || course.course_code || '-' }}</td>
                                                        <td class="px-3 py-2">{{ course.original_score ?? '-' }}</td>
                                                        <td class="px-3 py-2 font-semibold text-slate-900">{{ toFixedText(course.score) }}</td>
                                                        <td class="px-3 py-2">{{ toFixedText(course.credits) }}</td>
                                                        <td class="px-3 py-2">{{ toFixedText(course.weighted_score) }}</td>
                                                        <td class="px-3 py-2">{{ course.exam_type || '-' }}</td>
                                                        <td class="px-3 py-2 text-slate-600">{{ course.calculation_note || '-' }}</td>
                                                    </tr>
                                                    <tr v-if="!(item.calculation_courses || []).length">
                                                        <td colspan="8" class="px-3 py-5 text-center text-slate-500">暂无可展示的计算明细</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!props.academicYearAverages.length">
                                <td colspan="7" class="px-3 py-6 text-center text-slate-500">暂无学年学习平均成绩</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="gradeVisible" class="space-y-3">
                    <details v-for="semester in props.gradesBySemester" :key="semester.semester" class="rounded-lg border border-slate-200 bg-slate-50" open>
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-800">
                            {{ semester.semester_label }}（总绩点：{{ toFixedText(semester.total_grade_points) }} / 总学分：{{ toFixedText(semester.total_credits) }} / 已获学分：{{ toFixedText(semester.earned_credits) }}）
                        </summary>
                        <div class="overflow-x-auto border-t border-slate-200 bg-white">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left">课程代码</th>
                                        <th class="px-3 py-2 text-left">课程名称</th>
                                        <th class="px-3 py-2 text-left">成绩</th>
                                        <th class="px-3 py-2 text-left">绩点</th>
                                        <th class="px-3 py-2 text-left">学分</th>
                                        <th class="px-3 py-2 text-left">考试性质</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="grade in semester.items" :key="grade.id">
                                        <td class="px-3 py-2">{{ grade.kcbm || '-' }}</td>
                                        <td class="px-3 py-2">{{ grade.kcmc || '-' }}</td>
                                        <td class="px-3 py-2">{{ grade.cj || '-' }}</td>
                                        <td class="px-3 py-2">{{ toFixedText(grade.jd) }}</td>
                                        <td class="px-3 py-2">{{ toFixedText(grade.xf) }}</td>
                                        <td class="px-3 py-2">{{ grade.ksxz || '-' }}</td>
                                    </tr>
                                    <tr v-if="!semester.items.length">
                                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">暂无成绩记录</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <div v-if="!props.gradesBySemester.length" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                        暂无成绩数据
                    </div>
                </div>
            </section>

            <div class="space-y-6">

        <section v-if="activeProfileSection === 'basic'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold text-slate-950">基础信息</h2>
            <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><span class="text-slate-500">学号：</span>{{ props.student.xgh || '-' }}</div>
                <div><span class="text-slate-500">姓名：</span>{{ props.student.xm || '-' }}</div>
                <div><span class="text-slate-500">分院：</span>{{ props.student.dwmc || '-' }}</div>
                <div><span class="text-slate-500">班级：</span>{{ props.student.bjmc || '-' }}</div>
                <div><span class="text-slate-500">联系电话：</span>{{ props.student.yddh || '-' }}</div>
                <div><span class="text-slate-500">最近刷码：</span>{{ props.student.last_smsj || '-' }}</div>
                <div><span class="text-slate-500">状态：</span>{{ props.student.status || '-' }}</div>
                <div>
                    <span class="text-slate-500">{{ props.currentYear }}年度医保：</span>
                    <span
                        class="rounded px-2 py-1 text-xs font-semibold"
                        :class="medicalCovered(props.currentMedicalInsurance) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ medicalStatusText(props.currentMedicalInsurance) }}
                    </span>
                </div>
                <div>
                    <span class="text-slate-500">{{ props.currentYear }}年度学平险：</span>
                    <span
                        class="rounded px-2 py-1 text-xs font-semibold"
                        :class="safetyCovered(props.currentSafetyInsurance) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ safetyStatusText(props.currentSafetyInsurance) }}
                    </span>
                </div>
            </div>
        </section>

        <section v-if="activeProfileSection === 'basic'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold text-slate-950">大学前教育经历</h2>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">阶段</th>
                            <th class="px-3 py-2 text-left">开始时间</th>
                            <th class="px-3 py-2 text-left">结束时间</th>
                            <th class="px-3 py-2 text-left">学校</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in props.educationHistories" :key="item.id">
                            <td class="px-3 py-2">{{ item.qualifications || '-' }}</td>
                            <td class="px-3 py-2">{{ item.start_year || '-' }}</td>
                            <td class="px-3 py-2">{{ item.end_year || '-' }}</td>
                            <td class="px-3 py-2">{{ item.school_name || '-' }}</td>
                        </tr>
                        <tr v-if="!props.educationHistories.length">
                            <td colspan="4" class="px-3 py-6 text-center text-slate-500">暂无教育经历</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'basic'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold text-slate-950">住宿信息</h2>
            <div class="mb-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <span class="text-slate-500">宿舍号：</span>
                    <a v-if="props.dormitory?.ssh" class="text-sky-700 hover:underline" :href="`/students/dormitories/${encodeURIComponent(props.dormitory.ssh)}`">{{ props.dormitory.ssh }}</a>
                    <span v-else>-</span>
                </div>
                <div><span class="text-slate-500">床位号：</span>{{ props.dormitory?.ch || '-' }}</div>
                <div><span class="text-slate-500">寝室类型：</span>{{ props.dormitory?.qslx || '-' }}</div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">同宿舍人数</p>
                    <p class="text-xl font-bold text-slate-950">{{ props.dormitorySummary.roommate_total || 0 }}</p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                    <p class="text-xs text-rose-600">同宿舍失联人数</p>
                    <p class="text-xl font-bold text-rose-700">{{ props.dormitorySummary.lost_roommate_count || 0 }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs text-amber-700">同宿舍高风险人数</p>
                    <p class="text-xl font-bold text-amber-700">{{ props.dormitorySummary.high_risk_roommate_count || 0 }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-xs text-slate-500">宿舍总人数</p>
                    <p class="text-xl font-bold text-slate-950">{{ props.dormitorySummary.resident_total || 0 }}</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">学号</th>
                            <th class="px-3 py-2 text-left">姓名</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">专业</th>
                            <th class="px-3 py-2 text-left">班级</th>
                            <th class="px-3 py-2 text-left">床位</th>
                            <th class="px-3 py-2 text-left">最近刷码</th>
                            <th class="px-3 py-2 text-left">状态</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="mate in props.roommates" :key="mate.xh">
                            <td class="px-3 py-2">
                                <a class="text-sky-700 hover:underline" :href="`/students/profile/${encodeURIComponent(mate.xh)}`">{{ mate.xh }}</a>
                            </td>
                            <td class="px-3 py-2">
                                <a class="text-sky-700 hover:underline" :href="`/students/profile/${encodeURIComponent(mate.xh)}`">{{ mate.xm || '-' }}</a>
                            </td>
                            <td class="px-3 py-2">{{ mate.xy || '-' }}</td>
                            <td class="px-3 py-2">{{ mate.zy || '-' }}</td>
                            <td class="px-3 py-2">{{ mate.bj || '-' }}</td>
                            <td class="px-3 py-2">{{ mate.ch || '-' }}</td>
                            <td class="px-3 py-2">{{ mate.last_smsj || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="mate.status === 'lost' ? 'bg-rose-100 text-rose-700' : mate.is_high_risk ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                                    {{ studentStatusText(mate.status) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!props.roommates.length">
                            <td colspan="8" class="px-3 py-6 text-center text-slate-500">暂无舍友信息</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'support'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold text-slate-950">保险参保记录</h2>
                    <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                        <button
                            type="button"
                            class="min-w-24 rounded-md px-3 py-1.5 text-sm font-semibold transition"
                            :class="activeInsuranceTab === 'medical' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                            @click="activeInsuranceTab = 'medical'"
                        >
                            医保
                        </button>
                        <button
                            type="button"
                            class="min-w-24 rounded-md px-3 py-1.5 text-sm font-semibold transition"
                            :class="activeInsuranceTab === 'safety' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                            @click="activeInsuranceTab = 'safety'"
                        >
                            学平险
                        </button>
                    </div>
                </div>
                <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
            </div>

            <div v-if="activeInsuranceTab === 'medical'" class="mb-4 rounded-lg border px-4 py-3 text-sm" :class="medicalCovered(props.currentMedicalInsurance) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600'">
                {{ props.currentYear }}年度：{{ medicalStatusText(props.currentMedicalInsurance) }}
                <span v-if="props.currentMedicalInsurance">
                    ，参保状态：{{ props.currentMedicalInsurance.insurance_status || '-' }}，年度是否缴费：{{ props.currentMedicalInsurance.has_paid ? '是' : '否' }}
                </span>
            </div>
            <div v-else class="mb-4 rounded-lg border px-4 py-3 text-sm" :class="safetyCovered(props.currentSafetyInsurance) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600'">
                {{ props.currentYear }}年度：{{ safetyStatusText(props.currentSafetyInsurance) }}
            </div>

            <div v-if="activeInsuranceTab === 'medical'" class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">年度</th>
                            <th class="px-3 py-2 text-left">是否参保</th>
                            <th class="px-3 py-2 text-left">参保状态</th>
                            <th class="px-3 py-2 text-left">参保地</th>
                            <th class="px-3 py-2 text-left">参保日期</th>
                            <th class="px-3 py-2 text-left">缴费期间</th>
                            <th class="px-3 py-2 text-left">缴费类型</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in props.medicalInsurances" :key="item.id">
                            <td class="px-3 py-2">{{ item.annual_year || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="medicalCovered(item) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                    {{ medicalStatusText(item) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ item.insurance_status || '-' }}</td>
                            <td class="px-3 py-2">{{ item.insured_area || '-' }}</td>
                            <td class="px-3 py-2">{{ item.enrolled_on || '-' }}</td>
                            <td class="px-3 py-2">{{ item.payment_start_month || '-' }} - {{ item.payment_end_month || '-' }}</td>
                            <td class="px-3 py-2">{{ item.payment_type || '-' }}</td>
                        </tr>
                        <tr v-if="!props.medicalInsurances.length">
                            <td colspan="7" class="px-3 py-6 text-center text-slate-500">暂无医保参保记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">年度</th>
                            <th class="px-3 py-2 text-left">是否参保</th>
                            <th class="px-3 py-2 text-left">年级</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">专业</th>
                            <th class="px-3 py-2 text-left">班级</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in props.safetyInsurances" :key="item.id">
                            <td class="px-3 py-2">{{ item.annual_year || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="safetyCovered(item) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                    {{ safetyStatusText(item) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ item.grade || '-' }}</td>
                            <td class="px-3 py-2">{{ item.college || '-' }}</td>
                            <td class="px-3 py-2">{{ item.major || '-' }}</td>
                            <td class="px-3 py-2">{{ item.class_name || '-' }}</td>
                        </tr>
                        <tr v-if="!props.safetyInsurances.length">
                            <td colspan="6" class="px-3 py-6 text-center text-slate-500">暂无学平险参保记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'activity'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Campus activity</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-950">刷码动态</h2>
                </div>
                <div class="grid gap-2 sm:grid-cols-3 lg:w-[620px]">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-xs text-slate-500">最近一次</div>
                        <div class="mt-1 font-semibold text-slate-950">{{ dateTimeText(latestPass?.smsj) }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-xs text-slate-500">最近地点</div>
                        <div class="mt-1 truncate font-semibold text-slate-950">{{ latestPass?.smdd || '-' }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-xs text-slate-500">同行提示</div>
                        <div class="mt-1 font-semibold" :class="possibleFriendCount ? 'text-amber-700' : 'text-slate-950'">
                            {{ possibleFriendCount ? `${possibleFriendCount} 人可能为朋友` : '暂无高频同行' }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="props.recentPasses.length" class="overflow-hidden rounded-lg border border-slate-200">
                <div class="grid grid-cols-[116px_minmax(180px,1fr)_120px_minmax(180px,1fr)] bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 max-lg:hidden">
                    <span>时间</span>
                    <span>地点</span>
                    <span>方向</span>
                    <span>设备</span>
                </div>
                <div class="divide-y divide-slate-100">
                    <div
                        v-for="item in props.recentPasses"
                        :key="`${item.gh}-${item.smsj}-${item.device || 'none'}`"
                        class="grid gap-3 px-3 py-3 text-sm hover:bg-slate-50 lg:grid-cols-[116px_minmax(180px,1fr)_120px_minmax(180px,1fr)] lg:items-center"
                    >
                        <div>
                            <div class="font-semibold text-slate-950">{{ passTimePart(item.smsj) }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ passDatePart(item.smsj) }}</div>
                        </div>
                        <div class="font-medium text-slate-900">{{ item.smdd || '-' }}</div>
                        <div>
                            <span class="inline-flex min-w-14 justify-center rounded-full border px-2.5 py-1 text-xs font-semibold" :class="directionTone(item.crlx)">
                                {{ directionText(item.crlx) }}
                            </span>
                        </div>
                        <div class="break-all font-mono text-xs text-slate-600">{{ item.device || '-' }}</div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-lg border border-dashed border-slate-300 px-3 py-8 text-center text-sm text-slate-500">暂无刷码记录</div>
        </section>

        <section v-if="activeProfileSection === 'activity'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">10秒内同向随行人员</h2>
                    <p class="mt-1 text-sm text-slate-500">同地点、同进出方向累计超过 2 次时标记为可能为朋友。</p>
                </div>
                <div v-if="strongestCompanion" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    最高频：{{ strongestCompanion.xm || strongestCompanion.xgh || '-' }}，{{ strongestCompanion.companion_count }} 次
                </div>
            </div>
            <div v-if="props.companionInsights.length" class="overflow-hidden rounded-lg border border-slate-200">
                <div class="grid grid-cols-[minmax(160px,1fr)_120px_minmax(190px,1.1fr)_minmax(180px,1fr)_110px] bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 max-lg:hidden">
                    <span>人员</span>
                    <span>同行次数</span>
                    <span>最近出现</span>
                    <span>地点 / 方向</span>
                    <span>标记</span>
                </div>
                <div class="divide-y divide-slate-100">
                    <div
                        v-for="item in props.companionInsights"
                        :key="item.xgh"
                        class="grid gap-3 px-3 py-3 text-sm hover:bg-slate-50 lg:grid-cols-[minmax(160px,1fr)_120px_minmax(190px,1.1fr)_minmax(180px,1fr)_110px] lg:items-center"
                    >
                        <div>
                            <a v-if="item.xgh" class="font-semibold text-sky-700 hover:underline" :href="`/students/profile/${encodeURIComponent(item.xgh)}`">{{ item.xm || '-' }}</a>
                            <span v-else class="font-semibold text-slate-900">{{ item.xm || '-' }}</span>
                            <div class="mt-0.5 font-mono text-xs text-slate-500">{{ item.xgh || '-' }}</div>
                        </div>
                        <div>
                            <span class="inline-flex min-w-12 justify-center rounded-full bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white">{{ item.companion_count }}</span>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-950">{{ passTimePart(item.last_met_at) }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ passDatePart(item.last_met_at) }}</div>
                        </div>
                        <div class="text-slate-700">
                            <span class="font-medium text-slate-900">{{ item.last_smdd || '-' }}</span>
                            <span class="ml-2 inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold" :class="directionTone(item.last_crlx)">{{ directionText(item.last_crlx) }}</span>
                        </div>
                        <div>
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold" :class="companionTone(item)">
                                {{ item.is_possible_friend ? '可能为朋友' : '观察中' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-lg border border-dashed border-slate-300 px-3 py-8 text-center text-sm text-slate-500">暂无随行人员记录</div>
        </section>

        <section v-if="activeProfileSection === 'academic'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-950">体测成绩</h2>
                <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">学年</th>
                            <th class="px-3 py-2 text-left">总分</th>
                            <th class="px-3 py-2 text-left">性别</th>
                            <th class="px-3 py-2 text-left">院系</th>
                            <th class="px-3 py-2 text-left">班级</th>
                            <th class="px-3 py-2 text-left">备注</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in props.physicalTests" :key="item.id">
                            <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-800">{{ scoreText(item.score) }}</span>
                            </td>
                            <td class="px-3 py-2">{{ item.gender || '-' }}</td>
                            <td class="px-3 py-2">{{ item.college || '-' }}</td>
                            <td class="px-3 py-2">{{ item.class_name || '-' }}</td>
                            <td class="px-3 py-2">{{ item.remark || '-' }}</td>
                        </tr>
                        <tr v-if="!props.physicalTests.length">
                            <td colspan="6" class="px-3 py-6 text-center text-slate-500">暂无体测成绩</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'support'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-950">资助对象记录</h2>
                <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
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

        <section v-if="activeProfileSection === 'support'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-950">助学贷款记录</h2>
                <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
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

        <section v-if="activeProfileSection === 'honor'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-950">团学干部任职考核</h2>
                <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">学年</th>
                            <th class="px-3 py-2 text-left">学期</th>
                            <th class="px-3 py-2 text-left">机构</th>
                            <th class="px-3 py-2 text-left">部门</th>
                            <th class="px-3 py-2 text-left">职务</th>
                            <th class="px-3 py-2 text-left">总分</th>
                            <th class="px-3 py-2 text-left">等级</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in props.cadreAssessments" :key="item.id">
                            <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                            <td class="px-3 py-2">{{ item.semester ? `第${item.semester}学期` : '-' }}</td>
                            <td class="px-3 py-2">{{ item.organization || '-' }}</td>
                            <td class="px-3 py-2">{{ item.department || '-' }}</td>
                            <td class="px-3 py-2">{{ item.position || '-' }}</td>
                            <td class="px-3 py-2">{{ item.total_score ?? '-' }}</td>
                            <td class="px-3 py-2">{{ item.grade || '-' }}</td>
                        </tr>
                        <tr v-if="!props.cadreAssessments.length">
                            <td colspan="7" class="px-3 py-6 text-center text-slate-500">暂无团学干部任职考核记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'honor'" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold text-slate-950">科技竞赛获奖</h2>
                <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">时间</th>
                            <th class="px-3 py-2 text-left">荣誉名称</th>
                            <th class="px-3 py-2 text-left">年级</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">班级</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in props.technologyCompetitionAwards" :key="item.id">
                            <td class="px-3 py-2">{{ dateText(item.awarded_at) }}</td>
                            <td class="px-3 py-2">{{ item.award_name || '-' }}</td>
                            <td class="px-3 py-2">{{ item.grade || '-' }}</td>
                            <td class="px-3 py-2">{{ item.college || '-' }}</td>
                            <td class="px-3 py-2">{{ item.class_name || '-' }}</td>
                        </tr>
                        <tr v-if="!props.technologyCompetitionAwards.length">
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500">暂无科技竞赛获奖记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'honor'" class="mb-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-slate-950">奖励记录</h2>
                    <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
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
                    <a href="/student-imports" class="text-sm text-sky-700 hover:underline">导入</a>
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

        <section v-if="activeProfileSection === 'family'" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
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
            </div>
            </div>
        </div>

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
