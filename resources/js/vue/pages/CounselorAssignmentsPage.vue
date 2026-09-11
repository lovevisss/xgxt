<script setup>
import { computed, onMounted, ref } from 'vue';
import { Plus, Save, Trash2, Search, Upload, ArrowLeft, ShieldCheck, Link, X } from '@lucide/vue';
const groups = ref([]), colleges = ref([]), admin = ref(false), canDelegate = ref(false), canManage = ref(false);
const detail = ref(null), editing = ref(false), busy = ref(false), loading = ref(false), optionsLoading = ref(false), notice = ref('');
const query = ref(''), college = ref(''), grade = ref('recent'), classQuery = ref(''), options = ref([]), chosen = ref([]);
const file = ref(null), preview = ref(null), matching = ref(null), matchCode = ref('');
const form = ref({});
let detailRequest = 0, classRequest = 0;
const visibleGroups = computed(() => groups.value.map(g => ({ ...g, counselors: g.counselors.filter(u => (!college.value || u.dwbm === college.value) && (u.name + u.cas_username).includes(query.value)) })).filter(g => g.counselors.length));
const gradeOptions = computed(() => Array.from({ length: 12 }, (_, i) => String(new Date().getFullYear() - i).slice(-2)));
const writable = computed(() => detail.value ? detail.value.can_manage : canManage.value);
async function api(url, method = 'GET', body) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' };
    if (body && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(body); }
    const response = await fetch(url, { method, headers, body });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join('；') || result.message || '请求失败');
    return result;
}
async function run(action) { if (busy.value) return; busy.value = true; notice.value = ''; try { await action(); } catch (e) { notice.value = e.message; } finally { busy.value = false; } }
async function load() {
    const data = await api('/counselors/data');
    groups.value = data.data; colleges.value = data.colleges; admin.value = data.admin; canDelegate.value = data.can_delegate; canManage.value = data.can_manage;
}
async function open(user) {
    const request = ++detailRequest;
    classRequest++;
    loading.value = true;
    optionsLoading.value = false;
    options.value = [];
    try {
        const result = (await api('/counselors/' + user.id)).data;
        if (request !== detailRequest) return;
        detail.value = result; form.value = { ...result }; editing.value = true; chosen.value = []; matching.value = null;
    }
    catch (e) { if (request === detailRequest) notice.value = e.message; }
    finally { if (request === detailRequest) loading.value = false; }
    if (request === detailRequest && detail.value?.id === user.id) void searchClasses(user.id);
}
function create() { detailRequest++; classRequest++; detail.value = null; form.value = { cas_username: '', name: '', dwbm: colleges.value[0]?.code || '', phone: '', office_phone: '', office_location: '' }; editing.value = true; options.value = []; optionsLoading.value = false; }
async function save() {
    await run(async () => {
        const result = await api(detail.value ? '/counselors/' + detail.value.id : '/counselors/data', detail.value ? 'PUT' : 'POST', form.value);
        await load(); await open(result.data); notice.value = '资料已保存';
    });
}
async function searchClasses(counselorId = detail.value?.id) {
    if (!counselorId) return;
    const request = ++classRequest;
    optionsLoading.value = true;
    const params = new URLSearchParams({ counselor_id: counselorId, q: classQuery.value, grade: matching.value ? 'all' : grade.value });
    try {
        const result = (await api('/counselors/classes?' + params)).data;
        if (request !== classRequest || detail.value?.id !== counselorId) return;
        options.value = result; chosen.value = [];
    } catch (e) {
        if (request === classRequest) notice.value = e.message;
    } finally {
        if (request === classRequest) optionsLoading.value = false;
    }
}
async function add() {
    await run(async () => {
        for (const item of options.value.filter(o => chosen.value.includes(o.key))) await api('/counselors/' + detail.value.id + '/classes', 'POST', { class_name: item.class_name, class_code: item.class_code });
        await open(detail.value); await load(); notice.value = '带班关系已保存';
    });
}
async function remove(item) {
    if (!confirm('移除 ' + item.class_name + '？')) return;
    await run(async () => { await api('/counselors/' + detail.value.id + '/classes/' + item.id, 'DELETE'); await open(detail.value); await load(); });
}
async function removeUser() {
    if (!confirm('删除辅导员 ' + detail.value.name + ' 及其带班关系？')) return;
    await run(async () => { await api('/counselors/' + detail.value.id, 'DELETE'); detail.value = null; editing.value = false; await load(); });
}
async function upload(commit = false) {
    if (!file.value) return;
    await run(async () => {
        const body = new FormData(); body.append('file', file.value); body.append('commit', commit ? '1' : '0');
        const data = await api('/counselors/import', 'POST', body);
        if (commit) { preview.value = null; file.value = null; await load(); if (detail.value) await open(detail.value); notice.value = '导入完成，待匹配班级 ' + data.unmatched + ' 个'; }
        else preview.value = data;
    });
}
async function startMatch(item) { matching.value = item; matchCode.value = ''; classQuery.value = ''; await searchClasses(); }
async function saveMatch() {
    await run(async () => { await api('/counselors/' + detail.value.id + '/classes/' + matching.value.id + '/match', 'PUT', { class_code: matchCode.value }); await open(detail.value); });
}
onMounted(() => run(load));
</script>

