<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BellRing,
    FileText,
    Home,
    MessageSquareText,
    ReceiptText,
    UserRound,
    Wrench,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const residentNavigation: NavItem[] = [
    { title: 'My home', href: dashboard(), icon: Home },
    { title: 'My profile', href: '/tenant/profile', icon: UserRound },
    { title: 'Payments', href: '/tenant/payments', icon: ReceiptText },
    { title: 'Requests', href: '/tenant/requests', icon: Wrench },
    { title: 'Messages', href: '/tenant/messages', icon: MessageSquareText },
    { title: 'Documents', href: '/tenant/documents', icon: FileText },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-none">
        <SidebarHeader class="px-3 pt-4 pb-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-13 rounded-2xl px-2 hover:bg-transparent"
                    >
                        <Link :href="dashboard()"><AppLogo /></Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="pt-2">
            <NavMain label="Resident portal" :items="residentNavigation" />
        </SidebarContent>

        <SidebarFooter class="gap-3 px-3 pb-3">
            <div
                class="rounded-2xl border border-sidebar-border bg-sidebar-foreground/[0.035] p-3.5 group-data-[collapsible=icon]:hidden"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-xl bg-accent/12 text-accent"
                    ><BellRing class="size-4"
                /></span>
                <p class="mt-3 text-xs font-semibold text-sidebar-foreground">
                    Need help?
                </p>
                <p
                    class="mt-1 text-[10px] leading-4 text-sidebar-foreground/55"
                >
                    Send a request or message directly to property management.
                </p>
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
