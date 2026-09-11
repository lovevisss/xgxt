<script setup>
import { computed, onMounted, ref } from 'vue';
import { ArrowLeft, Plus, Save, Pencil, Trash2 } from '@lucide/vue';
const records = ref([]), colleges = ref([]), query = ref(''), notice = ref(''), busy = ref(false), editing = ref(false), existing = ref(false);
const form = ref({});
const filtered = computed(() => records.value.filter(r => (r.employee_no + r.teacher_name).includes(query.value)));
async function api(url, method = 'GET', body) {
    const response = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: body ? JSON.stringify(body) : undefined });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join('；') || result.message || '请求失败');
    return result;
}
async function run(action) { if (busy.value) return; busy.value = true; notice.value = ''; try { await action(); } catch (e) { notice.value = e.message; } finally { busy.value = false; } }
async function load() { const data = await api('/counselors/permissions/data'); records.value = data.data; colleges.value = data.colleges; }
function edit(row) { existing.value = !!row; form.value = row ? { ...row, department_codes: [...row.department_codes] } : { employee_no: '', teacher_name: '', all_colleges: false, department_codes: [], is_active: true }; editing.value = true; }
async function save() { await run(async () => { await api('/counselors/permissions/data', 'POST', form.value); editing.value = false; await load(); notice.value = '授权已保存'; }); }
async function remove(row) { if (!confirm('撤销 ' + row.teacher_name + ' 的带班管理权限？')) return; await run(async () => { await api('/counselors/permissions/' + row.id, 'DELETE'); await load(); }); }
function scope(row) { return row.all_colleges ? '全校' : row.department_codes.map(code => colleges.value.find(c => c.code === code)?.name || code).join('、'); }
onMounted(() => run(load));
</script>
<template>
<main class="counselor-workspace">
    <header class="page-heading"><div><a href="/counselors" class="back"><ArrowLeft :size="15" /> 带班管理</a><h1>带班管理授权</h1></div><button @click="edit(null)"><Plus :size="16" /> 新增授权</button></header>
    <p v-if="notice" class="notice" role="status">{{ notice }}</p>
    <form v-if="editing" class="person-form" @submit.prevent="save">
        <label>工号<input v-model.trim="form.employee_no" required :disabled="existing" /></label><label>姓名<input v-model.trim="form.teacher_name" required /></label>
        <label class="wide"><span><input v-model="form.all_colleges" type="checkbox" /> 全校</span></label>
        <div v-if="!form.all_colleges" class="wide class-options permission-scope"><label v-for="c in colleges" :key="c.code"><input v-model="form.department_codes" type="checkbox" :value="c.code" />{{ c.name }}</label></div>
        <label class="wide"><span><input v-model="form.is_active" type="checkbox" /> 启用</span></label>
        <div class="wide actions"><button class="primary" :disabled="busy"><Save :size="16" /> 保存授权</button><button type="button" @click="editing = false">取消</button></div>
    </form>
    <div class="filters"><input v-model="query" aria-label="搜索授权老师" placeholder="姓名 / 工号" /><span class="muted">{{ filtered.length }} 条授权</span></div>
    <div class="table-scroll"><table><thead><tr><th>老师</th><th>工号</th><th>管理范围</th><th>状态</th><th>操作</th></tr></thead><tbody>
        <tr v-for="r in filtered" :key="r.id"><td>{{ r.teacher_name }}</td><td>{{ r.employee_no }}</td><td>{{ scope(r) }}</td><td>{{ r.is_active ? '启用' : '停用' }}</td><td><div class="actions"><button title="编辑授权" :disabled="busy" @click="edit(r)"><Pencil :size="16" /></button><button title="撤销授权" :disabled="busy" @click="remove(r)"><Trash2 :size="16" /></button></div></td></tr>
        <tr v-if="!filtered.length"><td colspan="5" class="empty">{{ busy ? '加载中…' : '暂无授权' }}</td></tr>
    </tbody></table></div>
</main>
</template>
