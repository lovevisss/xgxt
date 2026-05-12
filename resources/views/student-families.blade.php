<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>学生家庭基本信息</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,700,800|space-grotesk:500,700" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">学生家庭基本信息</h1>
                <p class="mt-1 text-sm text-slate-500">数据来源中间库，可在本地维护修正并保留本地修改标记。</p>
            </div>
            <div class="flex gap-2">
                <button id="sync-btn" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">刷新列表</button>
                <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">返回首页</a>
            </div>
        </div>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap gap-2">
            <input id="search-input" type="text" placeholder="按学号/学生姓名/手机/工作单位检索" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-slate-200 focus:ring sm:w-96">
            <select id="emergency-filter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none ring-slate-200 focus:ring">
                <option value="">全部联系人</option>
                <option value="1">仅紧急联系人</option>
                <option value="0">非紧急联系人</option>
            </select>
            <button id="search-btn" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">查询</button>
            <button id="reset-btn" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">重置</button>
        </div>

        <div id="status" class="mb-4 hidden rounded-lg border px-3 py-2 text-sm"></div>

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left">学号</th>
                        <th class="px-3 py-2 text-left">学生姓名</th>
                        <th class="px-3 py-2 text-left">姓名</th>
                        <th class="px-3 py-2 text-left">关系</th>
                        <th class="px-3 py-2 text-left">具体关系</th>
                        <th class="px-3 py-2 text-left">工作单位</th>
                        <th class="px-3 py-2 text-left">职位</th>
                        <th class="px-3 py-2 text-left">手机</th>
                        <th class="px-3 py-2 text-left">紧急联系人</th>
                        <th class="px-3 py-2 text-left">本地修改</th>
                        <th class="px-3 py-2 text-left">操作</th>
                    </tr>
                    </thead>
                    <tbody id="family-table" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p id="result-meta" class="text-xs text-slate-500"></p>
            <div id="pagination" class="flex flex-wrap gap-2"></div>
        </div>
    </section>
</main>

<div id="edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/30 p-4">
    <form id="edit-form" class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-bold">编辑家庭联系人</h2>
            <button type="button" id="close-modal" class="rounded px-2 py-1 text-sm text-slate-500 hover:bg-slate-100">关闭</button>
        </div>

        <input id="edit-id" type="hidden">

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="text-sm">
                <span class="mb-1 block text-slate-600">姓名</span>
                <input id="edit-name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-slate-600">关系</span>
                <input id="edit-relationship" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-slate-600">具体关系</span>
                <input id="edit-specific-relationship" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-slate-600">工作单位</span>
                <input id="edit-work-unit" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-slate-600">职位</span>
                <input id="edit-position" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-slate-600">手机</span>
                <input id="edit-phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm sm:col-span-2">
                <span class="mb-1 block text-slate-600">紧急联系人</span>
                <select id="edit-emergency" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="0">否</option>
                    <option value="1">是</option>
                </select>
            </label>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <button id="cancel-edit" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">取消</button>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">保存</button>
        </div>
    </form>
</div>

