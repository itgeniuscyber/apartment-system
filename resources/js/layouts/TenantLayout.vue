<script setup lang="ts">
import TenantSidebarLayout from '@/layouts/tenant/TenantSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <TenantSidebarLayout :breadcrumbs="breadcrumbs">
        <slot />
    </TenantSidebarLayout>
</template>
