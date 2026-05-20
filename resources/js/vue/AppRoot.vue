<script setup>
import { computed } from 'vue';
import WelcomePage from './pages/WelcomePage.vue';
import StudentsPage from './pages/StudentsPage.vue';
import StudentFamiliesPage from './pages/StudentFamiliesPage.vue';
import StudentAwardPunishmentImportPage from './pages/StudentAwardPunishmentImportPage.vue';
import StudentProfilePage from './pages/StudentProfilePage.vue';
import SnippetsIndexPage from './pages/SnippetsIndexPage.vue';
import SnippetsCreatePage from './pages/SnippetsCreatePage.vue';
import SnippetsShowPage from './pages/SnippetsShowPage.vue';

const props = defineProps({
    page: { type: String, default: '' },
    initialProps: { type: Object, default: () => ({}) },
});

const pages = {
    welcome: WelcomePage,
    students: StudentsPage,
    studentFamilies: StudentFamiliesPage,
    studentAwardPunishmentImport: StudentAwardPunishmentImportPage,
    studentProfile: StudentProfilePage,
    snippetsIndex: SnippetsIndexPage,
    snippetsCreate: SnippetsCreatePage,
    snippetsShow: SnippetsShowPage,
};

const currentPage = computed(() => pages[props.page] || null);
</script>

<template>
    <component :is="currentPage" v-if="currentPage" v-bind="initialProps" />
    <div v-else class="mx-auto mt-10 max-w-2xl rounded-lg border border-rose-200 bg-rose-50 p-4 text-rose-700">
        无法识别页面：{{ page }}
    </div>
</template>
