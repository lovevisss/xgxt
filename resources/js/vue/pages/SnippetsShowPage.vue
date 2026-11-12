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
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 ui-page">
        <header class="mb-6 rounded-2xl border border-line bg-surface p-6 shadow-sm ui-card">
            <div class="flex items-center justify-between gap-3 mobile-wrap">
                <h1 class="text-2xl font-bold">{{ props.snippet.title }}</h1>
                <a class="text-sm text-muted hover:underline" :href="`/snippets/${props.snippet.id}/fork`">Fork Me</a>
            </div>
        </header>
        <section class="mb-6 rounded-xl border border-line bg-surface p-4 shadow-sm ui-card">
            <pre class="overflow-x-auto rounded bg-canvas p-3 text-xs text-main ui-code"><code>{{ props.snippet.body }}</code></pre>
            <p class="mt-3"><a href="/snippets" class="text-info hover:underline">Back</a></p>
        </section>
        <section v-if="props.forks.length" class="rounded-xl border border-line bg-surface p-4 shadow-sm ui-card">
            <h2 class="mb-3 text-lg font-semibold">Forks</h2>
            <ul class="space-y-2">
                <li v-for="fork in props.forks" :key="fork.id">
                    <a :href="`/snippets/${fork.id}`" class="text-info hover:underline">{{ fork.title }}</a>
                </li>
            </ul>
        </section>
    </main>
</template>
