<script setup>
import { computed } from 'vue';
import { House, Users, GraduationCap, Contact, Upload, RefreshCw, ShieldCheck, BookOpen, ClipboardList, LayoutGrid } from '@lucide/vue';
const props = defineProps({ name: String, size: { type: Number, default: 22 } });
const icons = { home: House, students: Users, graduates: GraduationCap, families: Contact, import: Upload, sync: RefreshCw, permissions: ShieldCheck, snippets: BookOpen, counselors: Users, users: ShieldCheck, logs: ClipboardList, management: LayoutGrid };
const icon = computed(() => icons[props.name] || LayoutGrid);
</script>
<template><component :is="icon" :size="size" :stroke-width="1.7" aria-hidden="true" /></template>
