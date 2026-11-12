<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ activeSection: String });
const open = ref(false);
const trigger = ref(null);
const panel = ref(null);
let media;
let previousOverflow;

function close() { open.value = false; }
function resized() { if (!media.matches) close(); }
function keydown(event) {
    if (!open.value) return;
    if (event.key === 'Escape') close();
    if (event.key === 'Tab') {
        const items = [...panel.value.querySelectorAll('button, a[href]')];
        const first = items[0];
        const last = items.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
}
watch(() => props.activeSection, close);
watch(open, async (value) => {
    if (value) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        await nextTick();
        panel.value?.querySelector('button')?.focus();
    } else {
        document.body.style.overflow = previousOverflow ?? '';
        if (media?.matches) trigger.value?.focus();
    }
});
onMounted(() => {
    media = window.matchMedia('(max-width: 767px)');
    media.addEventListener('change', resized);
    document.addEventListener('keydown', keydown);
});
onBeforeUnmount(() => {
    if (open.value) document.body.style.overflow = previousOverflow ?? '';
    media?.removeEventListener('change', resized);
    document.removeEventListener('keydown', keydown);
});
</script>

<template>
    <aside class="profile-navigation lg:sticky lg:top-6 lg:self-start" :class="{ 'is-open': open }">
        <button ref="trigger" type="button" class="profile-navigation-trigger ui-button" aria-controls="profile-navigation-panel" :aria-expanded="open" @click="open = true">档案分区菜单</button>
        <div v-if="open" class="profile-navigation-backdrop" @click="close" aria-hidden="true"></div>
        <div id="profile-navigation-panel" ref="panel" class="profile-navigation-panel" :role="open ? 'dialog' : undefined" :aria-modal="open ? 'true' : undefined" aria-label="档案分区导航">
            <button type="button" class="profile-navigation-close ui-button" @click="close">关闭菜单</button>
            <div @click="event => { if (event.target.closest('nav button')) close(); }"><slot /></div>
        </div>
    </aside>
</template>

<style>
.profile-navigation-trigger,.profile-navigation-close,.profile-navigation-backdrop{display:none}
</style>
