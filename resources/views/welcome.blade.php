<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教务数据工作台</title>
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
                        mist: '#EEF4FF',
                        accent: '#0F766E',
                        warm: '#F59E0B',
                    },
                },
            },
        };
    </script>
</head>
<body class="min-h-screen bg-gradient-to-b from-[#f8fbff] via-[#eef4ff] to-[#fff8ef] text-ink">
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute -top-28 -left-24 h-72 w-72 rounded-full bg-cyan-200/40 blur-3xl"></div>
        <div class="absolute top-1/3 -right-20 h-72 w-72 rounded-full bg-amber-200/40 blur-3xl"></div>
    </div>

    <main class="relative mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-12 rounded-3xl border border-white/70 bg-white/80 p-8 shadow-xl shadow-slate-200/50 backdrop-blur sm:p-12">
            <p class="font-display text-sm font-semibold uppercase tracking-[0.2em] text-accent">Voyager Campus</p>
            <h1 class="mt-4 font-display text-3xl font-bold leading-tight sm:text-5xl">学生信息管理工作台</h1>
            <p class="mt-4 max-w-2xl text-sm text-slate-600 sm:text-base">
                面向教务场景的数据管理入口。支持学生信息查询、编辑维护与后续业务扩展。
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/students" class="inline-flex items-center rounded-xl bg-ink px-5 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800">
                    进入学生管理
                </a>
                <a href="/student-families" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400">
                    学生家庭信息
                </a>
                <a href="/snippets" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400">
                    打开 Snippets
                </a>
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article class="rounded-2xl border border-white/70 bg-white/80 p-6 shadow-lg shadow-slate-200/40 backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">核心能力</p>
                <h2 class="mt-3 font-display text-xl font-semibold">学生信息查询</h2>
                <p class="mt-2 text-sm text-slate-600">按学号、姓名、班级快速筛选，支持分页浏览与结果定位。</p>
            </article>
            <article class="rounded-2xl border border-white/70 bg-white/80 p-6 shadow-lg shadow-slate-200/40 backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">核心能力</p>
                <h2 class="mt-3 font-display text-xl font-semibold">在线编辑保存</h2>
                <p class="mt-2 text-sm text-slate-600">通过弹窗编辑学生基础信息，保存后即时回显最新数据。</p>
            </article>
            <article class="rounded-2xl border border-white/70 bg-white/80 p-6 shadow-lg shadow-slate-200/40 backdrop-blur sm:col-span-2 lg:col-span-1">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">导航</p>
                <h2 class="mt-3 font-display text-xl font-semibold">统一业务入口</h2>
                <p class="mt-2 text-sm text-slate-600">首页聚合主要功能入口，降低操作路径并提升使用效率。</p>
            </article>
        </section>
    </main>
</body>
</html>
