<script setup>
import { computed, onMounted, ref } from 'vue';
import { ArrowLeft, ChevronLeft, ChevronRight, RefreshCw, Save, Search, ShieldCheck, UserCheck, Users, UserX } from '@lucide/vue';

const staff = ref([]);
const units = ref([]);
const colleges = ref([]);
const roles = ref([]);
const unmatchedUsers = ref([]);
const stats = ref({});
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const selected = ref(null);
const form = ref(emptyForm());
const query = ref('');
const unit = ref('');
const status = ref('active');
const role = ref('');
const loading = ref(false);
const saving = ref(false);
const notice = ref({ text: '', type: 'info' });

const roleLabels = {
    super_admin: '总管理员',
    admin: '管理员',
    counselor: '辅导员',
    staff: '普通人员',
};
const selectedScopeText = computed(() => {
    if (!form.value.counselor_management) return '未授权';
    if (form.value.all_colleges) return '全校';
    return form.value.department_codes
        .map((code) => colleges.value.find((college) => college.code === code)?.name || code)
        .join('、') || '请选择学院';
});

function emptyForm() {
    return { role: 'staff', counselor_management: false, all_colleges: false, department_codes: [] };
}
function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}
async function api(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...(options.headers || {}) },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join('；') || payload.message || '请求失败');
    return payload;
}
function showNotice(text, type = 'info') {
    notice.value = { text, type };
}
function selectStaff(member) {
    selected.value = member;
    form.value = {
        role: member.role || 'staff',
        counselor_management: Boolean(member.counselor_management),
        all_colleges: Boolean(member.all_colleges),
        department_codes: [...(member.department_codes || [])],
    };
}
async function load(page = 1) {
    loading.value = true;
    notice.value = { text: '', type: 'info' };
    try {
        const params = new URLSearchParams({ page, per_page: 40, q: query.value, unit: unit.value, status: status.value, role: role.value });
        const payload = await api('/admin/users/data?' + params);
        staff.value = payload.data || [];
        units.value = payload.units || [];
        colleges.value = payload.colleges || [];
        roles.value = payload.roles || [];
        unmatchedUsers.value = payload.unmatched_users || [];
        stats.value = payload.stats || {};
        meta.value = payload.meta || meta.value;
        if (selected.value) {
            const current = staff.value.find((member) => member.id === selected.value.id);
            if (current) selectStaff(current);
            else selected.value = null;
        }
    } catch (error) {
        showNotice(error.message, 'error');
    } finally {
        loading.value = false;
    }
}
async function save() {
    if (!selected.value || saving.value) return;
    saving.value = true;
    try {
        const payload = await api('/admin/staff/' + selected.value.id + '/permissions', {
            method: 'PUT',
            body: JSON.stringify(form.value),
        });
        selectStaff(payload.data);
        showNotice('已保存 ' + selected.value.name + ' 的权限。', 'success');
        await load(meta.value.current_page);
    } catch (error) {
        showNotice(error.message, 'error');
    } finally {
        saving.value = false;
    }
}
function toggleAllColleges() {
    if (form.value.all_colleges) form.value.department_codes = [];
}
function roleLabel(value) {
    return roleLabels[value] || '未开通';
}
function statusText(member) {
    if (!member.is_active) return '非在职';
    return member.account_exists ? '已开通' : '未开通';
}

onMounted(() => load());
</script>

