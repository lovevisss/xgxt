<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-main">
<main class="mx-auto flex min-h-screen max-w-2xl items-center justify-center px-4 py-8 sm:px-6 lg:px-8 ui-page">
    <section class="w-full rounded-lg border border-warning-line bg-surface p-8 text-center shadow-sm ui-card">
        <p class="text-sm font-semibold text-warning">CAS 认证服务</p>
        <h1 class="mt-2 text-2xl font-bold">{{ $title }}</h1>
        <p class="mt-3 text-sm leading-6 text-muted">{{ $message }}</p>
        <p class="mt-2 text-xs text-muted">重试时将重新获取登录凭证，不会重复使用本次票据。</p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <a href="/" class="rounded-lg border border-line px-4 py-2 text-sm text-muted ui-button">返回首页</a>
            <a href="{{ $retryUrl }}" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent ui-button ui-button-primary">重新登录</a>
        </div>
    </section>
</main>
</body>
</html>
