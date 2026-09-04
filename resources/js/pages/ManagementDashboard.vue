<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    FileText,
    LayoutDashboard,
    MessageSquareText,
    ShieldCheck,
    UsersRound,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Operations overview', href: '/dashboard' }],
    },
});

const props = defineProps<{
    staffName: string;
    permissions: string[];
    summary: {
        estates: number;
        units: number;
        tenants: number;
        active_leases: number;
        open_maintenance: number;
        unread_messages: number;
    };
}>();

const can = (permission: string): boolean =>
    props.permissions.includes(permission);
const shortcuts = computed(() =>
    [
        {
            permission: 'portfolio.manage',
            label: 'Manage properties',
            note: 'Estates, sections and units',
            href: '/properties',
            icon: Building2,
        },
        {
            permission: 'tenants.manage',
            label: 'Manage tenants',
            note: 'Profiles, identity and portal access',
            href: '/tenants',
            icon: UsersRound,
        },
        {
            permission: 'leases.manage',
            label: 'Manage leases',
            note: 'Agreements and occupancy',
            href: '/leases',
            icon: FileText,
        },
        {
            permission: 'maintenance.manage',
            label: 'Maintenance desk',
            note: `${props.summary.open_maintenance} open requests`,
            href: '/maintenance',
            icon: Wrench,
        },
        {
            permission: 'messages.manage',
            label: 'Tenant messages',
            note: `${props.summary.unread_messages} open conversations`,
            href: '/messages',
            icon: MessageSquareText,
        },
    ].filter((item) => can(item.permission)),
);
</script>

<template>
    <Head title="Operations overview" />
    <div
        class="mx-auto w-full max-w-[1540px] px-4 py-5 sm:px-6 lg:px-8 lg:py-7"
    >
        <section class="dashboard-enter" style="--delay: 20ms">
            <div
                class="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground"
            >
                <span>Home</span><span class="opacity-40">/</span
                ><span class="text-foreground">Operations</span>
            </div>
            <h1
                class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
            >
                Welcome back, {{ staffName.split(' ')[0] }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                Your workspace contains the operational tools assigned by a
                Super Admin. Private collection totals, owner accounts, loans
                and system credentials remain protected.
            </p>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="(card, index) in [
                    {
                        label: 'Registered units',
                        value: summary.units,
                        note: `${summary.estates} estate locations`,
                        icon: Building2,
                        color: 'bg-accent/10 text-accent',
                    },
                    {
                        label: 'Tenant records',
                        value: summary.tenants,
                        note: 'Residents in the directory',
                        icon: UsersRound,
                        color: 'bg-sky-500/10 text-sky-600',
                    },
                    {
                        label: 'Active leases',
                        value: summary.active_leases,
                        note: 'Current occupancy agreements',
                        icon: FileText,
                        color: 'bg-emerald-500/10 text-emerald-600',
                    },
                    {
                        label: 'Open maintenance',
                        value: summary.open_maintenance,
                        note: 'Requests needing attention',
                        icon: Wrench,
                        color: 'bg-amber-500/10 text-amber-600',
                    },
                ]"
                :key="card.label"
                class="surface-card card-lift dashboard-enter p-5"
                :style="`--delay: ${80 + index * 45}ms`"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl"
                    :class="card.color"
                    ><component :is="card.icon" class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    {{ card.label }}
                </p>
                <p class="money mt-1.5 text-[1.65rem] font-semibold">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">
                    {{ card.note }}
                </p>
            </article>
        </section>

        <section class="mt-5 grid gap-5 xl:grid-cols-[1.25fr_.75fr]">
            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 280ms"
            >
                <div class="flex items-center gap-2">
                    <LayoutDashboard class="size-4 text-accent" />
                    <h2 class="text-sm font-semibold">
                        Your assigned workspace
                    </h2>
                </div>
                <p class="mt-1.5 text-[10px] text-muted-foreground">
                    Only enabled responsibilities are shown here and in your
                    sidebar.
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="shortcut in shortcuts"
                        :key="shortcut.href"
                        :href="shortcut.href"
                        class="group flex items-center gap-3 rounded-2xl border border-border/70 p-4 transition-all hover:-translate-y-0.5 hover:border-accent/30 hover:shadow-sm"
                        ><span
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-secondary text-foreground"
                            ><component
                                :is="shortcut.icon"
                                class="size-4.5" /></span
                        ><span class="min-w-0 flex-1"
                            ><span class="block text-xs font-semibold">{{
                                shortcut.label
                            }}</span
                            ><span
                                class="mt-1 block truncate text-[9px] text-muted-foreground"
                                >{{ shortcut.note }}</span
                            ></span
                        ><ArrowRight
                            class="size-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                    /></Link>
                </div>
            </article>
            <article
                class="surface-card dashboard-enter overflow-hidden bg-[#171715] p-6 text-white dark:bg-[#efefe9] dark:text-[#171715]"
                style="--delay: 330ms"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-2xl bg-white/10 text-accent dark:bg-black/5"
                    ><ShieldCheck class="size-5"
                /></span>
                <h2 class="mt-6 text-lg font-semibold">
                    Role-protected access
                </h2>
                <p
                    class="mt-2 text-xs leading-6 text-white/55 dark:text-black/55"
                >
                    Financial overview totals, account routing, staff
                    permissions, private loans, communication API credentials
                    and smart-access controls are available only to Super
                    Admins.
                </p>
                <div class="mt-6 rounded-2xl bg-white/5 p-4 dark:bg-black/5">
                    <p
                        class="text-[9px] font-bold tracking-[0.14em] text-white/40 uppercase dark:text-black/40"
                    >
                        Your access
                    </p>
                    <p class="mt-2 text-xs font-semibold">
                        {{ permissions.length }} operational permissions
                    </p>
                    <p class="mt-1 text-[9px] text-white/45 dark:text-black/45">
                        Changes apply immediately when your Super Admin updates
                        the account.
                    </p>
                </div>
            </article>
        </section>
    </div>
</template>
