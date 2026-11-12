<script setup>
import MobileProfileNavigation from '../components/MobileProfileNavigation.vue';
import { computed, nextTick, onMounted, ref } from 'vue';
import { formatDatePart as passDatePart, formatDateTime as dateTimeText, formatTimePart as passTimePart } from '../utils/dateTime';

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
    moralAssessments: { type: Array, default: () => [] },
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

function directionTone(direction) {
    if (direction === 'in') {
        return 'border-success-line bg-success-surface text-success';
    }
    if (direction === 'out') {
        return 'border-warning-line bg-warning-surface text-warning';
    }

    return 'border-line bg-surface-raised text-muted';
}

function companionTone(item) {
    return item?.is_possible_friend
        ? 'border-warning-line bg-warning-surface text-warning'
        : 'border-line bg-surface-raised text-muted';
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

function semesterText(value) {
    return value ? `第${value}学期` : '-';
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
    <main class="mx-auto max-w-[1800px] px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-6 rounded-lg border border-line bg-surface p-6 shadow-sm ui-card">
            <div class="flex flex-wrap items-center justify-between gap-3 mobile-wrap">
                <div>
                    <p class="text-sm text-muted">{{ props.student.student_category === 'graduated' ? '毕业生档案' : '学生主页' }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-main">{{ props.student.xm }}（{{ props.student.xgh }}）</h1>
                    <p class="mt-1 text-sm text-muted">
                        分院：{{ props.student.dwmc || '-' }} / 班级：{{ props.student.bjmc || '-' }} / 联系电话：{{ props.student.yddh || '-' }}
                    </p>
                </div>
                <a :href="props.student.student_category === 'graduated' ? '/graduates' : '/students'" class="rounded-lg border border-line px-4 py-2 text-sm text-muted hover:bg-surface-raised ui-button">{{ props.student.student_category === 'graduated' ? '返回毕业生' : '返回学生管理' }}</a>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)] mobile-stack">
            <MobileProfileNavigation :active-section="activeProfileSection">
                <nav class="overflow-x-auto rounded-lg border border-line bg-surface p-2 shadow-sm lg:overflow-visible">
                    <div class="flex min-w-max gap-2 lg:min-w-0 lg:flex-col mobile-wrap">
                        <button
                            v-for="section in profileSections"
                            :key="section.key"
                            type="button"
                            class="w-full rounded-md border px-4 py-3 text-left transition ui-button"
                            :class="activeProfileSection === section.key ? 'border-accent bg-selected text-main shadow-sm' : 'border-transparent bg-surface text-muted hover:border-line hover:bg-surface-raised hover:text-main'"
                            @click="selectProfileSection(section.key)"
                        >
                            <span class="block text-sm font-semibold">{{ section.label }}</span>
                            <span class="mt-1 block whitespace-nowrap text-xs text-muted lg:whitespace-normal">{{ section.description }}</span>
                        </button>
                    </div>
                </nav>
            </MobileProfileNavigation>

            <div class="min-w-0">
        <section v-if="activeProfileSection === 'academic'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 mobile-wrap">
                <div>
                    <h2 class="text-lg font-semibold text-main">学习排名</h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ latestAcademicYearAverage?.academic_year || '暂无学年排名数据' }}
                    </p>
                </div>
                <button
                    v-if="latestAcademicYearAverage"
                    type="button"
                    class="rounded-lg border border-line px-3 py-2 text-sm text-muted hover:bg-surface-raised ui-button"
                    @click="toggleAverageDetail(latestAcademicYearAverage)"
                >
                    {{ isAverageDetailVisible(latestAcademicYearAverage) ? '收起计算过程' : '查看计算过程' }}
                </button>
            </div>

            <div v-if="latestAcademicYearAverage" class="space-y-3">
                <div class="grid gap-3 md:grid-cols-3 mobile-stack">
                    <article class="rounded-lg border border-line bg-surface-raised p-4">
                        <p class="text-sm text-muted">学习平均成绩</p>
                        <p class="mt-2 text-2xl font-semibold text-main">{{ scoreText(latestAcademicYearAverage.average_score) }}</p>
                        <p class="mt-1 text-xs text-muted">
                            计入 {{ latestAcademicYearAverage.course_count || 0 }} 门 / {{ scoreText(latestAcademicYearAverage.total_credits) }} 学分
                        </p>
                    </article>
                    <article class="rounded-lg border border-line bg-surface-raised p-4">
                        <p class="text-sm text-muted">班级排名</p>
                        <p class="mt-2 text-2xl font-semibold text-main">
                            {{ latestAcademicYearAverage.class_rank || '-' }}
                            <span class="text-base font-medium text-muted">/ {{ latestAcademicYearAverage.class_size || '-' }}</span>
                        </p>
                        <p class="mt-1 text-xs text-muted">{{ latestAcademicYearAverage.class_name || props.student.bjmc || '-' }}</p>
                    </article>
                    <article class="rounded-lg border border-line bg-surface-raised p-4">
                        <p class="text-sm text-muted">专业排名</p>
                        <p class="mt-2 text-2xl font-semibold text-main">
                            {{ latestAcademicYearAverage.major_rank || '-' }}
                            <span class="text-base font-medium text-muted">/ {{ latestAcademicYearAverage.major_size || '-' }}</span>
                        </p>
                        <p class="mt-1 text-xs text-muted">{{ latestAcademicYearAverage.major_code || '-' }}</p>
                    </article>
                </div>

                <div v-if="isAverageDetailVisible(latestAcademicYearAverage)" class="overflow-x-auto rounded-lg border border-line">
                    <table class="min-w-full text-sm ui-table">
                        <thead class="bg-surface-raised text-muted">
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
                        <tbody class="divide-y divide-line">
                            <tr v-for="course in latestAcademicYearAverage.calculation_courses || []" :key="`${course.semester}-${course.course_code}`">
                                <td class="px-3 py-2">{{ course.semester || '-' }}</td>
                                <td class="px-3 py-2">{{ course.course_name || course.course_code || '-' }}</td>
                                <td class="px-3 py-2">{{ course.original_score ?? '-' }}</td>
                                <td class="px-3 py-2 font-semibold text-main">{{ toFixedText(course.score) }}</td>
                                <td class="px-3 py-2">{{ toFixedText(course.credits) }}</td>
                                <td class="px-3 py-2">{{ toFixedText(course.weighted_score) }}</td>
                                <td class="px-3 py-2 text-muted">{{ course.calculation_note || '-' }}</td>
                            </tr>
                            <tr v-if="!(latestAcademicYearAverage.calculation_courses || []).length">
                                <td colspan="7" class="px-3 py-6 text-center text-muted">暂无可展示的计算明细</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-else class="rounded-lg border border-dashed border-line bg-surface-raised px-4 py-5 text-sm text-muted">
                暂无学习平均成绩和排名数据。
            </div>
        </section>

        <section v-if="activeProfileSection === 'academic'" class="mb-6 grid gap-6 xl:grid-cols-2 mobile-stack">
            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
                <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                    <h2 class="text-lg font-semibold text-main">综测成绩</h2>
                    <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
                </div>
                <div class="overflow-x-auto rounded-lg border border-line">
                    <table class="min-w-full text-sm ui-table">
                        <thead class="bg-surface-raised text-muted">
                            <tr>
                                <th class="px-3 py-2 text-left">学年</th>
                                <th class="px-3 py-2 text-left">名次</th>
                                <th class="px-3 py-2 text-left">综合测评</th>
                                <th class="px-3 py-2 text-left">德育</th>
                                <th class="px-3 py-2 text-left">智育</th>
                                <th class="px-3 py-2 text-left">体育</th>
                                <th class="px-3 py-2 text-left">美育</th>
                                <th class="px-3 py-2 text-left">劳育</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="item in props.comprehensiveAssessments" :key="item.id">
                                <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                                <td class="px-3 py-2">{{ item.rank || '-' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded bg-success-surface px-2 py-1 text-xs font-semibold text-success">{{ scoreText(item.total_score) }}</span>
                                </td>
                                <td class="px-3 py-2">{{ scoreText(item.moral_score) }}</td>
                                <td class="px-3 py-2">{{ scoreText(item.intellectual_score) }}</td>
                                <td class="px-3 py-2">{{ scoreText(item.physical_score) }}</td>
                                <td class="px-3 py-2">{{ scoreText(item.aesthetic_score) }}</td>
                                <td class="px-3 py-2">{{ scoreText(item.labor_score) }}</td>
                            </tr>
                            <tr v-if="!props.comprehensiveAssessments.length">
                                <td colspan="8" class="px-3 py-6 text-center text-muted">暂无综测成绩</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
                <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                    <div>
                        <h2 class="text-lg font-semibold text-main">学期德育分</h2>
                        <p class="mt-1 text-sm text-muted">按学年学期展示德育量化考核。</p>
                    </div>
                    <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
                </div>
                <div class="overflow-x-auto rounded-lg border border-line">
                    <table class="min-w-full text-sm ui-table">
                        <thead class="bg-surface-raised text-muted">
                            <tr>
                                <th class="px-3 py-2 text-left">学年学期</th>
                                <th class="px-3 py-2 text-left">名次</th>
                                <th class="px-3 py-2 text-left">总分</th>
                                <th class="px-3 py-2 text-left">基础</th>
                                <th class="px-3 py-2 text-left">加分</th>
                                <th class="px-3 py-2 text-left">减分</th>
                                <th class="px-3 py-2 text-left">学院 / 班级</th>
                                <th class="px-3 py-2 text-left">备注</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="item in props.moralAssessments" :key="item.id">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-main">{{ item.academic_year || '-' }}</div>
                                    <div class="mt-0.5 text-xs text-muted">{{ semesterText(item.semester) }}</div>
                                </td>
                                <td class="px-3 py-2">{{ item.rank || '-' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded bg-warning-surface px-2 py-1 text-xs font-semibold text-warning">{{ scoreText(item.total_score) }}</span>
                                </td>
                                <td class="px-3 py-2">{{ scoreText(item.base_score) }}</td>
                                <td class="px-3 py-2">{{ scoreText(item.bonus_score) }}</td>
                                <td class="px-3 py-2">{{ scoreText(item.deduction_score) }}</td>
                                <td class="px-3 py-2">{{ item.college || '-' }} / {{ item.class_name || '-' }}</td>
                                <td class="px-3 py-2">{{ item.remark || '-' }}</td>
                            </tr>
                            <tr v-if="!props.moralAssessments.length">
                                <td colspan="8" class="px-3 py-6 text-center text-muted">暂无学期德育分</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section v-if="activeProfileSection === 'academic'" id="schedule-panel" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 mobile-wrap">
                    <div>
                        <h2 class="text-lg font-semibold text-main">课表信息</h2>
                        <p class="mt-1 text-sm text-muted">{{ props.semesterLabel || '暂无学期' }} / {{ props.weekLabel || `第${props.selectedWeek || 1}周` }}</p>
                    </div>
                    <div class="flex items-center gap-2 mobile-wrap">
                        <button type="button" class="rounded-lg border border-line px-3 py-2 text-sm text-muted hover:bg-surface-raised ui-button" @click="scheduleVisible = !scheduleVisible">
                            {{ scheduleVisible ? '收起课表' : '展开课表' }}
                        </button>
                        <template v-if="scheduleVisible">
                            <a v-if="props.prevWeekUrl" :href="props.prevWeekUrl" class="rounded-lg border border-line px-3 py-2 text-sm text-muted hover:bg-surface-raised ui-button" @click="rememberScrollPosition">上周</a>
                            <button v-else type="button" class="rounded-lg border border-line px-3 py-2 text-sm text-muted ui-button" disabled>上周</button>
                            <a v-if="props.nextWeekUrl" :href="props.nextWeekUrl" class="rounded-lg border border-line px-3 py-2 text-sm text-muted hover:bg-surface-raised ui-button" @click="rememberScrollPosition">下周</a>
                            <button v-else type="button" class="rounded-lg border border-line px-3 py-2 text-sm text-muted ui-button" disabled>下周</button>
                        </template>
                    </div>
                </div>

                <div v-if="scheduleVisible" class="mobile-calendar overflow-x-auto rounded-xl border border-line bg-surface-raised p-3">
                    <div class="mb-2 grid grid-cols-[96px_repeat(7,minmax(0,1fr))] gap-2">
                        <div class="text-xs font-semibold text-muted">节次 / 时间</div>
                        <div v-for="day in weeklyCalendar" :key="`header-${day.value}`" class="text-sm font-semibold text-main text-center">{{ day.label }}</div>
                    </div>

                    <div class="grid grid-cols-[96px_repeat(7,minmax(0,1fr))] gap-2">
                        <div class="grid gap-1 mobile-stack" style="grid-template-rows: repeat(12, 88px);">
                            <div v-for="period in 12" :key="`time-${period}`" class="rounded-md border border-line bg-surface px-2 py-1 text-[11px] text-muted">
                                <p>第{{ period }}节</p>
                                <p>{{ periodTimeText(period) }}</p>
                            </div>
                        </div>

                        <div v-for="day in weeklyCalendar" :key="`day-${day.value}`" class="grid gap-1 mobile-stack" style="grid-template-rows: repeat(12, 88px);">
                            <template v-for="slot in day.slots" :key="`${day.value}-${slot.period}`">
                                <article
                                    v-if="slot.type === 'course' && slot.isStart"
                                    class="rounded-md border border-info-line bg-info-surface px-2 py-1.5 shadow-sm"
                                    :style="{ gridRow: `${slot.period} / span ${slot.primary._span}` }"
                                >
                                    <h4 class="text-xs font-semibold text-main">{{ slot.primary.course_name || '-' }}</h4>
                                    <p class="mt-0.5 text-[11px] text-muted">{{ slot.primary.period_label || '-' }} / {{ weekRangeText(slot.primary) }}</p>
                                    <p class="mt-0.5 text-[11px] text-muted">教师：{{ slot.primary.teacher_name || '-' }}</p>
                                    <p class="mt-0.5 text-[11px] text-muted">地点：{{ slot.primary.location || '-' }}</p>
                                    <p v-if="slot.extras > 0" class="mt-0.5 text-[11px] text-warning">同节次另有 {{ slot.extras }} 门课</p>
                                </article>
                                <div
                                    v-else-if="slot.type === 'empty'"
                                    class="rounded-md border border-dashed border-line bg-surface"
                                    :style="{ gridRow: `${slot.period} / span 1` }"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="activeProfileSection === 'academic'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 mobile-wrap">
                    <div>
                        <h2 class="text-lg font-semibold text-main">成绩信息</h2>
                        <p class="mt-1 text-sm text-muted">学业统计</p>
                    </div>
                    <button type="button" class="rounded-lg border border-line px-3 py-2 text-sm text-muted hover:bg-surface-raised ui-button" @click="gradeVisible = !gradeVisible">
                        {{ gradeVisible ? '收起成绩' : '展开成绩' }}
                    </button>
                </div>

                <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 mobile-stack">
                    <div class="rounded-xl border border-line bg-surface-raised p-3">
                        <p class="text-xs text-muted">已获得学分</p>
                        <p class="text-xl font-bold text-main">{{ toFixedText(props.earnedCreditsTotal) }}</p>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-raised p-3">
                        <p class="text-xs text-muted">平均绩点（GPA）</p>
                        <p class="text-xl font-bold text-main">{{ gpaText(props.averageGpa) }}</p>
                    </div>
                </div>

                <div class="mb-4 overflow-x-auto rounded-lg border border-line">
                    <table class="min-w-full text-sm ui-table">
                        <thead class="bg-surface-raised text-muted">
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
                        <tbody class="divide-y divide-line">
                            <template v-for="item in props.academicYearAverages" :key="item.id">
                                <tr>
                                    <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                                    <td class="px-3 py-2 font-semibold text-main">{{ toFixedText(item.average_score) }}</td>
                                    <td class="px-3 py-2">{{ toFixedText(item.total_credits) }}</td>
                                    <td class="px-3 py-2">{{ item.course_count || 0 }}</td>
                                    <td class="px-3 py-2">{{ item.class_rank ? `${item.class_rank}/${item.class_size || '-'}` : '-' }}</td>
                                    <td class="px-3 py-2">{{ item.major_rank ? `${item.major_rank}/${item.major_size || '-'}` : '-' }}</td>
                                    <td class="px-3 py-2">
                                        <button type="button" class="rounded border border-line px-2 py-1 text-xs text-muted hover:bg-surface-raised ui-button" @click="toggleAverageDetail(item)">
                                            {{ isAverageDetailVisible(item) ? '收起' : '查看计算过程' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="isAverageDetailVisible(item)">
                                    <td colspan="7" class="bg-surface-raised p-0">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-xs ui-table">
                                                <thead class="bg-surface-raised text-muted">
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
                                                <tbody class="divide-y divide-line bg-surface">
                                                    <tr v-for="course in item.calculation_courses || []" :key="`${item.id}-${course.semester}-${course.course_code}`">
                                                        <td class="px-3 py-2">{{ course.semester || '-' }}</td>
                                                        <td class="px-3 py-2">{{ course.course_name || course.course_code || '-' }}</td>
                                                        <td class="px-3 py-2">{{ course.original_score ?? '-' }}</td>
                                                        <td class="px-3 py-2 font-semibold text-main">{{ toFixedText(course.score) }}</td>
                                                        <td class="px-3 py-2">{{ toFixedText(course.credits) }}</td>
                                                        <td class="px-3 py-2">{{ toFixedText(course.weighted_score) }}</td>
                                                        <td class="px-3 py-2">{{ course.exam_type || '-' }}</td>
                                                        <td class="px-3 py-2 text-muted">{{ course.calculation_note || '-' }}</td>
                                                    </tr>
                                                    <tr v-if="!(item.calculation_courses || []).length">
                                                        <td colspan="8" class="px-3 py-5 text-center text-muted">暂无可展示的计算明细</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!props.academicYearAverages.length">
                                <td colspan="7" class="px-3 py-6 text-center text-muted">暂无学年学习平均成绩</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="gradeVisible" class="space-y-3">
                    <details v-for="semester in props.gradesBySemester" :key="semester.semester" class="rounded-lg border border-line bg-surface-raised" open>
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-main">
                            {{ semester.semester_label }}（总绩点：{{ toFixedText(semester.total_grade_points) }} / 总学分：{{ toFixedText(semester.total_credits) }} / 已获学分：{{ toFixedText(semester.earned_credits) }}）
                        </summary>
                        <div class="overflow-x-auto border-t border-line bg-surface">
                            <table class="min-w-full text-sm ui-table">
                                <thead class="bg-surface-raised text-muted">
                                    <tr>
                                        <th class="px-3 py-2 text-left">课程代码</th>
                                        <th class="px-3 py-2 text-left">课程名称</th>
                                        <th class="px-3 py-2 text-left">成绩</th>
                                        <th class="px-3 py-2 text-left">绩点</th>
                                        <th class="px-3 py-2 text-left">学分</th>
                                        <th class="px-3 py-2 text-left">考试性质</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr v-for="grade in semester.items" :key="grade.id">
                                        <td class="px-3 py-2">{{ grade.kcbm || '-' }}</td>
                                        <td class="px-3 py-2">{{ grade.kcmc || '-' }}</td>
                                        <td class="px-3 py-2">{{ grade.cj || '-' }}</td>
                                        <td class="px-3 py-2">{{ toFixedText(grade.jd) }}</td>
                                        <td class="px-3 py-2">{{ toFixedText(grade.xf) }}</td>
                                        <td class="px-3 py-2">{{ grade.ksxz || '-' }}</td>
                                    </tr>
                                    <tr v-if="!semester.items.length">
                                        <td colspan="6" class="px-3 py-6 text-center text-muted">暂无成绩记录</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <div v-if="!props.gradesBySemester.length" class="rounded-lg border border-dashed border-line bg-surface-raised px-3 py-6 text-center text-sm text-muted">
                        暂无成绩数据
                    </div>
                </div>
            </section>

            <div class="space-y-6">

        <section v-if="activeProfileSection === 'basic'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <h2 class="mb-3 text-lg font-semibold text-main">基础信息</h2>
            <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3 mobile-stack">
                <div><span class="text-muted">学号：</span>{{ props.student.xgh || '-' }}</div>
                <div><span class="text-muted">姓名：</span>{{ props.student.xm || '-' }}</div>
                <div><span class="text-muted">分院：</span>{{ props.student.dwmc || '-' }}</div>
                <div><span class="text-muted">班级：</span>{{ props.student.bjmc || '-' }}</div>
                <div><span class="text-muted">联系电话：</span>{{ props.student.yddh || '-' }}</div>
                <div><span class="text-muted">最近刷码：</span><time class="font-medium tabular-nums text-main" :datetime="props.student.last_smsj || undefined">{{ dateTimeText(props.student.last_smsj) }}</time></div>
                <div><span class="text-muted">状态：</span>{{ props.student.status || '-' }}</div>
                <div>
                    <span class="text-muted">{{ props.currentYear }}年度医保：</span>
                    <span
                        class="rounded px-2 py-1 text-xs font-semibold"
                        :class="medicalCovered(props.currentMedicalInsurance) ? 'bg-success-surface text-success' : 'bg-surface-raised text-muted'"
                    >
                        {{ medicalStatusText(props.currentMedicalInsurance) }}
                    </span>
                </div>
                <div>
                    <span class="text-muted">{{ props.currentYear }}年度学平险：</span>
                    <span
                        class="rounded px-2 py-1 text-xs font-semibold"
                        :class="safetyCovered(props.currentSafetyInsurance) ? 'bg-success-surface text-success' : 'bg-surface-raised text-muted'"
                    >
                        {{ safetyStatusText(props.currentSafetyInsurance) }}
                    </span>
                </div>
            </div>
        </section>

        <section v-if="activeProfileSection === 'basic'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <h2 class="mb-3 text-lg font-semibold text-main">大学前教育经历</h2>
            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">阶段</th>
                            <th class="px-3 py-2 text-left">开始时间</th>
                            <th class="px-3 py-2 text-left">结束时间</th>
                            <th class="px-3 py-2 text-left">学校</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="item in props.educationHistories" :key="item.id">
                            <td class="px-3 py-2">{{ item.qualifications || '-' }}</td>
                            <td class="px-3 py-2">{{ item.start_year || '-' }}</td>
                            <td class="px-3 py-2">{{ item.end_year || '-' }}</td>
                            <td class="px-3 py-2">{{ item.school_name || '-' }}</td>
                        </tr>
                        <tr v-if="!props.educationHistories.length">
                            <td colspan="4" class="px-3 py-6 text-center text-muted">暂无教育经历</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'basic'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <h2 class="mb-3 text-lg font-semibold text-main">住宿信息</h2>
            <div class="mb-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3 mobile-stack">
                <div>
                    <span class="text-muted">宿舍号：</span>
                    <a v-if="props.dormitory?.ssh" class="text-info hover:underline" :href="`/students/dormitories/${encodeURIComponent(props.dormitory.ssh)}`">{{ props.dormitory.ssh }}</a>
                    <span v-else>-</span>
                </div>
                <div><span class="text-muted">床位号：</span>{{ props.dormitory?.ch || '-' }}</div>
                <div><span class="text-muted">寝室类型：</span>{{ props.dormitory?.qslx || '-' }}</div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-4 mobile-stack">
                <div class="rounded-xl border border-line bg-surface-raised p-3">
                    <p class="text-xs text-muted">同宿舍人数</p>
                    <p class="text-xl font-bold text-main">{{ props.dormitorySummary.roommate_total || 0 }}</p>
                </div>
                <div class="rounded-xl border border-danger-line bg-danger-surface p-3">
                    <p class="text-xs text-danger">同宿舍失联人数</p>
                    <p class="text-xl font-bold text-danger">{{ props.dormitorySummary.lost_roommate_count || 0 }}</p>
                </div>
                <div class="rounded-xl border border-warning-line bg-warning-surface p-3">
                    <p class="text-xs text-warning">同宿舍高风险人数</p>
                    <p class="text-xl font-bold text-warning">{{ props.dormitorySummary.high_risk_roommate_count || 0 }}</p>
                </div>
                <div class="rounded-xl border border-line bg-surface p-3">
                    <p class="text-xs text-muted">宿舍总人数</p>
                    <p class="text-xl font-bold text-main">{{ props.dormitorySummary.resident_total || 0 }}</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
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
                    <tbody class="divide-y divide-line">
                        <tr v-for="mate in props.roommates" :key="mate.xh">
                            <td class="px-3 py-2">
                                <a class="text-info hover:underline" :href="`/students/profile/${encodeURIComponent(mate.xh)}`">{{ mate.xh }}</a>
                            </td>
                            <td class="px-3 py-2">
                                <a class="text-info hover:underline" :href="`/students/profile/${encodeURIComponent(mate.xh)}`">{{ mate.xm || '-' }}</a>
                            </td>
                            <td class="px-3 py-2">{{ mate.xy || '-' }}</td>
                            <td class="px-3 py-2">{{ mate.zy || '-' }}</td>
                            <td class="px-3 py-2">{{ mate.bj || '-' }}</td>
                            <td class="px-3 py-2">{{ mate.ch || '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums">
                                <time v-if="mate.last_smsj" :datetime="mate.last_smsj">
                                    <span class="block font-medium text-main">{{ passDatePart(mate.last_smsj) }}</span>
                                    <span class="block text-xs text-muted">{{ passTimePart(mate.last_smsj) }}</span>
                                </time>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="mate.status === 'lost' ? 'bg-danger-surface text-danger' : mate.is_high_risk ? 'bg-warning-surface text-warning' : 'bg-success-surface text-success'">
                                    {{ studentStatusText(mate.status) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!props.roommates.length">
                            <td colspan="8" class="px-3 py-6 text-center text-muted">暂无舍友信息</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'support'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                <div class="flex flex-wrap items-center gap-3 mobile-wrap">
                    <h2 class="text-lg font-semibold text-main">保险参保记录</h2>
                    <div class="inline-flex rounded-lg border border-line bg-surface-raised p-1">
                        <button
                            type="button"
                            class="min-w-24 rounded-md px-3 py-1.5 text-sm font-semibold transition ui-button"
                            :class="activeInsuranceTab === 'medical' ? 'bg-surface text-main shadow-sm' : 'text-muted hover:text-main'"
                            @click="activeInsuranceTab = 'medical'"
                        >
                            医保
                        </button>
                        <button
                            type="button"
                            class="min-w-24 rounded-md px-3 py-1.5 text-sm font-semibold transition ui-button"
                            :class="activeInsuranceTab === 'safety' ? 'bg-surface text-main shadow-sm' : 'text-muted hover:text-main'"
                            @click="activeInsuranceTab = 'safety'"
                        >
                            学平险
                        </button>
                    </div>
                </div>
                <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
            </div>

            <div v-if="activeInsuranceTab === 'medical'" class="mb-4 rounded-lg border px-4 py-3 text-sm" :class="medicalCovered(props.currentMedicalInsurance) ? 'border-success-line bg-success-surface text-success' : 'border-line bg-surface-raised text-muted'">
                {{ props.currentYear }}年度：{{ medicalStatusText(props.currentMedicalInsurance) }}
                <span v-if="props.currentMedicalInsurance">
                    ，参保状态：{{ props.currentMedicalInsurance.insurance_status || '-' }}，年度是否缴费：{{ props.currentMedicalInsurance.has_paid ? '是' : '否' }}
                </span>
            </div>
            <div v-else class="mb-4 rounded-lg border px-4 py-3 text-sm" :class="safetyCovered(props.currentSafetyInsurance) ? 'border-success-line bg-success-surface text-success' : 'border-line bg-surface-raised text-muted'">
                {{ props.currentYear }}年度：{{ safetyStatusText(props.currentSafetyInsurance) }}
            </div>

            <div v-if="activeInsuranceTab === 'medical'" class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
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
                    <tbody class="divide-y divide-line">
                        <tr v-for="item in props.medicalInsurances" :key="item.id">
                            <td class="px-3 py-2">{{ item.annual_year || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="medicalCovered(item) ? 'bg-success-surface text-success' : 'bg-surface-raised text-muted'">
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
                            <td colspan="7" class="px-3 py-6 text-center text-muted">暂无医保参保记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">年度</th>
                            <th class="px-3 py-2 text-left">是否参保</th>
                            <th class="px-3 py-2 text-left">年级</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">专业</th>
                            <th class="px-3 py-2 text-left">班级</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="item in props.safetyInsurances" :key="item.id">
                            <td class="px-3 py-2">{{ item.annual_year || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="safetyCovered(item) ? 'bg-success-surface text-success' : 'bg-surface-raised text-muted'">
                                    {{ safetyStatusText(item) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ item.grade || '-' }}</td>
                            <td class="px-3 py-2">{{ item.college || '-' }}</td>
                            <td class="px-3 py-2">{{ item.major || '-' }}</td>
                            <td class="px-3 py-2">{{ item.class_name || '-' }}</td>
                        </tr>
                        <tr v-if="!props.safetyInsurances.length">
                            <td colspan="6" class="px-3 py-6 text-center text-muted">暂无学平险参保记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'activity'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">Campus activity</p>
                    <h2 class="mt-1 text-lg font-semibold text-main">刷码动态</h2>
                </div>
                <div class="grid gap-2 sm:grid-cols-3 lg:w-[620px] mobile-stack">
                    <div class="rounded-lg border border-line bg-surface-raised px-3 py-2">
                        <div class="text-xs text-muted">最近一次</div>
                        <div class="mt-1 font-semibold text-main">{{ dateTimeText(latestPass?.smsj) }}</div>
                    </div>
                    <div class="rounded-lg border border-line bg-surface-raised px-3 py-2">
                        <div class="text-xs text-muted">最近地点</div>
                        <div class="mt-1 truncate font-semibold text-main">{{ latestPass?.smdd || '-' }}</div>
                    </div>
                    <div class="rounded-lg border border-line bg-surface-raised px-3 py-2">
                        <div class="text-xs text-muted">同行提示</div>
                        <div class="mt-1 font-semibold" :class="possibleFriendCount ? 'text-warning' : 'text-main'">
                            {{ possibleFriendCount ? `${possibleFriendCount} 人可能为朋友` : '暂无高频同行' }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="props.recentPasses.length" class="overflow-hidden rounded-lg border border-line">
                <div class="grid grid-cols-[116px_minmax(180px,1fr)_120px_minmax(180px,1fr)] bg-surface-raised px-3 py-2 text-xs font-semibold text-muted max-lg:hidden mobile-stack">
                    <span>时间</span>
                    <span>地点</span>
                    <span>方向</span>
                    <span>设备</span>
                </div>
                <div class="divide-y divide-line">
                    <div
                        v-for="item in props.recentPasses"
                        :key="`${item.gh}-${item.smsj}-${item.device || 'none'}`"
                        class="grid gap-3 px-3 py-3 text-sm hover:bg-surface-raised lg:grid-cols-[116px_minmax(180px,1fr)_120px_minmax(180px,1fr)] lg:items-center mobile-stack"
                    >
                        <div>
                            <div class="font-semibold text-main">{{ passTimePart(item.smsj) }}</div>
                            <div class="mt-0.5 text-xs text-muted">{{ passDatePart(item.smsj) }}</div>
                        </div>
                        <div class="font-medium text-main">{{ item.smdd || '-' }}</div>
                        <div>
                            <span class="inline-flex min-w-14 justify-center rounded-full border px-2.5 py-1 text-xs font-semibold" :class="directionTone(item.crlx)">
                                {{ directionText(item.crlx) }}
                            </span>
                        </div>
                        <div class="break-all font-mono text-xs text-muted">{{ item.device || '-' }}</div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-lg border border-dashed border-line px-3 py-8 text-center text-sm text-muted">暂无刷码记录</div>
        </section>

        <section v-if="activeProfileSection === 'activity'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between mobile-wrap">
                <div>
                    <h2 class="text-lg font-semibold text-main">10秒内同向随行人员</h2>
                    <p class="mt-1 text-sm text-muted">同地点、同进出方向累计超过 2 次时标记为可能为朋友。</p>
                </div>
                <div v-if="strongestCompanion" class="rounded-lg border border-warning-line bg-warning-surface px-3 py-2 text-sm text-warning">
                    最高频：{{ strongestCompanion.xm || strongestCompanion.xgh || '-' }}，{{ strongestCompanion.companion_count }} 次
                </div>
            </div>
            <div v-if="props.companionInsights.length" class="overflow-hidden rounded-lg border border-line">
                <div class="grid grid-cols-[minmax(160px,1fr)_120px_minmax(190px,1.1fr)_minmax(180px,1fr)_110px] bg-surface-raised px-3 py-2 text-xs font-semibold text-muted max-lg:hidden mobile-stack">
                    <span>人员</span>
                    <span>同行次数</span>
                    <span>最近出现</span>
                    <span>地点 / 方向</span>
                    <span>标记</span>
                </div>
                <div class="divide-y divide-line">
                    <div
                        v-for="item in props.companionInsights"
                        :key="item.xgh"
                        class="grid gap-3 px-3 py-3 text-sm hover:bg-surface-raised lg:grid-cols-[minmax(160px,1fr)_120px_minmax(190px,1.1fr)_minmax(180px,1fr)_110px] lg:items-center mobile-stack"
                    >
                        <div>
                            <a v-if="item.xgh" class="font-semibold text-info hover:underline" :href="`/students/profile/${encodeURIComponent(item.xgh)}`">{{ item.xm || '-' }}</a>
                            <span v-else class="font-semibold text-main">{{ item.xm || '-' }}</span>
                            <div class="mt-0.5 font-mono text-xs text-muted">{{ item.xgh || '-' }}</div>
                        </div>
                        <div>
                            <span class="inline-flex min-w-12 justify-center rounded-full bg-accent px-2.5 py-1 text-xs font-semibold text-on-accent">{{ item.companion_count }}</span>
                        </div>
                        <div>
                            <div class="font-semibold text-main">{{ passTimePart(item.last_met_at) }}</div>
                            <div class="mt-0.5 text-xs text-muted">{{ passDatePart(item.last_met_at) }}</div>
                        </div>
                        <div class="text-muted">
                            <span class="font-medium text-main">{{ item.last_smdd || '-' }}</span>
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
            <div v-else class="rounded-lg border border-dashed border-line px-3 py-8 text-center text-sm text-muted">暂无随行人员记录</div>
        </section>

        <section v-if="activeProfileSection === 'academic'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                <h2 class="text-lg font-semibold text-main">体测成绩</h2>
                <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">学年</th>
                            <th class="px-3 py-2 text-left">总分</th>
                            <th class="px-3 py-2 text-left">性别</th>
                            <th class="px-3 py-2 text-left">院系</th>
                            <th class="px-3 py-2 text-left">班级</th>
                            <th class="px-3 py-2 text-left">备注</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="item in props.physicalTests" :key="item.id">
                            <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-info-surface px-2 py-1 text-xs font-semibold text-info">{{ scoreText(item.score) }}</span>
                            </td>
                            <td class="px-3 py-2">{{ item.gender || '-' }}</td>
                            <td class="px-3 py-2">{{ item.college || '-' }}</td>
                            <td class="px-3 py-2">{{ item.class_name || '-' }}</td>
                            <td class="px-3 py-2">{{ item.remark || '-' }}</td>
                        </tr>
                        <tr v-if="!props.physicalTests.length">
                            <td colspan="6" class="px-3 py-6 text-center text-muted">暂无体测成绩</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'support'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                <h2 class="text-lg font-semibold text-main">资助对象记录</h2>
                <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">学年</th>
                            <th class="px-3 py-2 text-left">资助等级</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">专业</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="item in props.supportRecipients" :key="item.id">
                            <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-warning-surface px-2 py-1 text-xs font-semibold text-warning">{{ item.support_level || '-' }}</span>
                            </td>
                            <td class="px-3 py-2">{{ item.college || '-' }}</td>
                            <td class="px-3 py-2">{{ item.major || '-' }}</td>
                        </tr>
                        <tr v-if="!props.supportRecipients.length">
                            <td colspan="4" class="px-3 py-6 text-center text-muted">暂无资助对象记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'support'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                <h2 class="text-lg font-semibold text-main">助学贷款记录</h2>
                <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">年度</th>
                            <th class="px-3 py-2 text-left">来源</th>
                            <th class="px-3 py-2 text-left">金额</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">班级</th>
                            <th class="px-3 py-2 text-left">备注</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="loan in props.loans" :key="loan.id">
                            <td class="px-3 py-2">{{ loan.annual_year || '-' }}</td>
                            <td class="px-3 py-2">{{ loan.source || '-' }}</td>
                            <td class="px-3 py-2">{{ money(loan.amount) }}</td>
                            <td class="px-3 py-2">{{ loan.college || '-' }}</td>
                            <td class="px-3 py-2">{{ loan.class_name || '-' }}</td>
                            <td class="px-3 py-2">{{ loan.remark || '-' }}</td>
                        </tr>
                        <tr v-if="!props.loans.length">
                            <td colspan="6" class="px-3 py-6 text-center text-muted">暂无助学贷款记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'honor'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                <h2 class="text-lg font-semibold text-main">团学干部任职考核</h2>
                <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
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
                    <tbody class="divide-y divide-line">
                        <tr v-for="item in props.cadreAssessments" :key="item.id">
                            <td class="px-3 py-2">{{ item.academic_year || '-' }}</td>
                            <td class="px-3 py-2">{{ item.semester === '1' ? '第一学期' : item.semester === '2' ? '第二学期' : '-' }}</td>
                            <td class="px-3 py-2">{{ item.organization || '-' }}</td>
                            <td class="px-3 py-2">{{ item.department || '-' }}</td>
                            <td class="px-3 py-2">{{ item.position || '-' }}</td>
                            <td class="px-3 py-2">{{ item.total_score ?? '-' }}</td>
                            <td class="px-3 py-2">{{ item.grade || '-' }}</td>
                        </tr>
                        <tr v-if="!props.cadreAssessments.length">
                            <td colspan="7" class="px-3 py-6 text-center text-muted">暂无团学干部任职考核记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'honor'" class="mb-6 rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                <h2 class="text-lg font-semibold text-main">科技竞赛获奖</h2>
                <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
            </div>
            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">时间</th>
                            <th class="px-3 py-2 text-left">荣誉名称</th>
                            <th class="px-3 py-2 text-left">年级</th>
                            <th class="px-3 py-2 text-left">学院</th>
                            <th class="px-3 py-2 text-left">班级</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="item in props.technologyCompetitionAwards" :key="item.id">
                            <td class="px-3 py-2">{{ dateText(item.awarded_at) }}</td>
                            <td class="px-3 py-2">{{ item.award_name || '-' }}</td>
                            <td class="px-3 py-2">{{ item.grade || '-' }}</td>
                            <td class="px-3 py-2">{{ item.college || '-' }}</td>
                            <td class="px-3 py-2">{{ item.class_name || '-' }}</td>
                        </tr>
                        <tr v-if="!props.technologyCompetitionAwards.length">
                            <td colspan="5" class="px-3 py-6 text-center text-muted">暂无科技竞赛获奖记录</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="activeProfileSection === 'honor'" class="mb-6 grid gap-6 lg:grid-cols-2 mobile-stack">
            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
                <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                    <h2 class="text-lg font-semibold text-main">奖励记录</h2>
                    <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
                </div>
                <div class="overflow-x-auto rounded-lg border border-line">
                    <table class="min-w-full text-sm ui-table">
                        <thead class="bg-surface-raised text-muted">
                            <tr>
                                <th class="px-3 py-2 text-left">年度</th>
                                <th class="px-3 py-2 text-left">奖励名称</th>
                                <th class="px-3 py-2 text-left">等级</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="award in props.awards" :key="award.id">
                                <td class="px-3 py-2">{{ award.annual_year || '-' }}</td>
                                <td class="px-3 py-2">{{ award.award_name || '-' }}</td>
                                <td class="px-3 py-2">{{ award.level || '-' }}</td>
                            </tr>
                            <tr v-if="!props.awards.length">
                                <td colspan="3" class="px-3 py-6 text-center text-muted">暂无奖励记录</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
                <div class="mb-3 flex items-center justify-between gap-2 mobile-wrap">
                    <h2 class="text-lg font-semibold text-main">惩罚记录</h2>
                    <a href="/student-imports" class="text-sm text-info hover:underline">导入</a>
                </div>
                <div class="overflow-x-auto rounded-lg border border-line">
                    <table class="min-w-full text-sm ui-table">
                        <thead class="bg-surface-raised text-muted">
                            <tr>
                                <th class="px-3 py-2 text-left">发生年度</th>
                                <th class="px-3 py-2 text-left">惩罚时间</th>
                                <th class="px-3 py-2 text-left">惩罚原因</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="punishment in props.punishments" :key="punishment.id">
                                <td class="px-3 py-2">{{ punishment.annual_year || '-' }}</td>
                                <td class="px-3 py-2">{{ punishment.punished_at || '-' }}</td>
                                <td class="px-3 py-2">{{ punishment.reason || '-' }}</td>
                            </tr>
                            <tr v-if="!props.punishments.length">
                                <td colspan="3" class="px-3 py-6 text-center text-muted">暂无惩罚记录</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section v-if="activeProfileSection === 'family'" class="rounded-lg border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 mobile-wrap">
                <div>
                    <h2 class="text-lg font-semibold text-main">家长信息</h2>
                    <p v-if="!props.canUpdateFamilies" class="mt-1 text-xs text-muted">仅该学生当前分院的辅导员或超管可修改。</p>
                </div>
            </div>

            <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-danger-line bg-danger-surface text-danger' : 'border-success-line bg-success-surface text-success'">
                {{ notice.text }}
            </div>

            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
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
                    <tbody class="divide-y divide-line">
                        <tr v-for="family in familyRows" :key="family.id" class="align-top">
                            <td class="px-3 py-2">{{ family.name || '-' }}</td>
                            <td class="px-3 py-2">{{ family.relationship || family.specific_relationship || '-' }}</td>
                            <td class="px-3 py-2">{{ family.work_unit || '-' }}</td>
                            <td class="px-3 py-2">{{ family.position || '-' }}</td>
                            <td class="px-3 py-2">{{ family.phone || '-' }}</td>
                            <td class="px-3 py-2">{{ family.is_emergency_contact ? '是' : '否' }}</td>
                            <td v-if="props.canUpdateFamilies" class="px-3 py-2">
                                <button type="button" class="rounded border border-line px-2 py-1 text-xs text-muted hover:bg-surface-raised ui-button" @click="openEdit(family)">编辑</button>
                            </td>
                        </tr>
                        <tr v-if="!familyRows.length">
                            <td :colspan="props.canUpdateFamilies ? 7 : 6" class="px-3 py-6 text-center text-muted">暂无家长信息</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
            </div>
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-canvas/80 p-4 mobile-modal" @click.self="closeEdit">
            <form class="w-full max-w-2xl rounded-lg bg-surface p-5 shadow-xl ui-card" @submit.prevent="saveFamily">
                <h2 class="mb-4 text-xl font-bold text-main">编辑家长信息</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mobile-stack">
                    <label class="text-sm text-muted">
                        姓名
                        <input v-model="form.name" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm text-main ui-control" type="text">
                    </label>
                    <label class="text-sm text-muted">
                        关系
                        <input v-model="form.relationship" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm text-main ui-control" type="text">
                    </label>
                    <label class="text-sm text-muted">
                        具体关系
                        <input v-model="form.specific_relationship" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm text-main ui-control" type="text">
                    </label>
                    <label class="text-sm text-muted">
                        工作单位
                        <input v-model="form.work_unit" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm text-main ui-control" type="text">
                    </label>
                    <label class="text-sm text-muted">
                        职务
                        <input v-model="form.position" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm text-main ui-control" type="text">
                    </label>
                    <label class="text-sm text-muted">
                        手机
                        <input v-model="form.phone" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm text-main ui-control" type="text">
                    </label>
                    <label class="text-sm text-muted sm:col-span-2">
                        紧急联系人
                        <select v-model="form.is_emergency_contact" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm text-main ui-control">
                            <option value="0">否</option>
                            <option value="1">是</option>
                        </select>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-2 mobile-wrap">
                    <button type="button" class="rounded border border-line px-3 py-2 text-sm text-muted hover:bg-surface-raised ui-button" @click="closeEdit">取消</button>
                    <button class="rounded bg-accent px-3 py-2 text-sm font-semibold text-on-accent disabled:opacity-60 ui-button ui-button-primary" :disabled="saving">{{ saving ? '保存中...' : '保存' }}</button>
                </div>
            </form>
        </div>
    </main>
</template>