<template>
<main class="counselor-workspace">
    <header class="page-heading">
        <div><a href="/" class="back"><ArrowLeft :size="15" /> 首页</a><h1>辅导员带班管理</h1></div>
        <div class="actions">
            <a v-if="canDelegate" href="/counselors/permissions" class="button"><ShieldCheck :size="16" /> 人员与权限</a>
            <button v-if="canManage" :disabled="busy || loading" @click="create"><Plus :size="16" /> 新增辅导员</button>
        </div>
    </header>
    <p v-if="notice" role="status" class="notice">{{ notice }}</p>
    <section v-if="canManage" class="import-bar">
        <Upload :size="18" /><input type="file" accept=".xlsx,.xls" aria-label="带班信息表" @change="file = $event.target.files[0]; preview = null" />
        <button :disabled="!file || busy" @click="upload(false)">预览导入</button>
    </section>
    <section v-if="preview" class="preview">
        <div class="section-heading"><h2>导入预览 · {{ preview.records.length }} 位老师</h2><button title="关闭预览" @click="preview = null"><X :size="16" /></button></div>
        <p v-for="error in preview.errors" :key="error" class="error">{{ error }}</p>
        <div class="table-scroll"><table><thead><tr><th>老师</th><th>学院</th><th>新增班级</th><th>移除班级</th><th>导入后</th></tr></thead><tbody>
            <tr v-for="r in preview.records" :key="r.user.cas_username"><td>{{ r.user.name }}<small>{{ r.user.cas_username }}</small></td><td>{{ r.user.dwmc }}</td><td>{{ r.added.join('、') || '-' }}</td><td class="error">{{ r.removed.join('、') || '-' }}</td><td>{{ r.assignments.length }} 班</td></tr>
        </tbody></table></div>
        <div class="actions"><span>待匹配 {{ preview.unmatched }} 班</span><button class="primary" :disabled="busy || preview.errors.length > 0" @click="upload(true)">确认替换表内老师带班关系</button></div>
    </section>
    <div class="filters">
        <label class="search"><Search :size="16" /><input v-model="query" placeholder="姓名 / 工号" aria-label="搜索辅导员" /></label>
        <select v-model="college" aria-label="筛选学院"><option value="">全部学院</option><option v-for="c in colleges" :key="c.code" :value="c.code">{{ c.name }}</option></select>
        <span class="muted">{{ visibleGroups.reduce((n,g) => n + g.counselors.length, 0) }} 位辅导员</span>
    </div>
    <div class="workspace-columns">
        <aside class="people">
            <section v-for="g in visibleGroups" :key="g.college"><h2>{{ g.college }} <span>{{ g.counselors.length }}</span></h2>
                <button v-for="u in g.counselors" :key="u.id" class="person" :class="{ selected: detail?.id === u.id }" :disabled="busy || loading" @click="open(u)">
                    <span><strong>{{ u.name }}</strong><small>{{ u.cas_username }}</small></span><span>{{ u.class_assignments_count }} 班</span>
                </button>
            </section>
            <p v-if="!visibleGroups.length" class="empty">{{ busy ? '加载中…' : '暂无匹配人员' }}</p>
        </aside>
        <section class="detail" :aria-busy="loading">
            <p v-if="loading" class="muted">加载中…</p>
            <template v-else-if="editing">
                <div class="section-heading"><h2>{{ detail ? detail.name : '新增辅导员' }}</h2><button v-if="detail?.can_delete" title="删除辅导员" :disabled="busy" @click="removeUser"><Trash2 :size="17" /></button></div>
                <form class="person-form" @submit.prevent="save">
                    <label>工号<input v-model="form.cas_username" required :disabled="!writable || (!!detail && !admin)" /></label>
                    <label>姓名<input v-model="form.name" required :disabled="!writable" /></label>
                    <label class="wide">学院<select v-model="form.dwbm" required :disabled="!writable"><option v-if="!writable" :value="form.dwbm">{{ form.dwmc }}</option><option v-for="c in colleges" :key="c.code" :value="c.code">{{ c.name }}</option></select></label>
                    <label>手机<input v-model="form.phone" :disabled="!writable" /></label><label>办公电话<input v-model="form.office_phone" :disabled="!writable" /></label>
                    <label class="wide">办公室<input v-model="form.office_location" :disabled="!writable" /></label>
                    <div v-if="writable" class="wide"><button class="primary" :disabled="busy"><Save :size="16" /> 保存资料</button></div>
                </form>
                <template v-if="detail">
                    <div class="section-heading"><h2>带班信息</h2><span class="muted">{{ detail.assignments.length }} 班</span></div>
                    <div class="assigned-list"><div v-for="a in detail.assignments" :key="a.id" class="assigned">
                        <span>{{ a.class_name }}<small>{{ a.class_code || '待匹配' }}</small></span>
                        <div v-if="writable" class="actions"><button :title="'关联正式班级：' + a.class_name" :disabled="busy" @click="startMatch(a)"><Link :size="16" /></button><button :title="'移除 ' + a.class_name" :disabled="busy" @click="remove(a)"><X :size="16" /></button></div>
                    </div></div>
                    <p v-if="!detail.assignments.length" class="empty">暂无带班信息</p>
                    <section v-if="writable" class="class-picker">
                        <div class="section-heading"><h2>{{ matching ? '关联：' + matching.class_name : '添加班级' }}</h2><button v-if="matching" title="取消关联" @click="matching = null; searchClasses()"><X :size="16" /></button></div>
                        <form class="filters" @submit.prevent="searchClasses">
                            <input v-model="classQuery" placeholder="班级名称 / 代码" aria-label="搜索班级" />
                            <select v-model="grade" aria-label="年级" @change="searchClasses"><option value="recent">最近四届</option><option value="all">全部年级</option><option v-for="g in gradeOptions" :key="g" :value="g">{{ g }} 级</option></select>
                            <button title="搜索班级"><Search :size="16" /></button>
                        </form>
                        <p v-if="optionsLoading" class="class-loading" role="status"><span></span>正在加载可选班级</p>
                        <template v-else-if="matching"><select v-model="matchCode" aria-label="正式班级"><option value="">选择正式班级</option><option v-for="o in options.filter(o => o.class_code)" :key="o.key" :value="o.class_code">{{ o.class_name }} · {{ o.class_code }}</option></select><button :disabled="!matchCode || busy" @click="saveMatch">确认关联</button></template>
                        <template v-else><div class="class-options"><label v-for="o in options" :key="o.key"><input v-model="chosen" type="checkbox" :value="o.key" /><span>{{ o.class_name }}<small>{{ o.class_code || '待匹配' }} · {{ o.student_count }} 人</small></span></label></div><p v-if="!options.length" class="empty">暂无匹配班级</p><button class="primary" :disabled="!chosen.length || busy" @click="add"><Plus :size="16" /> 添加已选 {{ chosen.length }} 班</button></template>
                    </section>
                </template>
            </template>
            <p v-else class="empty">请选择辅导员</p>
        </section>
    </div>