<template>
    <main class="permission-workspace">
        <header class="permission-header">
            <div>
                <a href="/" class="back-link"><ArrowLeft :size="15" /> 首页</a>
                <h1>人员与权限管理</h1>
                <p>从在职教职工目录选择人员，统一配置系统角色和带班管理范围。</p>
            </div>
            <div class="header-actions">
                <a href="/sync-tasks" class="quiet-button"><RefreshCw :size="16" /> 同步中心</a>
                <a href="/admin/login-logs" class="quiet-button">登录日志</a>
            </div>
        </header>

        <p v-if="notice.text" class="permission-notice" :class="'is-' + notice.type" role="status">{{ notice.text }}</p>

        <section class="permission-stats" aria-label="权限概览">
            <div><Users :size="18" /><span><strong>{{ stats.active_staff || 0 }}</strong>在职教职工</span></div>
            <div><UserCheck :size="18" /><span><strong>{{ stats.provisioned || 0 }}</strong>已开通账号</span></div>
            <div><ShieldCheck :size="18" /><span><strong>{{ stats.privileged || 0 }}</strong>管理员</span></div>
            <div><UserCheck :size="18" /><span><strong>{{ stats.counselor_managers || 0 }}</strong>带班管理授权</span></div>
        </section>

        <section class="directory-filters">
            <label class="search-field"><Search :size="16" /><input v-model.trim="query" placeholder="姓名 / 工号" aria-label="搜索姓名或工号" @keyup.enter="load(1)"></label>
            <select v-model="unit" aria-label="筛选单位" @change="load(1)">
                <option value="">全部单位</option>
                <option v-for="item in units" :key="item.code" :value="item.code">{{ item.name }}（{{ item.total }}）</option>
            </select>
            <select v-model="status" aria-label="筛选在职状态" @change="load(1)">
                <option value="active">在职</option>
                <option value="inactive">非在职</option>
                <option value="all">全部状态</option>
            </select>
            <select v-model="role" aria-label="筛选角色" @change="load(1)">
                <option value="">全部角色</option>
                <option value="unassigned">未开通</option>
                <option v-for="item in roles" :key="item" :value="item">{{ roleLabel(item) }}</option>
            </select>
            <button type="button" class="icon-button" title="执行搜索" @click="load(1)"><Search :size="17" /></button>
        </section>

        <div class="permission-layout">
            <aside class="staff-directory" aria-label="教职工目录">
                <div class="directory-caption">
                    <span>{{ meta.total || 0 }} 人</span>
                    <span>第 {{ meta.current_page || 1 }} / {{ meta.last_page || 1 }} 页</span>
                </div>
                <button
                    v-for="member in staff"
                    :key="member.id"
                    type="button"
                    class="staff-row"
                    :class="{ selected: selected?.id === member.id, inactive: !member.is_active }"
                    @click="selectStaff(member)"
                >
                    <span class="staff-identity">
                        <strong>{{ member.name }}</strong>
                        <small>{{ member.employee_no }} · {{ member.department_name || '未设置单位' }}</small>
                    </span>
                    <span class="staff-access">
                        <em>{{ roleLabel(member.role) }}</em>
                        <small>{{ statusText(member) }}</small>
                    </span>
                </button>
                <p v-if="!staff.length" class="empty-state">{{ loading ? '正在加载…' : '没有符合条件的人员' }}</p>
                <div class="pager">
                    <button title="上一页" :disabled="loading || meta.current_page <= 1" @click="load(meta.current_page - 1)"><ChevronLeft :size="17" /></button>
                    <button title="下一页" :disabled="loading || meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)"><ChevronRight :size="17" /></button>
                </div>
            </aside>

            <section class="permission-detail">
                <template v-if="selected">
                    <div class="detail-heading">
                        <div>
                            <span class="status-mark" :class="{ active: selected.is_active }">{{ selected.is_active ? '在职' : '非在职' }}</span>
                            <h2>{{ selected.name }}</h2>
                            <p>{{ selected.employee_no }} · {{ selected.department_name || '未设置单位' }}</p>
                        </div>
                        <span class="account-state">{{ selected.account_exists ? '账号已开通' : '授权后创建账号' }}</span>
                    </div>

                    <dl class="staff-facts">
                        <div><dt>邮箱</dt><dd>{{ selected.email || '-' }}</dd></div>
                        <div><dt>手机</dt><dd>{{ selected.phone || '-' }}</dd></div>
                        <div><dt>目录同步</dt><dd>{{ selected.synced_at || '-' }}</dd></div>
                    </dl>

                    <form class="permission-form" @submit.prevent="save">
                        <fieldset :disabled="!selected.is_active || saving">
                            <legend>系统角色</legend>
                            <div class="role-options">
                                <label v-for="item in roles" :key="item" :class="{ checked: form.role === item }">
                                    <input v-model="form.role" type="radio" :value="item">
                                    <span><strong>{{ roleLabel(item) }}</strong><small v-if="item === 'super_admin'">角色与权限管理</small><small v-else-if="item === 'admin'">全校业务管理</small><small v-else-if="item === 'counselor'">按带班查看学生</small><small v-else>无默认后台权限</small></span>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset :disabled="!selected.is_active || saving">
                            <div class="fieldset-heading">
                                <div><legend>辅导员带班管理</legend><p>{{ selectedScopeText }}</p></div>
                                <label class="switch"><input v-model="form.counselor_management" type="checkbox"><span></span></label>
                            </div>
                            <template v-if="form.counselor_management">
                                <label class="all-colleges"><input v-model="form.all_colleges" type="checkbox" @change="toggleAllColleges"> 管理全校</label>
                                <div v-if="!form.all_colleges" class="college-grid">
                                    <label v-for="item in colleges" :key="item.code">
                                        <input v-model="form.department_codes" type="checkbox" :value="item.code">
                                        <span>{{ item.name }}</span>
                                    </label>
                                </div>
                            </template>
                        </fieldset>

                        <button class="save-button" :disabled="!selected.is_active || saving">
                            <Save :size="17" /> {{ saving ? '保存中…' : '保存人员权限' }}
                        </button>
                        <p v-if="!selected.is_active" class="inactive-note"><UserX :size="15" /> 非在职人员不能新增或恢复权限。</p>
                    </form>
                </template>
                <div v-else class="detail-placeholder">
                    <ShieldCheck :size="30" />
                    <h2>选择一位教职工</h2>
                    <p>查看账号状态，并配置角色和带班管理范围。</p>
                </div>
            </section>
        </div>

        <details v-if="unmatchedUsers.length" class="unmatched-users">
            <summary>目录中不存在的本地账号（{{ unmatchedUsers.length }}）</summary>
            <div class="unmatched-grid">
                <div v-for="user in unmatchedUsers" :key="user.id">
                    <strong>{{ user.name }}</strong>
                    <span>{{ user.cas_username }} · {{ roleLabel(user.role) }} · {{ user.dwmc || '未设置单位' }}</span>
                </div>
            </div>
        </details>
    </main>
