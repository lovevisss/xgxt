<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    definitions: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
});

const definitions = ref([...props.definitions]);
const tasks = ref([...props.tasks]);
const selectedKey = ref(definitions.value[0]?.key || '');
const optionValues = ref({});
const starting = ref(false);
const notice = ref({ text: '', type: 'info' });
const selectedTaskId = ref(tasks.value[0]?.id || null);
const accountChanges = ref({ data: [], current_page: 1, last_page: 1, total: 0 });
const loadingChanges = ref(false);
let pollingTimer = null;

const selectedDefinition = computed(() => definitions.value.find((item) => item.key === selectedKey.value) || definitions.value[0] || null);
const selectedTask = computed(() => tasks.value.find((task) => task.id === selectedTaskId.value) || tasks.value[0] || null);
const activeTasks = computed(() => tasks.value.filter((task) => task.is_active || ['queued', 'running'].includes(task.status)));

const statusText = {
    queued: '排队中',
    running: '同步中',
    succeeded: '已完成',
    failed: '失败',
};

const statusClass = {
    queued: 'border-warning-line bg-warning-surface text-warning',
    running: 'border-info-line bg-info-surface text-info',
    succeeded: 'border-success-line bg-success-surface text-success',
    failed: 'border-danger-line bg-danger-surface text-danger',
};

function getCSRF() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.content : '';
}

function setNotice(text, type = 'info') {
    notice.value = { text, type };
}

function optionConfigEntries(definition) {
    return Object.entries(definition?.options || {});
}

function selectDefinition(key) {
    selectedKey.value = key;
    const definition = definitions.value.find((item) => item.key === key);

    optionValues.value = {};
    optionConfigEntries(definition).forEach(([name, config]) => {
        optionValues.value[name] = config.default ?? (config.type === 'boolean' ? false : '');
    });
}

function mergeTask(task) {
    const index = tasks.value.findIndex((item) => item.id === task.id);

    if (index >= 0) {
        tasks.value[index] = task;
    } else {
        tasks.value.unshift(task);
    }

    tasks.value = tasks.value.slice(0, 20);
}

async function refreshTasks() {
    const response = await fetch('/sync-tasks/data');

    if (!response.ok) {
        return;
    }

    const data = await response.json();
    definitions.value = data.definitions || definitions.value;
    tasks.value = data.tasks || [];

    if (!selectedTaskId.value && tasks.value.length) {
        selectedTaskId.value = tasks.value[0].id;
    }
}

async function refreshTask(id) {
    const response = await fetch(`/sync-tasks/data/${id}`);

    if (!response.ok) {
        return;
    }

    mergeTask(await response.json());
}

async function fetchAccountChanges(page = 1) {
    const task = selectedTask.value;
    if (!task || task.key !== 'delayed_student_accounts') {
        accountChanges.value = { data: [], current_page: 1, last_page: 1, total: 0 };
        return;
    }

    loadingChanges.value = true;
    try {
        const response = await fetch(`/sync-tasks/data/${task.id}/changes?page=${page}`);
        if (response.ok && selectedTask.value?.id === task.id) {
            accountChanges.value = await response.json();
        }
    } finally {
        loadingChanges.value = false;
    }
}

async function startTask() {
    if (!selectedDefinition.value) {
        return;
    }

    starting.value = true;
    setNotice('正在创建同步任务...');

    try {
        const response = await fetch('/sync-tasks/data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCSRF(),
            },
            body: JSON.stringify({
                key: selectedDefinition.value.key,
                options: optionValues.value,
            }),
        });

        if (!response.ok) {
            setNotice('任务创建失败，请稍后重试。', 'error');
            return;
        }

        const task = await response.json();
        mergeTask(task);
        selectedTaskId.value = task.id;
        setNotice('任务已进入队列，页面会自动刷新状态。', 'success');
    } finally {
        starting.value = false;
    }
}

function startPolling() {
    stopPolling();
    pollingTimer = window.setInterval(async () => {
        if (activeTasks.value.length === 0) {
            await refreshTasks();
            return;
        }

        await Promise.all(activeTasks.value.map((task) => refreshTask(task.id)));
    }, 2000);
}

