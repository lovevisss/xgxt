<script setup>
const props = defineProps({
    snippet: { type: Object, default: () => ({}) },
    csrf: { type: String, default: '' },
});
</script>
<template>
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold">New Snippet</h1>
            <p class="mt-1 text-sm text-slate-500">Vue3 版本创建页</p>
        </header>
        <form action="/snippets" method="POST" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <input type="hidden" name="_token" :value="props.csrf">
            <input v-if="props.snippet?.id" type="hidden" name="forked_id" :value="props.snippet.id">
            <div>
                <label class="mb-1 block text-sm text-slate-600" for="title">Title</label>
                <input id="title" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" type="text" name="title" :value="props.snippet?.title || ''">
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-600" for="body">Body</label>
                <textarea id="body" class="min-h-48 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" name="body">{{ props.snippet?.body || '' }}</textarea>
            </div>
            <div class="flex gap-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" type="submit">Create Snippet</button>
                <a href="/snippets" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Back</a>
            </div>
        </form>
    </main>
</template>
