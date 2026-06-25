<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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
    queued: 'border-amber-200 bg-amber-50 text-amber-700',
    running: 'border-sky-200 bg-sky-50 text-sky-700',
    succeeded: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    failed: 'border-rose-200 bg-rose-50 text-rose-700',
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

onBeforeUnmount(stopPolling);
</script>

<template>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">数据同步</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">同步中心</h1>
                    <p class="mt-2 text-sm text-slate-600">集中执行系统内所有 sync 命令，任务会进入队列并持续返回状态和日志。</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="/" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">返回首页</a>
                    <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="refreshTasks">刷新状态</button>
                </div>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
            <section class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">选择同步内容</h2>
                    <div class="mt-4 space-y-2">
                        <button
                            v-for="definition in definitions"
                            :key="definition.key"
                            type="button"
                            class="w-full rounded-md border px-4 py-3 text-left transition"
                            :class="selectedKey === definition.key ? 'border-slate-900 bg-slate-50 text-slate-950' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
                            @click="selectDefinition(definition.key)"
                        >
                            <span class="flex items-center justify-between gap-3">
                                <span class="text-sm font-semibold">{{ definition.title }}</span>
                                <span class="shrink-0 rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-500">{{ definition.command.replace('sync:', '') }}</span>
                            </span>
                            <span class="mt-2 block text-xs leading-5 text-slate-500">{{ definition.description }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="selectedDefinition" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">同步参数</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ selectedDefinition.command }}</p>

                    <div v-if="optionConfigEntries(selectedDefinition).length" class="mt-4 space-y-3">
                        <label v-for="[name, config] in optionConfigEntries(selectedDefinition)" :key="name" class="block">
                            <span class="text-sm font-medium text-slate-700">{{ config.label }}</span>
                            <input
                                v-if="config.type === 'number'"
                                v-model="optionValues[name]"
                                type="number"
                                :min="config.min"
                                :max="config.max"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                            >
                            <input
                                v-else-if="config.type === 'text'"
                                v-model="optionValues[name]"
                                type="text"
                                :placeholder="config.placeholder || ''"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                            >
                            <span v-else class="mt-2 flex items-center gap-2">
                                <input v-model="optionValues[name]" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900">
                                <span class="text-sm text-slate-600">启用</span>
                            </span>
                        </label>
                    </div>
                    <p v-else class="mt-4 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-500">该命令无需额外参数。</p>

                    <button
                        type="button"
                        class="mt-4 w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-400"
                        :disabled="starting"
                        @click="startTask"
                    >
                        {{ starting ? '正在创建...' : '开始同步' }}
                    </button>

                    <p
                        v-if="notice.text"
                        class="mt-3 rounded-md px-3 py-2 text-sm"
                        :class="notice.type === 'error' ? 'bg-rose-50 text-rose-700' : notice.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-600'"
                    >
                        {{ notice.text }}
                    </p>
                </div>
            </section>

            <section class="min-w-0 space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">当前任务</h2>
                            <p class="mt-1 text-sm text-slate-500">活动任务会每 2 秒自动刷新。</p>
                        </div>
                        <span v-if="activeTasks.length" class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                            {{ activeTasks.length }} 个任务执行中
                        </span>
                    </div>

                    <div v-if="selectedTask" class="mt-4 rounded-lg border border-slate-200">
                        <div class="border-b border-slate-200 p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-bold text-slate-950">{{ selectedTask.title }}</h3>
                                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass[selectedTask.status] || 'border-slate-200 bg-slate-50 text-slate-600'">
                                            {{ statusLabel(selectedTask.status) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">{{ selectedTask.command }} · {{ optionSummary(selectedTask) }}</p>
                                </div>
                                <div class="text-sm text-slate-500 lg:text-right">
                                    <p>耗时：{{ durationText(selectedTask) }}</p>
                                    <p>最近输出：{{ selectedTask.last_output_at || '-' }}</p>
                                </div>
                            </div>
                            <div v-if="['queued', 'running'].includes(selectedTask.status)" class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full w-1/2 animate-pulse rounded-full bg-sky-500"></div>
                            </div>
                            <p v-if="selectedTask.error" class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ selectedTask.error }}</p>
                        </div>
                        <pre class="max-h-[460px] overflow-auto whitespace-pre-wrap bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ selectedTask.log || '暂无输出。' }}</pre>
                    </div>

                    <div v-else class="mt-4 rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                        暂无同步任务。
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">最近任务</h2>
                    <div class="mt-3 overflow-hidden rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">任务</th>
                                    <th class="px-3 py-2">状态</th>
                                    <th class="px-3 py-2">开始时间</th>
                                    <th class="px-3 py-2">耗时</th>
                                    <th class="px-3 py-2">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="task in tasks" :key="task.id" :class="selectedTaskId === task.id ? 'bg-slate-50' : ''">
                                    <td class="px-3 py-2">
                                        <p class="font-semibold text-slate-900">{{ task.title }}</p>
                                        <p class="text-xs text-slate-500">{{ task.command }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="statusClass[task.status] || 'border-slate-200 bg-slate-50 text-slate-600'">
                                            {{ statusLabel(task.status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ task.started_at || task.created_at || '-' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ durationText(task) }}</td>
                                    <td class="px-3 py-2">
                                        <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="selectedTaskId = task.id">
                                            查看
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!tasks.length">
                                    <td colspan="5" class="px-3 py-8 text-center text-slate-500">暂无任务记录</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
