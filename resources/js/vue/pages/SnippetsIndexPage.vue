<script setup>
const props = defineProps({
    snippets: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-6 rounded-2xl border border-line bg-surface p-6 shadow-sm ui-card">
            <div class="flex items-center justify-between mobile-wrap">
                <div>
                    <h1 class="text-2xl font-bold">Snippets</h1>
                    <p class="mt-1 text-sm text-muted">Vue3 版本列表页</p>
                </div>
                <div class="flex gap-2 mobile-wrap">
                    <a href="/" class="rounded-lg border border-line px-4 py-2 text-sm ui-button">首页</a>
                    <a href="/snippets/create" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent ui-button ui-button-primary">新建 Snippet</a>
                </div>
            </div>
        </header>

        <section class="space-y-4">
            <article v-for="snippet in props.snippets" :key="snippet.id" class="rounded-xl border border-line bg-surface p-4 shadow-sm">
                <div class="mb-2 flex items-center justify-between gap-3 mobile-wrap">
                    <a class="font-semibold text-info hover:underline" :href="`/snippets/${snippet.id}`">{{ snippet.title }}</a>
                    <a class="text-sm text-muted hover:underline" :href="`/snippets/${snippet.id}/fork`">Fork Me</a>
                </div>
                <pre class="overflow-x-auto rounded bg-canvas p-3 text-xs text-main ui-code"><code>{{ snippet.body }}</code></pre>
            </article>
            <p v-if="!props.snippets.length" class="rounded-xl border border-line bg-surface p-6 text-center text-muted">暂无 Snippet</p>
        </section>
    </main>
</template>

