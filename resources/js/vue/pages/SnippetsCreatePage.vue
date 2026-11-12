<script setup>
const props = defineProps({
    snippet: { type: Object, default: () => ({}) },
    csrf: { type: String, default: '' },
});
</script>
<template>
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-6 rounded-2xl border border-line bg-surface p-6 shadow-sm ui-card">
            <h1 class="text-2xl font-bold">New Snippet</h1>
            <p class="mt-1 text-sm text-muted">Vue3 版本创建页</p>
        </header>
        <form action="/snippets" method="POST" class="space-y-4 rounded-2xl border border-line bg-surface p-6 shadow-sm ui-card">
            <input type="hidden" name="_token" :value="props.csrf">
            <input v-if="props.snippet?.id" type="hidden" name="forked_id" :value="props.snippet.id">
            <div>
                <label class="mb-1 block text-sm text-muted" for="title">Title</label>
                <input id="title" class="w-full rounded-lg border border-line px-3 py-2 text-sm ui-control" type="text" name="title" :value="props.snippet?.title || ''">
            </div>
            <div>
                <label class="mb-1 block text-sm text-muted" for="body">Body</label>
                <textarea id="body" class="min-h-48 w-full rounded-lg border border-line px-3 py-2 text-sm ui-control" name="body">{{ props.snippet?.body || '' }}</textarea>
            </div>
            <div class="flex gap-2 mobile-wrap">
                <button class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent ui-button ui-button-primary" type="submit">Create Snippet</button>
                <a href="/snippets" class="rounded-lg border border-line px-4 py-2 text-sm ui-button">Back</a>
            </div>
        </form>
    </main>
</template>
