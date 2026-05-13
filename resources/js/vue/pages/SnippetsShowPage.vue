<script setup>
const props = defineProps({
    snippet: {
        type: Object,
        required: true,
    },
    forks: {
        type: Array,
        default: () => [],
    },
});
</script>
<template>
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">{{ props.snippet.title }}</h1>
                <a class="text-sm text-slate-600 hover:underline" :href="`/snippets/${props.snippet.id}/fork`">Fork Me</a>
            </div>
        </header>
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <pre class="overflow-x-auto rounded bg-slate-900 p-3 text-xs text-slate-100"><code>{{ props.snippet.body }}</code></pre>
            <p class="mt-3"><a href="/snippets" class="text-sky-700 hover:underline">Back</a></p>
        </section>
        <section v-if="props.forks.length" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold">Forks</h2>
            <ul class="space-y-2">
                <li v-for="fork in props.forks" :key="fork.id">
                    <a :href="`/snippets/${fork.id}`" class="text-sky-700 hover:underline">{{ fork.title }}</a>
                </li>
            </ul>
        </section>
    </main>
</template>