<script>
    let records = [];
    let pagination = {};
    let currentPage = 1;
    let keyword = '';
    let emergency = '';

    const tableEl = document.getElementById('family-table');
    const paginationEl = document.getElementById('pagination');
    const statusEl = document.getElementById('status');
    const metaEl = document.getElementById('result-meta');
    const modalEl = document.getElementById('edit-modal');

    function showStatus(message, type = 'info') {
        const theme = {
            info: 'border-sky-200 bg-sky-50 text-sky-700',
            success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            error: 'border-rose-200 bg-rose-50 text-rose-700'
        };
        statusEl.className = `mb-4 rounded-lg border px-3 py-2 text-sm ${theme[type] || theme.info}`;
        statusEl.textContent = message;
        statusEl.classList.remove('hidden');
    }

    function hideStatus() {
        statusEl.classList.add('hidden');
    }

    function getCSRF() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.content : '';
    }

    async function fetchRecords(page = 1) {
        try {
            showStatus('正在加载家庭信息...');
            const q = encodeURIComponent(keyword);
            const e = encodeURIComponent(emergency);
            const res = await fetch(`/student-families/data?page=${page}&q=${q}&emergency=${e}`);
            if (!res.ok) throw new Error('加载失败');

            const data = await res.json();
            records = data.data || [];
            pagination = data;
            currentPage = data.current_page || 1;

            renderTable();
            renderPagination();
            metaEl.textContent = `当前显示 ${data.from || 0}-${data.to || 0} 条，共 ${data.total || 0} 条`;
            hideStatus();
        } catch (error) {
            showStatus('家庭信息加载失败，请稍后重试。', 'error');
        }
    }

    function renderTable() {
        tableEl.innerHTML = '';
        if (!records.length) {
            tableEl.innerHTML = '<tr><td colspan="11" class="px-3 py-6 text-center text-slate-500">暂无数据</td></tr>';
            return;
        }

        records.forEach((row, idx) => {
            tableEl.innerHTML += `
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2">${row.stu_no || ''}</td>
                    <td class="px-3 py-2">${row.student_name || '-'}</td>
                    <td class="px-3 py-2">${row.name || ''}</td>
                    <td class="px-3 py-2">${row.relationship || ''}</td>
                    <td class="px-3 py-2">${row.specific_relationship || '-'}</td>
                    <td class="px-3 py-2">${row.work_unit || '-'}</td>
                    <td class="px-3 py-2">${row.position || '-'}</td>
                    <td class="px-3 py-2">${row.phone || '-'}</td>
                    <td class="px-3 py-2">${row.is_emergency_contact ? '是' : '否'}</td>
                    <td class="px-3 py-2">${row.is_local_modified ? '已修改' : '未修改'}</td>
                    <td class="px-3 py-2"><button class="rounded border border-slate-300 px-2 py-1 text-xs" onclick="openEditModal(${idx})">编辑</button></td>
                </tr>
            `;
        });
    }

    function pageButton(label, page, active = false, disabled = false) {
        return `<button ${disabled ? 'disabled' : `onclick="gotoPage(${page})"`} class="rounded border px-3 py-1 text-sm ${active ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300 bg-white text-slate-700'} ${disabled ? 'opacity-50 cursor-not-allowed' : ''}">${label}</button>`;
    }

    function renderPagination() {
        const lastPage = pagination.last_page || 1;
        const curr = pagination.current_page || 1;
        const buttons = [];

        buttons.push(pageButton('上一页', curr - 1, false, curr <= 1));
        for (let i = Math.max(1, curr - 2); i <= Math.min(lastPage, curr + 2); i += 1) {
            buttons.push(pageButton(String(i), i, i === curr));
        }
        buttons.push(pageButton('下一页', curr + 1, false, curr >= lastPage));
        paginationEl.innerHTML = buttons.join('');
    }

    function gotoPage(page) {
        fetchRecords(page);
    }

    function openEditModal(index) {
        const row = records[index];
        if (!row) return;

        document.getElementById('edit-id').value = row.id;
        document.getElementById('edit-name').value = row.name || '';
        document.getElementById('edit-relationship').value = row.relationship || '';
        document.getElementById('edit-specific-relationship').value = row.specific_relationship || '';
        document.getElementById('edit-work-unit').value = row.work_unit || '';
        document.getElementById('edit-position').value = row.position || '';
        document.getElementById('edit-phone').value = row.phone || '';
        document.getElementById('edit-emergency').value = row.is_emergency_contact ? '1' : '0';

        modalEl.classList.remove('hidden');
        modalEl.classList.add('flex');
    }

    function closeModal() {
        modalEl.classList.add('hidden');
        modalEl.classList.remove('flex');
    }

    document.getElementById('edit-form').addEventListener('submit', async function (event) {
        event.preventDefault();
        const id = document.getElementById('edit-id').value;

        const payload = {
            name: document.getElementById('edit-name').value,
            relationship: document.getElementById('edit-relationship').value,
            specific_relationship: document.getElementById('edit-specific-relationship').value,
            work_unit: document.getElementById('edit-work-unit').value,
            position: document.getElementById('edit-position').value,
            phone: document.getElementById('edit-phone').value,
            is_emergency_contact: document.getElementById('edit-emergency').value,
        };

        try {
            const res = await fetch(`/student-families/data/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCSRF(),
                },
                body: JSON.stringify(payload),
            });

            if (!res.ok) throw new Error('保存失败');

            closeModal();
            showStatus('保存成功。', 'success');
            fetchRecords(currentPage);
        } catch (error) {
            showStatus('保存失败，请重试。', 'error');
        }
    });

    document.getElementById('search-btn').addEventListener('click', function () {
        keyword = document.getElementById('search-input').value.trim();
        emergency = document.getElementById('emergency-filter').value;
        fetchRecords(1);
    });

    document.getElementById('reset-btn').addEventListener('click', function () {
        keyword = '';
        emergency = '';
        document.getElementById('search-input').value = '';
        document.getElementById('emergency-filter').value = '';
        fetchRecords(1);
    });

    document.getElementById('sync-btn').addEventListener('click', function () {
        fetchRecords(currentPage);
    });

    document.getElementById('close-modal').addEventListener('click', closeModal);
    document.getElementById('cancel-edit').addEventListener('click', closeModal);

    modalEl.addEventListener('click', function (event) {
        if (event.target === modalEl) {
            closeModal();
        }
    });

    const params = new URLSearchParams(window.location.search);
    keyword = params.get('q') || '';
    emergency = params.get('emergency') || '';
    document.getElementById('search-input').value = keyword;
    document.getElementById('emergency-filter').value = emergency;

    fetchRecords();
</script>
</body>
</html>

