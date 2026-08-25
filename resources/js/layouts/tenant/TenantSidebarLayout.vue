<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import TenantSidebar from '@/components/TenantSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <AppShell variant="sidebar">
        <TenantSidebar />
        <AppContent
            variant="sidebar"
            class="min-w-0 overflow-x-clip bg-background"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
