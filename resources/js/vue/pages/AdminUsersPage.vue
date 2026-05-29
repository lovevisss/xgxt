<script setup>
import { onMounted, ref } from 'vue';

const users = ref([]);
const roleOptions = ref([]);
const notice = ref({ text: '', type: 'info' });
const updatingUserId = ref(null);

function getCSRF() {
    const m = document.querySelector('meta[name="csrf-token"]');

    return m ? m.content : '';
}

function showNotice(text, type = 'info') {
    notice.value = { text, type };
}

async function fetchUsers() {
    showNotice('正在加载管理员列表...');
    const res = await fetch('/admin/users/data');
    if (!res.ok) {
        showNotice('加载失败，请稍后重试。', 'error');

        return;
    }

    const data = await res.json();
    users.value = data.data || [];
    roleOptions.value = data.roles || [];
    notice.value = { text: '', type: 'info' };
}

async function updateRole(user, role) {
    if (updatingUserId.value !== null) {
        return;
    }

    updatingUserId.value = user.id;
    const res = await fetch(`/admin/users/${user.id}/role`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRF(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ role }),
    });

    if (!res.ok) {
        const payload = await res.json().catch(() => ({}));
        showNotice(payload.message || '更新失败，请稍后重试。', 'error');
        updatingUserId.value = null;
        await fetchUsers();

        return;
    }

    showNotice('角色更新成功。', 'success');
    updatingUserId.value = null;
    await fetchUsers();
}

onMounted(async () => {
    await fetchUsers();
});
</script>

<template>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-3xl font-bold">管理员配置</h1>
                    <p class="mt-1 text-sm text-slate-500">仅超级管理员可调整用户角色。</p>
                </div>
                <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">返回首页</a>
            </div>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div v-if="notice.text" class="mb-4 rounded-lg border px-3 py-2 text-sm" :class="notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : notice.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700'">
                {{ notice.text }}
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">ID</th>
                            <th class="px-3 py-2 text-left">姓名</th>
                            <th class="px-3 py-2 text-left">CAS 账号</th>
                            <th class="px-3 py-2 text-left">分院</th>
                            <th class="px-3 py-2 text-left">角色</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="user in users" :key="user.id">
                            <td class="px-3 py-2">{{ user.id }}</td>
                            <td class="px-3 py-2">{{ user.name || '-' }}</td>
                            <td class="px-3 py-2">{{ user.cas_username || '-' }}</td>
                            <td class="px-3 py-2">{{ user.dwmc || user.dwbm || '-' }}</td>
                            <td class="px-3 py-2">
                                <select
                                    class="rounded border border-slate-300 px-2 py-1 text-sm"
                                    :value="user.role"
                                    :disabled="updatingUserId === user.id"
                                    @change="updateRole(user, $event.target.value)"
                                >
                                    <option v-for="role in roleOptions" :key="`${user.id}-${role}`" :value="role">{{ role }}</option>
                                </select>
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500">暂无用户数据</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>