function stopPolling() {
    if (pollingTimer) {
        window.clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

function durationText(task) {
    if (typeof task?.elapsed_seconds === 'number') {
        return formatSeconds(task.elapsed_seconds);
    }

    if (!task?.started_at) {
        return '尚未开始';
    }

    const start = new Date(task.started_at.replace(' ', 'T'));
    const end = task.finished_at ? new Date(task.finished_at.replace(' ', 'T')) : new Date();
    const seconds = Math.max(0, Math.floor((end - start) / 1000));
    return formatSeconds(seconds);
}

function formatSeconds(seconds) {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return minutes > 0 ? `${minutes}分${rest}秒` : `${rest}秒`;
}

function optionSummary(task) {
    const pairs = Object.entries(task?.options || {}).filter(([, value]) => value !== null && value !== '' && value !== false);

    return pairs.length ? pairs.map(([name, value]) => `${name}=${value === true ? '是' : value}`).join('，') : '默认参数';
}

function statusLabel(status) {
    return statusText[status] || status;
}

onMounted(() => {
    selectDefinition(selectedKey.value);
    startPolling();
});

watch([selectedTaskId, () => selectedTask.value?.status], ([taskId], previous) => {
    const previousTaskId = previous?.[0];
    if (taskId !== previousTaskId) {
        accountChanges.value = { data: [], current_page: 1, last_page: 1, total: 0 };
    }
    fetchAccountChanges();
}, { immediate: true });

onBeforeUnmount(stopPolling);
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-6 rounded-lg border border-line bg-surface p-6 shadow-sm ui-card">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-muted">数据同步</p>
                    <h1 class="mt-1 text-2xl font-bold text-main">同步中心</h1>
                    <p class="mt-2 text-sm text-muted">集中执行系统内所有 sync 命令，任务会进入队列并持续返回状态和日志。</p>
                </div>
                <div class="flex flex-wrap gap-2 mobile-wrap">
                    <a href="/" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-muted hover:bg-surface-raised ui-button">返回首页</a>
                    <button type="button" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-muted hover:bg-surface-raised ui-button" @click="refreshTasks">刷新状态</button>
                </div>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)] mobile-stack">
            <section class="space-y-4">
                <div class="rounded-lg border border-line bg-surface p-4 shadow-sm ui-card">
                    <h2 class="text-base font-semibold text-main">选择同步内容</h2>
                    <div class="mt-4 space-y-2">
                        <button
                            v-for="definition in definitions"
                            :key="definition.key"
                            type="button"
                            class="w-full rounded-md border px-4 py-3 text-left transition ui-button"
                            :class="selectedKey === definition.key ? 'border-accent bg-selected text-main' : 'border-line bg-surface text-muted hover:border-line hover:bg-surface-raised'"
                            @click="selectDefinition(definition.key)"
                        >
                            <span class="flex items-center justify-between gap-3 mobile-wrap">
                                <span class="text-sm font-semibold">{{ definition.title }}</span>
                                <span class="shrink-0 rounded bg-surface-raised px-2 py-1 text-[11px] text-muted">{{ definition.command.replace('sync:', '') }}</span>
                            </span>
                            <span class="mt-2 block text-xs leading-5 text-muted">{{ definition.description }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="selectedDefinition" class="rounded-lg border border-line bg-surface p-4 shadow-sm ui-card">
                    <h2 class="text-base font-semibold text-main">同步参数</h2>
                    <p class="mt-1 text-xs text-muted">{{ selectedDefinition.command }}</p>

                    <div v-if="optionConfigEntries(selectedDefinition).length" class="mt-4 space-y-3">
                        <label v-for="[name, config] in optionConfigEntries(selectedDefinition)" :key="name" class="block">
                            <span class="text-sm font-medium text-muted">{{ config.label }}</span>
                            <input
                                v-if="config.type === 'number'"
                                v-model="optionValues[name]"
                                type="number"
                                :min="config.min"
                                :max="config.max"
                                class="mt-1 w-full rounded-md border border-line px-3 py-2 text-sm text-main ui-control"
                            >
                            <input
                                v-else-if="config.type === 'text'"
                                v-model="optionValues[name]"
                                type="text"
                                :placeholder="config.placeholder || ''"
                                class="mt-1 w-full rounded-md border border-line px-3 py-2 text-sm text-main ui-control"
                            >
                            <span v-else class="mt-2 flex items-center gap-2 mobile-wrap">
                                <input v-model="optionValues[name]" type="checkbox" class="h-4 w-4 rounded border-line text-main">
                                <span class="text-sm text-muted">启用</span>
                            </span>
                        </label>
                    </div>
                    <p v-else class="mt-4 rounded-md bg-surface-raised px-3 py-2 text-sm text-muted">该命令无需额外参数。</p>

                    <button
                        type="button"
                        class="mt-4 w-full rounded-lg bg-accent px-4 py-3 text-sm font-semibold text-on-accent disabled:cursor-not-allowed disabled:bg-line ui-button ui-button-primary"
                        :disabled="starting"
                        @click="startTask"
                    >
                        {{ starting ? '正在创建...' : '开始同步' }}
                    </button>

                    <p
                        v-if="notice.text"
                        class="mt-3 rounded-md px-3 py-2 text-sm"
                        :class="notice.type === 'error' ? 'bg-danger-surface text-danger' : notice.type === 'success' ? 'bg-success-surface text-success' : 'bg-surface-raised text-muted'"
                    >
                        {{ notice.text }}
                    </p>
                </div>
            </section>

            <section class="min-w-0 space-y-4">
                <div class="rounded-lg border border-line bg-surface p-4 shadow-sm ui-card">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mobile-wrap">
                        <div>
                            <h2 class="text-base font-semibold text-main">当前任务</h2>
                            <p class="mt-1 text-sm text-muted">活动任务会每 2 秒自动刷新。</p>
                        </div>
                        <span v-if="activeTasks.length" class="rounded-full border border-info-line bg-info-surface px-3 py-1 text-xs font-semibold text-info">
                            {{ activeTasks.length }} 个任务执行中
                        </span>
                    </div>

                    <div v-if="selectedTask" class="mt-4 rounded-lg border border-line">
                        <div class="border-b border-line p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2 mobile-wrap">
                                        <h3 class="text-lg font-bold text-main">{{ selectedTask.title }}</h3>
                                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass[selectedTask.status] || 'border-line bg-surface-raised text-muted'">
                                            {{ statusLabel(selectedTask.status) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-muted">{{ selectedTask.command }} · {{ optionSummary(selectedTask) }}</p>
                                </div>
                                <div class="text-sm text-muted lg:text-right">
                                    <p>耗时：{{ durationText(selectedTask) }}</p>
                                    <p>最近输出：{{ selectedTask.last_output_at || '-' }}</p>
                                </div>
                            </div>
                            <div v-if="['queued', 'running'].includes(selectedTask.status)" class="mt-4 h-2 overflow-hidden rounded-full bg-surface-raised">
                                <div class="h-full w-1/2 animate-pulse rounded-full bg-info"></div>
                            </div>
                            <p v-if="selectedTask.error" class="mt-3 rounded-md bg-danger-surface px-3 py-2 text-sm text-danger">{{ selectedTask.error }}</p>
                        </div>
                        <pre class="max-h-[460px] overflow-auto whitespace-pre-wrap bg-canvas p-4 text-xs leading-6 text-main ui-code">{{ selectedTask.log || '暂无输出。' }}</pre>
                    </div>

                    <div v-else class="mt-4 rounded-lg border border-dashed border-line p-8 text-center text-sm text-muted">
                        暂无同步任务。
                    </div>
                </div>

                <div v-if="selectedTask?.key === 'delayed_student_accounts'" class="rounded-lg border border-line bg-surface p-4 shadow-sm ui-card">
                    <div class="flex flex-wrap items-center justify-between gap-3 mobile-wrap">
                        <h2 class="text-base font-semibold text-main">逐人变更日志</h2>
                        <span class="text-sm text-muted">共 {{ accountChanges.total || 0 }} 人</span>
                    </div>
                    <div class="mt-3 overflow-x-auto border border-line">
                        <table class="min-w-[850px] w-full text-sm ui-table">
                            <thead class="bg-surface-raised text-left text-muted">
                                <tr>
                                    <th class="px-3 py-2">学号 / 姓名</th>
                                    <th class="px-3 py-2">学院</th>
                                    <th class="px-3 py-2">原失效日期</th>
                                    <th class="px-3 py-2">新失效日期</th>
                                    <th class="px-3 py-2">状态变更</th>
                                    <th class="px-3 py-2">修改时间</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="change in accountChanges.data" :key="change.id">
                                    <td class="px-3 py-2"><span class="block font-medium">{{ change.student_name }}</span><span class="text-xs text-muted">{{ change.student_xgh }}</span></td>
                                    <td class="px-3 py-2">{{ change.college_name || '-' }}</td>
                                    <td class="px-3 py-2">{{ change.previous_expiry_date || '未设置' }}</td>
                                    <td class="px-3 py-2">{{ change.new_expiry_date || '未设置' }}</td>
                                    <td class="px-3 py-2">{{ change.previous_state }} → {{ change.new_state }}</td>
                                    <td class="px-3 py-2">{{ change.changed_at }}</td>
                                </tr>
                                <tr v-if="!accountChanges.data?.length"><td colspan="6" class="px-3 py-6 text-center text-muted">{{ loadingChanges ? '正在加载...' : '暂无字段变更' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 flex items-center justify-end gap-2 text-sm mobile-wrap">
                        <span class="text-muted">第 {{ accountChanges.current_page || 1 }} / {{ accountChanges.last_page || 1 }} 页</span>
                        <button class="rounded border border-line px-2 py-1 disabled:opacity-40 ui-button" :disabled="loadingChanges || accountChanges.current_page <= 1" @click="fetchAccountChanges(accountChanges.current_page - 1)">上一页</button>
                        <button class="rounded border border-line px-2 py-1 disabled:opacity-40 ui-button" :disabled="loadingChanges || accountChanges.current_page >= accountChanges.last_page" @click="fetchAccountChanges(accountChanges.current_page + 1)">下一页</button>
                    </div>
                </div>

                <div class="rounded-lg border border-line bg-surface p-4 shadow-sm ui-card">
                    <h2 class="text-base font-semibold text-main">最近任务</h2>
                    <div class="mt-3 overflow-x-auto rounded-lg border border-line">
                        <table class="min-w-full divide-y divide-line text-sm ui-table">
                            <thead class="bg-surface-raised text-left text-xs font-semibold text-muted">
                                <tr>
                                    <th class="px-3 py-2">任务</th>
                                    <th class="px-3 py-2">状态</th>
                                    <th class="px-3 py-2">开始时间</th>
                                    <th class="px-3 py-2">耗时</th>
                                    <th class="px-3 py-2">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line bg-surface">
                                <tr v-for="task in tasks" :key="task.id" :class="selectedTaskId === task.id ? 'bg-surface-raised' : ''">
                                    <td class="px-3 py-2">
                                        <p class="font-semibold text-main">{{ task.title }}</p>
                                        <p class="text-xs text-muted">{{ task.command }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="statusClass[task.status] || 'border-line bg-surface-raised text-muted'">
                                            {{ statusLabel(task.status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-muted">{{ task.started_at || task.created_at || '-' }}</td>
                                    <td class="px-3 py-2 text-muted">{{ durationText(task) }}</td>
                                    <td class="px-3 py-2">
                                        <button type="button" class="rounded border border-line px-2 py-1 text-xs font-semibold text-muted hover:bg-surface-raised ui-button" @click="selectedTaskId = task.id">
                                            查看
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!tasks.length">
                                    <td colspan="5" class="px-3 py-8 text-center text-muted">暂无任务记录</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
