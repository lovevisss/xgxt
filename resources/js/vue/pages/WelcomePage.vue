<template>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-8 rounded-lg border border-line bg-surface p-8 shadow-sm ui-card">
            <p class="text-sm font-semibold uppercase tracking-wider text-accent">Voyager Campus</p>
            <h1 class="mt-3 text-3xl font-bold text-main">学生信息管理工作台</h1>
            <p class="mt-3 text-sm text-muted">统一入口：学生监测、家庭信息、数据导入、数据同步、辅导员带班与权限管理。</p>
            <div class="mt-6 flex flex-wrap gap-3 mobile-wrap">
                <a href="/students" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent ui-button ui-button-primary">进入学生管理</a>
                <a href="/student-families" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-muted ui-button">学生家庭信息</a>
                <a href="/student-imports" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-muted ui-button">学生数据导入</a>
                <a href="/sync-tasks" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-muted ui-button">同步中心</a>
                <a href="/counselors" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-muted ui-button">辅导员带班管理</a>
                <a href="/student-access-permissions" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-muted ui-button">学生权限清单</a>
                <a href="/admin/users" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-muted ui-button">人员与权限</a>
            </div>
        </header>
    </main>
</template>