</main>
</template>
<style>
.counselor-workspace{max-width:1440px;margin:auto;padding:28px 24px;color:#202b30;letter-spacing:0}
.counselor-workspace h1{font-size:24px;font-weight:700;margin:8px 0}.counselor-workspace h2{font-size:15px;font-weight:650;margin:0}
.counselor-workspace .page-heading,.counselor-workspace .section-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px}
.counselor-workspace .back,.counselor-workspace .actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.counselor-workspace .back{font-size:13px;color:#52636b}
.counselor-workspace button,.counselor-workspace .button{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid #cbd4d7;border-radius:5px;padding:8px 12px;background:#fff;font-size:13px;cursor:pointer;min-height:36px}
.counselor-workspace button:hover{background:#edf6f3}.counselor-workspace button:disabled{opacity:.5;cursor:default}.counselor-workspace .primary{background:#16725c;color:white;border-color:#16725c}
.counselor-workspace input:not([type=checkbox]):not([type=file]),.counselor-workspace select{border:1px solid #cbd4d7;border-radius:5px;padding:8px 10px;background:white;min-width:0;max-width:100%;font-size:14px}
.counselor-workspace input:disabled,.counselor-workspace select:disabled{background:#f4f6f6}.counselor-workspace .filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:16px 0}.counselor-workspace .search{display:flex;align-items:center;gap:6px}
.counselor-workspace .import-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-block:1px solid #dce2e4;padding:14px 0}.counselor-workspace input[type=file]{max-width:100%;font-size:13px}
.counselor-workspace .workspace-columns{display:grid;grid-template-columns:330px minmax(0,1fr);border-top:1px solid #dce2e4;background:white;min-height:500px}
.counselor-workspace .people{border-right:1px solid #dce2e4;max-height:900px;overflow:auto}.counselor-workspace .people h2{background:#f1f5f5;padding:12px 16px;font-size:13px;display:flex;justify-content:space-between}
.counselor-workspace .person{width:100%;justify-content:space-between;border:0;border-radius:0;border-bottom:1px solid #edf0f1;padding:13px 16px;text-align:left}.counselor-workspace .person.selected{background:#e8f5ef;box-shadow:inset 3px 0 #16725c}
.counselor-workspace small{display:block;font-size:12px;color:#6a7b82;margin-top:3px}.counselor-workspace .detail{padding:22px;min-width:0}
.counselor-workspace .person-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:28px}.counselor-workspace .person-form label{display:flex;flex-direction:column;gap:6px;font-size:13px}.counselor-workspace .wide{grid-column:1/-1}
.counselor-workspace .assigned-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 20px}.counselor-workspace .assigned{display:flex;justify-content:space-between;gap:8px;padding:10px 0;border-bottom:1px solid #e5e9ea;font-size:14px;overflow-wrap:anywhere}
.counselor-workspace .class-picker{margin-top:24px;border-top:1px solid #dce2e4;padding-top:20px}.counselor-workspace .class-options{max-height:280px;overflow:auto;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-bottom:14px}.counselor-workspace .class-options label{display:flex;align-items:center;gap:9px;padding:8px;font-size:14px}
.counselor-workspace .class-loading{display:flex;align-items:center;gap:9px;min-height:72px;color:#52636b;font-size:13px}.counselor-workspace .class-loading span{width:16px;height:16px;border:2px solid #cbd4d7;border-top-color:#16725c;border-radius:50%;animation:class-spin .7s linear infinite}@keyframes class-spin{to{transform:rotate(360deg)}}
.counselor-workspace .notice{padding:12px;background:#fff5d9;color:#755500;margin-bottom:16px}.counselor-workspace .muted,.counselor-workspace .empty{color:#6a7b82;font-size:13px}.counselor-workspace .empty{padding:36px 16px;text-align:center}
.counselor-workspace .preview{padding:20px 0;border-bottom:1px solid #dce2e4}.counselor-workspace .table-scroll{overflow:auto;max-height:380px;margin-bottom:16px}.counselor-workspace table{width:100%;border-collapse:collapse;font-size:13px}.counselor-workspace th,.counselor-workspace td{text-align:left;padding:10px;border-bottom:1px solid #dce2e4;min-width:100px;overflow-wrap:anywhere}.counselor-workspace th{background:#f1f5f5;position:sticky;top:0}.counselor-workspace .error{color:#ad3b38}
.counselor-workspace .person-form .class-options label{flex-direction:row;justify-content:flex-start;text-align:left}.counselor-workspace input[type=checkbox]{accent-color:#16725c;flex-shrink:0}.counselor-workspace .permission-scope{max-height:none}
@media(max-width:760px){.counselor-workspace{padding:16px 12px}.counselor-workspace .page-heading{align-items:flex-start;flex-direction:column}.counselor-workspace .workspace-columns{grid-template-columns:1fr}.counselor-workspace .people{max-height:300px;border-right:0;border-bottom:1px solid #dce2e4}.counselor-workspace .detail{padding:18px 12px}.counselor-workspace .assigned-list,.counselor-workspace .class-options{grid-template-columns:1fr}.counselor-workspace .filters select{max-width:100%}.counselor-workspace .person-form{grid-template-columns:1fr}.counselor-workspace .class-picker .filters input{width:100%}}
</style>
