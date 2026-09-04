<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    FileText,
    LayoutDashboard,
    LockKeyhole,
    MessageSquareText,
    HandCoins,
    ArrowLeftRight,
    ReceiptText,
    Settings2,
    ShieldCheck,
    UsersRound,
    WalletCards,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
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

const page = usePage();
const access = computed(() => page.props.auth.access);
const isSuperAdmin = computed(() => access.value?.is_super_admin ?? false);
const can = (permission: string): boolean =>
    isSuperAdmin.value ||
    (access.value?.permissions ?? []).includes(permission);

const operations = computed<NavItem[]>(() => [
    { title: 'Overview', href: dashboard(), icon: LayoutDashboard },
    ...(can('portfolio.manage')
        ? [{ title: 'Estates & units', href: '/properties', icon: Building2 }]
        : []),
    ...(can('tenants.manage')
        ? [{ title: 'Tenants', href: '/tenants', icon: UsersRound }]
        : []),
    ...(can('leases.manage')
        ? [{ title: 'Leases', href: '/leases', icon: FileText }]
        : []),
    ...(can('finance.operations')
        ? [{ title: 'Rent finance', href: '/finance', icon: WalletCards }]
        : []),
    ...(can('cashflow.manage')
        ? [
              {
                  title: 'Expenses & revenue',
                  href: '/cashflow',
                  icon: ArrowLeftRight,
              },
          ]
        : []),
    ...(isSuperAdmin.value
        ? [{ title: 'Loans', href: '/loans', icon: HandCoins }]
        : []),
    ...(can('maintenance.manage')
        ? [{ title: 'Maintenance', href: '/maintenance', icon: Wrench }]
        : []),
    ...(can('messages.manage')
        ? [
              {
                  title: 'Messages',
                  href: '/messages',
                  icon: MessageSquareText,
              },
          ]
        : []),
]);

const oversight = computed<NavItem[]>(() => [
    ...(can('reports.view')
        ? [{ title: 'Reports', href: '/reports', icon: BarChart3 }]
        : []),
    ...(isSuperAdmin.value
        ? [
              {
                  title: 'Staff & security',
                  href: '/security',
                  icon: ShieldCheck,
              },
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
          ]
        : []),
]);
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
            <NavMain
                v-if="oversight.length"
                label="Oversight"
                :items="oversight"
            />
        </SidebarContent>

        <SidebarFooter class="gap-3 px-3 pb-3">
            <div
                v-if="isSuperAdmin"
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
