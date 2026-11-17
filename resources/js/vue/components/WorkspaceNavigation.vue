<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import WorkspaceIcon from './WorkspaceIcon.vue';
const props = defineProps({ workspace: Object, page: String });
const hash = ref(window.location.hash);
function updateHash() { hash.value = window.location.hash; }
onMounted(async () => {
    window.addEventListener('hashchange', updateHash);
    await nextTick();
    if (props.page === 'welcome' && hash.value === '#management') document.getElementById('management')?.scrollIntoView();
});
onBeforeUnmount(() => window.removeEventListener('hashchange', updateHash));
function active(key) {
    if (props.page === 'welcome') return key === (hash.value === '#management' ? 'management' : 'home');
    if (props.page.startsWith('studentFamilies')) return key === 'families';
    if (['students', 'studentProfile', 'studentDormitory'].includes(props.page)) return key === 'students';
    return key === 'management';
}
</script>
<template>
    <header class="workspace-topbar">
        <a href="/" class="workspace-brand"><WorkspaceIcon name="management" /><span>学生工作台</span></a>
        <nav class="workspace-desktop-nav" aria-label="主导航"><a v-for="item in workspace.navigation" :key="item.key" :href="item.href" :class="{ active: active(item.key) }" :aria-current="active(item.key) ? 'page' : undefined">{{ item.label }}</a></nav>
        <div class="workspace-account"><span>{{ workspace.user?.name }}<small>{{ workspace.user?.roleLabel }}</small></span><a v-if="workspace.user?.role" href="/auth/cas/logout?returnUrl=/" class="workspace-logout">退出</a></div>
    </header>
    <nav class="workspace-bottom-nav" aria-label="手机主导航"><a v-for="item in workspace.navigation" :key="item.key" :href="item.href" :class="{ active: active(item.key) }" :aria-current="active(item.key) ? 'page' : undefined"><WorkspaceIcon :name="item.key" /><span>{{ item.label }}</span></a></nav>
</template>
