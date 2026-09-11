<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
<main class="mx-auto flex min-h-screen max-w-2xl items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
    <section class="w-full rounded-lg border border-amber-200 bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-semibold text-amber-700">CAS 认证服务</p>
        <h1 class="mt-2 text-2xl font-bold">{{ $title }}</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">{{ $message }}</p>
        <p class="mt-2 text-xs text-slate-500">重试时将重新获取登录凭证，不会重复使用本次票据。</p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700">返回首页</a>
            <a href="{{ $retryUrl }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">重新登录</a>
        </div>
    </section>
</main>
</body>
</html>