</template>

<style>
.permission-workspace{--ink:#1f2d31;--muted:#68797f;--line:#dce4e5;--soft:#f1f6f4;--accent:#15705a;max-width:1440px;margin:auto;padding:28px 24px 48px;color:var(--ink);letter-spacing:0}
.permission-header{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;padding-bottom:22px;border-bottom:1px solid var(--line)}
.permission-header h1{margin:8px 0 4px;font-size:26px;font-weight:750}.permission-header p,.detail-heading p{margin:0;color:var(--muted);font-size:13px}
.back-link,.header-actions,.quiet-button{display:flex;align-items:center;gap:7px}.back-link{color:#53666c;font-size:13px}.header-actions{flex-wrap:wrap}
.quiet-button,.icon-button,.pager button{border:1px solid #cbd6d8;background:#fff;border-radius:5px;padding:8px 11px;font-size:13px;min-height:36px}
.permission-notice{margin:16px 0 0;padding:11px 14px;font-size:13px}.permission-notice.is-error{background:#fff0ef;color:#9f3733}.permission-notice.is-success{background:#e7f5ee;color:#17654f}
.permission-stats{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid var(--line)}.permission-stats>div{display:flex;align-items:center;gap:10px;padding:18px;border-right:1px solid var(--line);color:#517068}.permission-stats>div:last-child{border:0}.permission-stats span{display:flex;align-items:baseline;gap:6px;color:var(--muted);font-size:12px}.permission-stats strong{font-size:23px;color:var(--ink)}
.directory-filters{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:15px 0}.directory-filters select,.directory-filters input{border:1px solid #cad5d7;border-radius:5px;background:#fff;padding:8px 10px;font-size:13px;min-height:37px}.directory-filters select{max-width:280px}.search-field{display:flex;align-items:center;gap:7px}.search-field input{width:210px}
.permission-layout{display:grid;grid-template-columns:390px minmax(0,1fr);border-block:1px solid var(--line);min-height:640px;background:#fff}
.staff-directory{border-right:1px solid var(--line);min-width:0}.directory-caption{display:flex;justify-content:space-between;padding:10px 14px;background:var(--soft);color:var(--muted);font-size:12px}
.staff-row{display:flex;width:100%;justify-content:space-between;align-items:center;gap:14px;border:0;border-bottom:1px solid #edf1f2;background:#fff;padding:12px 14px;text-align:left;cursor:pointer}.staff-row:hover{background:#f6faf8}.staff-row.selected{background:#e6f4ee;box-shadow:inset 3px 0 var(--accent)}.staff-row.inactive{opacity:.62}
.staff-identity,.staff-access{min-width:0}.staff-identity strong,.staff-identity small,.staff-access small{display:block}.staff-identity strong{font-size:14px}.staff-identity small{margin-top:3px;color:var(--muted);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.staff-access{text-align:right;flex-shrink:0}.staff-access em{font-style:normal;font-size:12px}.staff-access small{color:var(--muted);font-size:11px;margin-top:3px}
.pager{display:flex;justify-content:flex-end;gap:6px;padding:11px 14px}.pager button{padding:6px 9px}.pager button:disabled{opacity:.4}.empty-state{text-align:center;color:var(--muted);padding:48px 18px;font-size:13px}
.permission-detail{padding:26px 30px;min-width:0}.detail-heading{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding-bottom:20px;border-bottom:1px solid var(--line)}.detail-heading h2{font-size:22px;margin:7px 0 3px}.status-mark,.account-state{display:inline-flex;padding:4px 7px;border-radius:3px;background:#f0f2f2;color:#6a797e;font-size:11px}.status-mark.active{background:#dff2e9;color:#14614c}.account-state{margin-top:4px}
.staff-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:0;padding:18px 0;border-bottom:1px solid var(--line)}.staff-facts dt{color:var(--muted);font-size:11px}.staff-facts dd{margin:5px 0 0;font-size:13px;overflow-wrap:anywhere}
.permission-form fieldset{border:0;margin:0;padding:24px 0;border-bottom:1px solid var(--line)}.permission-form legend{font-size:15px;font-weight:700}.role-options{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-top:13px}.role-options label{display:flex;gap:9px;padding:12px;border:1px solid #d4dddf;border-radius:5px;cursor:pointer}.role-options label.checked{border-color:var(--accent);background:#edf7f3}.role-options input{margin-top:2px;accent-color:var(--accent)}.role-options strong,.role-options small{display:block}.role-options strong{font-size:13px}.role-options small{margin-top:4px;color:var(--muted);font-size:11px}
.fieldset-heading{display:flex;justify-content:space-between;align-items:center;gap:18px}.fieldset-heading p{margin:5px 0 0;color:var(--muted);font-size:12px}.switch input{position:absolute;opacity:0}.switch span{display:block;width:40px;height:22px;border-radius:12px;background:#bdc8ca;padding:3px;cursor:pointer}.switch span:after{content:'';display:block;width:16px;height:16px;border-radius:50%;background:white;transition:transform .15s}.switch input:checked+span{background:var(--accent)}.switch input:checked+span:after{transform:translateX(18px)}
.all-colleges{display:inline-flex;gap:8px;margin-top:16px;font-size:13px}.college-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 20px;margin-top:14px}.college-grid label{display:flex;gap:8px;align-items:flex-start;font-size:13px}.college-grid input,.all-colleges input{accent-color:var(--accent);margin-top:2px}
.save-button{display:inline-flex;align-items:center;gap:8px;margin-top:22px;border:1px solid var(--accent);border-radius:5px;background:var(--accent);color:white;padding:10px 15px;font-size:13px;font-weight:650}.save-button:disabled{opacity:.45}.inactive-note{display:inline-flex;align-items:center;gap:6px;margin-left:12px;color:#9d504a;font-size:12px}
.detail-placeholder{display:flex;min-height:520px;align-items:center;justify-content:center;flex-direction:column;color:#819095;text-align:center}.detail-placeholder h2{margin:12px 0 4px;font-size:17px}.detail-placeholder p{margin:0;font-size:13px}
.unmatched-users{margin-top:18px;border-top:1px solid var(--line);padding-top:15px}.unmatched-users summary{cursor:pointer;font-size:13px;font-weight:650}.unmatched-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}.unmatched-grid div{padding:10px;background:#f5f7f7}.unmatched-grid strong,.unmatched-grid span{display:block;font-size:12px}.unmatched-grid span{margin-top:3px;color:var(--muted)}
@media(max-width:900px){.permission-stats{grid-template-columns:repeat(2,1fr)}.permission-stats>div:nth-child(2){border-right:0}.permission-layout{grid-template-columns:330px minmax(0,1fr)}.role-options{grid-template-columns:repeat(2,1fr)}.staff-facts{grid-template-columns:1fr}.unmatched-grid{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.permission-workspace{padding:17px 12px 36px}.permission-header{align-items:flex-start;flex-direction:column}.permission-header h1{font-size:22px}.permission-stats>div{padding:13px 9px}.permission-stats strong{font-size:19px}.directory-filters>*{width:100%;max-width:none!important}.search-field input{width:100%}.icon-button{width:auto}.permission-layout{grid-template-columns:1fr}.staff-directory{border-right:0;border-bottom:1px solid var(--line);max-height:430px;overflow:auto}.permission-detail{padding:20px 12px}.detail-heading{flex-direction:column}.role-options,.college-grid{grid-template-columns:1fr 1fr}.inactive-note{display:flex;margin:10px 0}.unmatched-grid{grid-template-columns:1fr}}
</style>
