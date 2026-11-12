<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>权限不足</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas">
<main class="mx-auto flex min-h-screen max-w-2xl items-center justify-center px-4 py-8 sm:px-6 lg:px-8 ui-page">
    <section class="w-full rounded-2xl border border-warning-line bg-surface p-8 text-center shadow-sm ui-card">
        <p class="text-sm font-semibold text-warning">403 Forbidden</p>
        <h1 class="mt-2 text-2xl font-bold text-main">权限不足</h1>
        <p class="mt-3 text-sm text-muted">{{ $message ?? '当前账号暂无访问权限，请联系超级管理员处理。' }}</p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <a href="/" class="rounded-lg border border-line px-4 py-2 text-sm text-muted ui-button">返回首页</a>
            <a href="/auth/cas/logout?returnUrl=/" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent ui-button ui-button-primary">退出并切换账号</a>
        </div>
    </section>
</main>
</body>
</html>

