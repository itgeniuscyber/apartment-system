<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    FileText,
    LayoutDashboard,
    LockKeyhole,
    MessageSquareText,
    ReceiptText,
    Settings2,
    ShieldCheck,
    UsersRound,
    WalletCards,
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

const operations: NavItem[] = [
    { title: 'Overview', href: dashboard(), icon: LayoutDashboard },
    { title: 'Estates & units', href: '/properties', icon: Building2 },
    { title: 'Tenants', href: '/tenants', icon: UsersRound },
    { title: 'Leases', href: '/leases', icon: FileText },
    { title: 'Finance', href: '/finance', icon: WalletCards },
    { title: 'Maintenance', href: '/maintenance', icon: Wrench },
    {
        title: 'Messages',
        href: '/messages',
        icon: MessageSquareText,
    },
];

const oversight: NavItem[] = [
    { title: 'Reports', href: '/reports', icon: BarChart3 },
    { title: 'Security', href: '/security', icon: ShieldCheck },
    {
        title: 'Communications',
        href: '/communication-settings',
        icon: Settings2,
    },
    {
        title: 'Smart access',
        href: '/smart-access',
        icon: LockKeyhole,
        badge: 'Soon',
    },
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
            <NavMain label="Management" :items="operations" />
            <NavMain label="Oversight" :items="oversight" />
        </SidebarContent>

        <SidebarFooter class="gap-3 px-3 pb-3">
            <div
                class="rent-target-card rounded-2xl border border-sidebar-border bg-sidebar-foreground/[0.035] p-3.5 group-data-[collapsible=icon]:hidden"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="flex size-8 items-center justify-center rounded-xl bg-accent/12 text-accent"
                        ><ReceiptText class="size-4"
                    /></span>
                    <span
                        class="rounded-full bg-emerald-500/10 px-2 py-1 text-[9px] font-bold text-emerald-600 dark:text-emerald-400"
                        >Portfolio live</span
                    >
                </div>
                <p class="mt-3 text-xs font-semibold text-sidebar-foreground">
                    Monthly rent schedule
                </p>
                <p class="money mt-1 text-lg font-bold text-sidebar-foreground">
                    UGX 36.95M
                </p>
                <div
                    class="mt-3 h-1.5 overflow-hidden rounded-full bg-sidebar-border"
                >
                    <div class="h-full w-full rounded-full bg-accent" />
                </div>
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
