<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>学生管理</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,700,800|space-grotesk:500,700" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Manrope', 'sans-serif'],
                        display: ['Space Grotesk', 'sans-serif'],
                    },
                    colors: {
                        ink: '#0E1726',
                        paper: '#F8FAFC',
                        sea: '#0F766E',
                    },
                },
            },
        };
    </script>
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 via-sky-50 to-cyan-50 text-ink">
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute -top-20 -left-24 h-64 w-64 rounded-full bg-cyan-200/35 blur-3xl"></div>
        <div class="absolute top-1/3 -right-16 h-64 w-64 rounded-full bg-amber-200/30 blur-3xl"></div>
    </div>

    <main class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-3xl border border-white/70 bg-white/80 p-6 shadow-xl shadow-slate-200/50 backdrop-blur sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-display text-sm font-semibold uppercase tracking-[0.2em] text-sea">Student Hub</p>
                    <h1 class="mt-2 font-display text-3xl font-bold sm:text-4xl">学生信息管理</h1>
                    <p class="mt-2 text-sm text-slate-600">支持分页查询、关键字检索和在线编辑保存。</p>
                </div>
                <a href="/" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400">返回首页</a>
            </div>
        </header>

        <section class="rounded-3xl border border-white/70 bg-white/85 p-5 shadow-xl shadow-slate-200/50 backdrop-blur sm:p-6">
            <div class="mb-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs text-slate-500">学生总数</p>
                    <p id="summary-total" class="mt-2 font-display text-2xl font-bold text-slate-800">-</p>
                </div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs text-rose-600">当前失联人数</p>
                    <p id="summary-lost-total" class="mt-2 font-display text-2xl font-bold text-rose-700">-</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs text-amber-700">今日新增失联</p>
                    <p id="summary-lost-today" class="mt-2 font-display text-2xl font-bold text-amber-700">-</p>
                </div>
            </div>

            <div class="mb-5 flex flex-wrap gap-2">
                <button id="high-risk-btn" class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">一键只看高风险（>=7天）</button>
                <button id="clear-risk-btn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400">取消高风险筛选</button>
            </div>

            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input id="search-input" type="text" placeholder="按学号 / 姓名 / 班级搜索" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none ring-sea/20 transition focus:ring">
                <select id="status-filter" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none ring-sea/20 transition focus:ring">
                    <option value="">全部状态</option>
                    <option value="normal">正常</option>
                    <option value="lost">失联</option>
                </select>
                <div class="flex gap-2">
                    <button id="search-btn" class="rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">查询</button>
                    <button id="reset-btn" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400">重置</button>
                </div>
            </div>

            <div id="status" class="mb-4 hidden rounded-xl border px-4 py-2 text-sm"></div>

            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">学号</th>
                                <th class="px-4 py-3 text-left font-semibold">姓名</th>
                                <th class="px-4 py-3 text-left font-semibold">性别</th>
                                <th class="px-4 py-3 text-left font-semibold">班级</th>
                                <th class="px-4 py-3 text-left font-semibold">联系电话</th>
                                <th class="px-4 py-3 text-left font-semibold">最近刷码</th>
                                <th class="px-4 py-3 text-left font-semibold">距上次刷码</th>
                                <th class="px-4 py-3 text-left font-semibold">平均出入间隔(基准)</th>
                                <th class="px-4 py-3 text-left font-semibold">状态</th>
                                <th class="px-4 py-3 text-left font-semibold">统计豁免</th>
                                <th class="px-4 py-3 text-left font-semibold">操作</th>
                            </tr>
                        </thead>
                        <tbody id="students-table" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p id="result-meta" class="text-xs text-slate-500"></p>
                <div id="pagination" class="flex flex-wrap items-center gap-2"></div>
            </div>
        </section>
    </main>

    <div id="edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/35 p-4">
        <form id="edit-form" class="w-full max-w-lg rounded-2xl border border-white/70 bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-start justify-between">
                <div>
                    <h2 class="font-display text-2xl font-bold">编辑学生信息</h2>
                    <p class="text-xs text-slate-500">保存后会立即刷新当前页数据</p>
                </div>
                <button type="button" id="close-modal" class="rounded-lg px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100">关闭</button>
            </div>

            <input type="hidden" id="edit-xgh">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm">
                    <span class="mb-1 block text-slate-600">姓名</span>
                    <input id="edit-xm" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none ring-sea/20 focus:ring">
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-slate-600">性别码</span>
                    <input id="edit-xbm" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none ring-sea/20 focus:ring">
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-slate-600">班级名称</span>
                    <input id="edit-bjmc" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none ring-sea/20 focus:ring">
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-slate-600">班级编码</span>
                    <input id="edit-bjbm" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none ring-sea/20 focus:ring">
                </label>
                <label class="text-sm sm:col-span-2">
                    <span class="mb-1 block text-slate-600">电话</span>
                    <input id="edit-yddh" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none ring-sea/20 focus:ring">
                </label>
                <label class="text-sm sm:col-span-2">
                    <span class="mb-1 block text-slate-600">暂不计入统计原因</span>
                    <input id="edit-exclude-reason" type="text" placeholder="例如：离校实习、请假、交换学习" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none ring-sea/20 focus:ring">
                </label>
                <label class="text-sm sm:col-span-2">
                    <span class="mb-1 block text-slate-600">暂不计入统计截止时间</span>
                    <input id="edit-exclude-until" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none ring-sea/20 focus:ring">
                    <p class="mt-1 text-xs text-slate-500">建议默认设为半年后；留空表示恢复计入统计</p>
                </label>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="cancel-edit" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">取消</button>
                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">保存</button>
            </div>
        </form>
    </div>

    <script>
        let studentsData = [];
        let paginationInfo = {};
        let currentPage = 1;
        let currentKeyword = '';
        let currentStatus = '';
        let currentRisk = '';

        const statusEl = document.getElementById('status');
        const tableEl = document.getElementById('students-table');
        const paginationEl = document.getElementById('pagination');
        const metaEl = document.getElementById('result-meta');
        const modalEl = document.getElementById('edit-modal');
        const summaryTotalEl = document.getElementById('summary-total');
        const summaryLostTotalEl = document.getElementById('summary-lost-total');
        const summaryLostTodayEl = document.getElementById('summary-lost-today');

        function showStatus(message, type = 'info') {
            const theme = {
                info: 'border-sky-200 bg-sky-50 text-sky-700',
                success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
                error: 'border-rose-200 bg-rose-50 text-rose-700',
            };
            statusEl.className = `mb-4 rounded-xl border px-4 py-2 text-sm ${theme[type] || theme.info}`;
            statusEl.textContent = message;
            statusEl.classList.remove('hidden');
        }

        function hideStatus() {
            statusEl.classList.add('hidden');
        }

        async function fetchStudents(page = 1) {
            try {
                showStatus('正在加载数据...');
                const query = encodeURIComponent(currentKeyword);
                const status = encodeURIComponent(currentStatus);
                const risk = encodeURIComponent(currentRisk);
                const res = await fetch(`/students/data?page=${page}&q=${query}&status=${status}&risk=${risk}`);
                if (!res.ok) throw new Error('加载失败');
                const data = await res.json();

                studentsData = data.data || [];
                paginationInfo = data;
                currentPage = data.current_page || page;

                renderTable();
                renderPagination();
                renderMeta();
                renderSummary(data.summary || {});
                hideStatus();
            } catch (error) {
                showStatus('数据加载失败，请稍后重试。', 'error');
            }
        }

        function renderSummary(summary) {
            summaryTotalEl.textContent = summary.total ?? 0;
            summaryLostTotalEl.textContent = summary.lost_total ?? 0;
            summaryLostTodayEl.textContent = summary.lost_today ?? 0;
        }

        function renderMeta() {
            const total = paginationInfo.total || 0;
            const from = paginationInfo.from || 0;
            const to = paginationInfo.to || 0;
            metaEl.textContent = `当前显示 ${from}-${to} 条，共 ${total} 条记录`;
        }

        function renderTable() {
            tableEl.innerHTML = '';

            if (!studentsData.length) {
                tableEl.innerHTML = '<tr><td colspan="11" class="px-4 py-8 text-center text-slate-500">暂无符合条件的数据</td></tr>';
                return;
            }

            studentsData.forEach((stu, idx) => {
                const statusText = stu.status === 'lost' ? '失联' : '正常';
                const statusClass = stu.status === 'lost'
                    ? 'bg-rose-100 text-rose-700 border-rose-200'
                    : 'bg-emerald-100 text-emerald-700 border-emerald-200';

                const dayCount = Number.isInteger(stu.days_since_last_smsj)
                    ? stu.days_since_last_smsj
                    : null;
                const dayText = dayCount === null ? '暂无记录' : `${dayCount} 天`;
                const dayClass = dayCount === null
                    ? 'bg-slate-100 text-slate-600 border-slate-200'
                    : dayCount >= 7
                        ? 'bg-rose-100 text-rose-700 border-rose-200'
                        : dayCount >= 3
                            ? 'bg-amber-100 text-amber-700 border-amber-200'
                            : 'bg-emerald-100 text-emerald-700 border-emerald-200';

                const exclusionText = stu.is_excluded
                    ? `豁免至 ${stu.exclude_until || '-'}${stu.exclude_reason ? `（${stu.exclude_reason}）` : ''}`
                    : '未豁免';
                const exclusionClass = stu.is_excluded
                    ? 'bg-sky-100 text-sky-700 border-sky-200'
                    : 'bg-slate-100 text-slate-600 border-slate-200';

                const avgInterval = typeof stu.avg_pass_interval_minutes === 'number'
                    ? stu.avg_pass_interval_minutes
                    : null;
                const avgIntervalText = avgInterval === null
                    ? '样本不足'
                    : avgInterval >= 1440
                        ? `${(avgInterval / 1440).toFixed(1)} 天`
                        : avgInterval >= 60
                            ? `${(avgInterval / 60).toFixed(1)} 小时`
                            : `${avgInterval.toFixed(1)} 分钟`;
                const avgIntervalClass = avgInterval === null
                    ? 'bg-slate-100 text-slate-600 border-slate-200'
                    : 'bg-cyan-100 text-cyan-700 border-cyan-200';

                tableEl.innerHTML += `
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="px-4 py-3 font-medium">${stu.xgh || ''}</td>
                        <td class="px-4 py-3">${stu.xm || ''}</td>
                        <td class="px-4 py-3">${stu.xbm || ''}</td>
                        <td class="px-4 py-3">${stu.bjmc || ''}</td>
                        <td class="px-4 py-3">${stu.yddh || ''}</td>
                        <td class="px-4 py-3">${stu.last_smsj || '-'}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold ${dayClass}">${dayText}</span></td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold ${avgIntervalClass}">${avgIntervalText}</span></td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass}">${statusText}</span></td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold ${exclusionClass}">${exclusionText}</span></td>
                        <td class="px-4 py-3">
                            <button onclick="openEditModal(${idx})" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-400">编辑</button>
                        </td>
                    </tr>
                `;
            });
        }

        function pageButton(label, page, active = false, disabled = false) {
            return `<button ${disabled ? 'disabled' : `onclick="gotoPage(${page})"`} class="rounded-lg border px-3 py-1.5 text-sm ${active ? 'border-ink bg-ink text-white' : 'border-slate-300 bg-white text-slate-700'} ${disabled ? 'cursor-not-allowed opacity-50' : 'hover:border-slate-400'}">${label}</button>`;
        }

        function renderPagination() {
            const total = paginationInfo.last_page || 1;
            const curr = paginationInfo.current_page || 1;
            const buttons = [];

            buttons.push(pageButton('上一页', curr - 1, false, curr <= 1));
            for (let i = Math.max(1, curr - 2); i <= Math.min(total, curr + 2); i += 1) {
                buttons.push(pageButton(String(i), i, i === curr));
            }
            buttons.push(pageButton('下一页', curr + 1, false, curr >= total));

            paginationEl.innerHTML = buttons.join('');
        }

        function gotoPage(page) {
            fetchStudents(page);
        }

        function openEditModal(idx) {
            const s = studentsData[idx];
            if (!s) return;

            document.getElementById('edit-xgh').value = s.xgh || '';
            document.getElementById('edit-xm').value = s.xm || '';
            document.getElementById('edit-xbm').value = s.xbm || '';
            document.getElementById('edit-bjmc').value = s.bjmc || '';
            document.getElementById('edit-bjbm').value = s.bjbm || '';
            document.getElementById('edit-yddh').value = s.yddh || '';
            document.getElementById('edit-exclude-reason').value = s.exclude_reason || '';
            document.getElementById('edit-exclude-until').value = s.exclude_until ? String(s.exclude_until).slice(0, 10) : '';

            modalEl.classList.remove('hidden');
            modalEl.classList.add('flex');
        }

        function closeEditModal() {
            modalEl.classList.add('hidden');
            modalEl.classList.remove('flex');
        }

        function getCSRF() {
            const m = document.querySelector('meta[name="csrf-token"]');
            return m ? m.content : '';
        }

        document.getElementById('edit-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const xgh = document.getElementById('edit-xgh').value;
            const payload = {
                xm: document.getElementById('edit-xm').value,
                xbm: document.getElementById('edit-xbm').value,
                bjmc: document.getElementById('edit-bjmc').value,
                bjbm: document.getElementById('edit-bjbm').value,
                yddh: document.getElementById('edit-yddh').value,
                exclude_reason: document.getElementById('edit-exclude-reason').value,
                exclude_until: document.getElementById('edit-exclude-until').value,
            };

            try {
                const res = await fetch(`/students/data/${xgh}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCSRF(),
                    },
                    body: JSON.stringify(payload),
                });

                if (!res.ok) throw new Error('保存失败');

                closeEditModal();
                showStatus('保存成功，数据已更新。', 'success');
                fetchStudents(currentPage);
            } catch (error) {
                showStatus('保存失败，请检查输入后重试。', 'error');
            }
        });

        document.getElementById('search-btn').addEventListener('click', function () {
            currentKeyword = document.getElementById('search-input').value.trim();
            currentStatus = document.getElementById('status-filter').value;
            fetchStudents(1);
        });

        document.getElementById('reset-btn').addEventListener('click', function () {
            document.getElementById('search-input').value = '';
            document.getElementById('status-filter').value = '';
            currentKeyword = '';
            currentStatus = '';
            currentRisk = '';
            fetchStudents(1);
        });

        document.getElementById('high-risk-btn').addEventListener('click', function () {
            currentRisk = 'high';
            fetchStudents(1);
        });

        document.getElementById('clear-risk-btn').addEventListener('click', function () {
            currentRisk = '';
            fetchStudents(1);
        });

        document.getElementById('close-modal').addEventListener('click', closeEditModal);
        document.getElementById('cancel-edit').addEventListener('click', closeEditModal);

        modalEl.addEventListener('click', function (e) {
            if (e.target === modalEl) closeEditModal();
        });

        fetchStudents();
    </script>
</body>
</html>
