<script setup>
const props = defineProps({
    snippets: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Snippets</h1>
                    <p class="mt-1 text-sm text-slate-500">Vue3 版本列表页</p>
                </div>
                <div class="flex gap-2">
                    <a href="/" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">首页</a>
                    <a href="/snippets/create" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">新建 Snippet</a>
                </div>
            </div>
        </header>

        <section class="space-y-4">
            <article v-for="snippet in props.snippets" :key="snippet.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <a class="font-semibold text-sky-700 hover:underline" :href="`/snippets/${snippet.id}`">{{ snippet.title }}</a>
                    <a class="text-sm text-slate-600 hover:underline" :href="`/snippets/${snippet.id}/fork`">Fork Me</a>
                </div>
                <pre class="overflow-x-auto rounded bg-slate-900 p-3 text-xs text-slate-100"><code>{{ snippet.body }}</code></pre>
            </article>
            <p v-if="!props.snippets.length" class="rounded-xl border border-slate-200 bg-white p-6 text-center text-slate-500">暂无 Snippet</p>
        </section>
    </main>
</template>

