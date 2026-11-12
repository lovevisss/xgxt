<script setup>
import { onMounted, ref } from 'vue';

const logs = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const query = ref('');
const notice = ref({ text: '', type: 'info' });
const loading = ref(false);

function showNotice(text, type = 'info') {
    notice.value = { text, type };
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('zh-CN', {
        hour12: false,
    });
}

async function fetchLogs(page = 1) {
    loading.value = true;
    showNotice('正在加载登录日志...');

    const params = new URLSearchParams({ page: String(page) });
    if (query.value.trim() !== '') {
        params.set('q', query.value.trim());
    }

    const res = await fetch(`/admin/login-logs/data?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });

    loading.value = false;
    if (!res.ok) {
        showNotice('加载失败，请稍后重试。', 'error');

        return;
    }

    const data = await res.json();
    logs.value = data.data || [];
    meta.value = {
        current_page: data.current_page || 1,
        last_page: data.last_page || 1,
        total: data.total || 0,
    };
    notice.value = { text: '', type: 'info' };
}

function search() {
    fetchLogs(1);
}

onMounted(() => {
    fetchLogs();
});
</script>

<template>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-6 rounded-2xl border border-line bg-surface p-6 shadow-sm ui-card">
            <div class="flex flex-wrap items-center justify-between gap-3 mobile-wrap">
                <div>
                    <h1 class="text-3xl font-bold text-main">登录日志</h1>
                    <p class="mt-1 text-sm text-muted">查看 CAS 用户登录账号、时间和来源信息。</p>
                </div>
                <div class="flex gap-2 mobile-wrap">
                    <a href="/admin/users" class="rounded-lg border border-line px-4 py-2 text-sm text-muted ui-button">人员与权限</a>
                    <a href="/" class="rounded-lg border border-line px-4 py-2 text-sm text-muted ui-button">返回首页</a>
                </div>
            </div>
        </header>

        <section class="rounded-2xl border border-line bg-surface p-5 shadow-sm ui-card">
            <div class="mb-4 flex flex-wrap items-center gap-2 mobile-wrap">
                <input
                    v-model="query"
                    type="search"
                    class="w-72 max-w-full rounded-lg border border-line px-3 py-2 text-sm ui-control"
                    placeholder="搜索账号或姓名"
                    @keyup.enter="search"
                >
                <button
                    type="button"
                    class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent disabled:opacity-60 ui-button ui-button-primary"
                    :disabled="loading"
                    @click="search"
                >
                    查询
                </button>
                <span class="text-sm text-muted">共 {{ meta.total }} 条</span>
            </div>

            <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-danger-line bg-danger-surface text-danger' : 'border-info-line bg-info-surface text-info'">
                {{ notice.text }}
            </div>

            <div class="overflow-x-auto rounded-xl border border-line">
                <table class="min-w-full text-sm ui-table">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">登录时间</th>
                            <th class="px-3 py-2 text-left">账号</th>
                            <th class="px-3 py-2 text-left">姓名</th>
                            <th class="px-3 py-2 text-left">IP</th>
                            <th class="px-3 py-2 text-left">浏览器</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="log in logs" :key="log.id">
                            <td class="whitespace-nowrap px-3 py-2">{{ formatDate(log.logged_in_at) }}</td>
                            <td class="px-3 py-2">{{ log.cas_username || '-' }}</td>
                            <td class="px-3 py-2">{{ log.name || '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ log.ip_address || '-' }}</td>
                            <td class="max-w-md truncate px-3 py-2" :title="log.user_agent || ''">{{ log.user_agent || '-' }}</td>
                        </tr>
                        <tr v-if="!logs.length">
                            <td colspan="5" class="px-3 py-6 text-center text-muted">暂无登录日志</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2 mobile-wrap">
                <button
                    type="button"
                    class="rounded-lg border border-line px-3 py-2 text-sm disabled:opacity-50 ui-button"
                    :disabled="loading || meta.current_page <= 1"
                    @click="fetchLogs(meta.current_page - 1)"
                >
                    上一页
                </button>
                <span class="text-sm text-muted">{{ meta.current_page }} / {{ meta.last_page }}</span>
                <button
                    type="button"
                    class="rounded-lg border border-line px-3 py-2 text-sm disabled:opacity-50 ui-button"
                    :disabled="loading || meta.current_page >= meta.last_page"
                    @click="fetchLogs(meta.current_page + 1)"
                >
                    下一页
                </button>
            </div>
        </section>
    </main>
</template>
