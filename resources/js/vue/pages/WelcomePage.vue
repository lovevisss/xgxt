<script setup>
import { inject } from 'vue';
import { ArrowUpRight, ShieldCheck } from '@lucide/vue';
import WorkspaceIcon from '../components/WorkspaceIcon.vue';
const workspace = inject('workspace', {});
</script>
<template>
    <main class="workspace-home ui-page">
        <header class="workspace-intro">
            <div><p class="workspace-eyebrow">学生事务 · 工作入口</p><h1>{{ workspace.authenticated ? `${workspace.user?.name}，你好` : '学生工作台' }}</h1><p class="workspace-intro-note">{{ workspace.authenticated ? '从这里进入你的学生业务与管理工作。' : '登录后，查看你有权限使用的业务与学生资料。' }}</p></div>
            <div v-if="workspace.authenticated" class="workspace-identity"><span class="workspace-role">{{ workspace.user?.roleLabel }}</span><p><ShieldCheck :size="18" aria-hidden="true" /><span><small>学生查看范围</small><strong>{{ workspace.scopeLabel }}</strong></span></p></div>
        </header>
        <section v-if="!workspace.authenticated" class="workspace-empty ui-card"><WorkspaceIcon name="permissions" :size="32" /><h2>连接你的工作空间</h2><p>使用学校统一身份认证登录。</p><a href="/auth/cas/redirect" class="ui-button ui-button-primary">统一身份认证登录 <ArrowUpRight :size="16" aria-hidden="true" /></a></section>
        <section v-else-if="workspace.status" class="workspace-empty ui-card" role="status"><WorkspaceIcon name="permissions" :size="32" /><h2>暂时没有可用业务</h2><p>{{ workspace.status }}</p><p>{{ workspace.scopeLabel }}</p></section>
        <section v-for="group in workspace.groups || []" :key="group.key" :id="group.key === 'management' || (group.key === 'system' && !(workspace.groups || []).some(g => g.key === 'management')) ? 'management' : group.key" class="workspace-module-group">
            <div class="workspace-group-heading"><h2>{{ group.label }}</h2><span>{{ group.entries.length }} 项业务</span></div>
            <div class="workspace-card-grid"><a v-for="entry in group.entries" :key="entry.key" :href="entry.href" class="workspace-entry" :class="{ 'workspace-entry-primary': entry.key === 'students' }"><div class="workspace-entry-top"><span class="workspace-entry-icon"><WorkspaceIcon :name="entry.key" /></span><ArrowUpRight :size="18" class="workspace-entry-arrow" aria-hidden="true" /></div><h3>{{ entry.label }}</h3><p>{{ entry.description }}</p></a></div>
        </section>
    </main>
</template>
