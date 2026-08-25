<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-3 py-2">
        <SidebarGroupLabel
            class="px-3 text-[9px] font-bold tracking-[0.16em] text-sidebar-foreground/45 uppercase"
            >{{ label ?? 'Workspace' }}</SidebarGroupLabel
        >
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="h-10 rounded-xl px-3 font-medium transition-all duration-200 hover:translate-x-0.5 data-[active=true]:shadow-sm"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" class="size-4.5" />
                        <span>{{ item.title }}</span>
                        <span
                            v-if="item.badge"
                            class="ml-auto rounded-full bg-accent/12 px-2 py-0.5 text-[9px] font-bold tracking-wide text-accent uppercase"
                            >{{ item.badge }}</span
                        >
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
