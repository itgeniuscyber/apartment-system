<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Banknote,
    BellRing,
    Building2,
    Check,
    ChevronDown,
    CircleDollarSign,
    Clock3,
    Download,
    Filter,
    MoreHorizontal,
    Plus,
    ReceiptText,
    ShieldAlert,
    UsersRound,
    WalletCards,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Financial overview', href: dashboard() }],
    },
});

type Transaction = {
    unit: string;
    tenant: string;
    channel: string;
    amount: string;
    time: string;
    status: string;
    icon: Component;
};

const props = defineProps<{
    portfolio: {
        estate_count: number;
        unit_count: number;
        monthly_rent: number;
        setup_required: number;
    };
    estatePerformance: Array<{
        id: number;
        name: string;
        units: number;
        sections: number;
        scheduled_rent: number;
        color: string;
    }>;
    settlementAccounts: Array<{
        id: number;
        owner_name: string;
        bank_name: string;
        account_last_four: string;
        estate_names: string[];
        scheduled_rent: number;
    }>;
}>();

const formatCompactMoney = (value: number): string => {
    if (value >= 1_000_000) {
        return `UGX ${(value / 1_000_000).toFixed(value % 1_000_000 === 0 ? 0 : 2)}M`;
    }

    return `UGX ${new Intl.NumberFormat('en-UG').format(value)}`;
};

const estates = computed(() =>
    props.estatePerformance.map((estate) => ({
        ...estate,
        expected: formatCompactMoney(estate.scheduled_rent).replace('UGX ', ''),
        collected: 'UGX 0',
        rate: 0,
    })),
);

const transactions: Transaction[] = [];
</script>

<template>
    <Head title="Financial overview" />

    <div
        class="mx-auto w-full max-w-[1680px] px-4 py-5 sm:px-6 lg:px-8 lg:py-7"
    >
        <section
            class="dashboard-enter flex flex-col justify-between gap-5 xl:flex-row xl:items-end"
            style="--delay: 20ms"
        >
            <div>
                <div
                    class="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground"
                >
                    <span>Home</span
                    ><span class="text-muted-foreground/40">/</span
                    ><span class="text-foreground">Overview</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Financial overview
                </h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Confirmed portfolio rates are live. Collection, arrears, and
                    occupancy analytics will activate as leases are added.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <Filter class="size-4" /> Filter
                    <ChevronDown class="size-3.5 text-muted-foreground" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <Download class="size-4" /> Export
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-4 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,0.22)] transition-all hover:-translate-y-0.5 hover:bg-accent/90"
                >
                    <Plus class="size-4" /> Record payment
                </button>
            </div>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                class="surface-card card-lift dashboard-enter relative overflow-hidden p-5"
                style="--delay: 90ms"
            >
                <div
                    class="absolute -top-10 -right-10 size-28 rounded-full bg-accent/10 blur-2xl"
                />
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                        ><ReceiptText class="size-5"
                    /></span>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                        ><Check class="size-3" /> Database live</span
                    >
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Scheduled rent
                </p>
                <p
                    class="money mt-1.5 text-[1.75rem] font-semibold tracking-[-0.055em]"
                >
                    {{ formatCompactMoney(portfolio.monthly_rent) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Across {{ portfolio.unit_count }} registered units
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 140ms"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        ><CircleDollarSign class="size-5"
                    /></span>
                    <span
                        class="rounded-full bg-secondary px-2 py-1 text-[10px] font-bold text-muted-foreground"
                        >No payments yet</span
                    >
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Collected this month
                </p>
                <p
                    class="money mt-1.5 text-[1.75rem] font-semibold tracking-[-0.055em]"
                >
                    UGX 0
                </p>
                <div
                    class="mt-3 h-1.5 overflow-hidden rounded-full bg-secondary"
                >
                    <div class="h-full w-0 rounded-full bg-emerald-500" />
                </div>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 190ms"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                        ><Clock3 class="size-5"
                    /></span>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-1 text-[10px] font-bold text-muted-foreground"
                        ><Clock3 class="size-3" /> Awaiting leases</span
                    >
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Outstanding balance
                </p>
                <p
                    class="money mt-1.5 text-[1.75rem] font-semibold tracking-[-0.055em]"
                >
                    UGX 0
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    No invoices have been generated yet
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 240ms"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                        ><Building2 class="size-5"
                    /></span>
                    <span
                        class="rounded-full bg-secondary px-2 py-1 text-[10px] font-bold text-muted-foreground"
                        >{{ portfolio.setup_required }} need setup</span
                    >
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Tenant setup progress
                </p>
                <p
                    class="money mt-1.5 text-[1.75rem] font-semibold tracking-[-0.055em]"
                >
                    {{ portfolio.unit_count - portfolio.setup_required }} /
                    {{ portfolio.unit_count }}
                </p>
                <div class="mt-3 flex gap-1">
                    <span
                        v-for="item in 11"
                        :key="item"
                        class="h-1.5 flex-1 rounded-full bg-secondary"
                    />
                </div>
            </article>
        </section>

        <section class="mt-4 grid gap-4 xl:grid-cols-[1.48fr_0.82fr]">
            <article
                class="surface-card dashboard-enter overflow-hidden p-5 sm:p-6"
                style="--delay: 290ms"
            >
                <div
                    class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                >
                    <div>
                        <h2 class="text-base font-semibold tracking-[-0.025em]">
                            Collection performance
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Collected rent compared with the monthly target
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 w-fit items-center gap-2 rounded-full bg-secondary px-3.5 text-[11px] font-semibold"
                    >
                        Last 6 months <ChevronDown class="size-3.5" />
                    </button>
                </div>

                <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_180px]">
                    <div class="min-w-0">
                        <div class="mb-5 flex items-end gap-4">
                            <div>
                                <p class="text-[11px] text-muted-foreground">
                                    Total collected
                                </p>
                                <p
                                    class="money mt-1 text-2xl font-semibold tracking-[-0.05em]"
                                >
                                    UGX 0
                                </p>
                            </div>
                            <span
                                class="mb-1 inline-flex items-center gap-1 text-[10px] font-bold text-muted-foreground"
                                ><Clock3 class="size-3" /> Waiting for
                                activity</span
                            >
                        </div>
                        <div
                            class="relative h-44 overflow-hidden rounded-2xl bg-muted/55 px-2 pt-4"
                        >
                            <div
                                class="absolute inset-x-4 top-1/4 border-t border-dashed border-border"
                            />
                            <div
                                class="absolute inset-x-4 top-1/2 border-t border-dashed border-border"
                            />
                            <div
                                class="absolute inset-x-4 top-3/4 border-t border-dashed border-border"
                            />
                            <svg
                                viewBox="0 0 640 155"
                                class="relative z-10 h-full w-full"
                                preserveAspectRatio="none"
                                aria-label="Collection trend chart"
                            >
                                <defs>
                                    <linearGradient
                                        id="areaFill"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="var(--chart-1)"
                                            stop-opacity="0.22"
                                        />
                                        <stop
                                            offset="100%"
                                            stop-color="var(--chart-1)"
                                            stop-opacity="0"
                                        />
                                    </linearGradient>
                                </defs>
                                <path
                                    d="M0 140 L640 140 L640 155 L0 155 Z"
                                    fill="url(#areaFill)"
                                />
                                <path
                                    class="chart-line"
                                    d="M0 140 L640 140"
                                    fill="none"
                                    stroke="var(--chart-1)"
                                    stroke-width="3.5"
                                    stroke-linecap="round"
                                />
                                <circle
                                    cx="640"
                                    cy="140"
                                    r="5"
                                    fill="var(--card)"
                                    stroke="var(--chart-1)"
                                    stroke-width="3"
                                />
                            </svg>
                        </div>
                        <div
                            class="mt-3 grid grid-cols-6 text-center text-[10px] font-medium text-muted-foreground"
                        >
                            <span>Mar</span><span>Apr</span><span>May</span
                            ><span>Jun</span><span>Jul</span><span>Aug</span>
                        </div>
                    </div>

                    <div class="grid content-center gap-3">
                        <div
                            class="rounded-2xl border border-border/70 bg-muted/45 p-4"
                        >
                            <p
                                class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Avg. collection
                            </p>
                            <p class="money mt-2 text-xl font-semibold">
                                UGX 0
                            </p>
                            <p
                                class="mt-1 text-[10px] text-emerald-600 dark:text-emerald-400"
                            >
                                No payment history yet
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-[#171715] p-4 text-white dark:bg-[#efefe9] dark:text-[#171715]"
                        >
                            <p
                                class="text-[10px] font-semibold tracking-wide text-white/50 uppercase dark:text-black/45"
                            >
                                Collection leader
                            </p>
                            <p class="mt-2 text-lg font-semibold">
                                Not available
                            </p>
                            <p
                                class="mt-1 text-[10px] text-white/55 dark:text-black/50"
                            >
                                Starts after first payments
                            </p>
                        </div>
                    </div>
                </div>
            </article>

            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 340ms"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-base font-semibold tracking-[-0.025em]">
                            Arrears aging
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Outstanding by days overdue
                        </p>
                    </div>
                    <button
                        class="flex size-8 items-center justify-center rounded-full hover:bg-secondary"
                        aria-label="More options"
                    >
                        <MoreHorizontal class="size-4" />
                    </button>
                </div>
                <div
                    class="mt-7 flex flex-col items-center gap-7 sm:flex-row xl:flex-col 2xl:flex-row"
                >
                    <div
                        class="relative size-40 shrink-0 rounded-full p-[18px]"
                        style="
                            background: conic-gradient(var(--secondary) 0 100%);
                        "
                    >
                        <div
                            class="flex size-full flex-col items-center justify-center rounded-full bg-card"
                        >
                            <span class="money text-xl font-semibold">0</span
                            ><span
                                class="mt-1 text-[9px] font-semibold tracking-wide text-muted-foreground uppercase"
                                >Total due</span
                            >
                        </div>
                    </div>
                    <div class="w-full space-y-3.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2.5"
                                ><i
                                    class="size-2.5 rounded-full bg-accent"
                                />1–30 days</span
                            ><strong class="money font-semibold">0</strong>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2.5"
                                ><i
                                    class="size-2.5 rounded-full bg-amber-500"
                                />31–60 days</span
                            ><strong class="money font-semibold">0</strong>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2.5"
                                ><i
                                    class="size-2.5 rounded-full bg-red-500"
                                />61–90 days</span
                            ><strong class="money font-semibold">0</strong>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2.5"
                                ><i
                                    class="size-2.5 rounded-full bg-foreground"
                                />90+ days</span
                            ><strong class="money font-semibold">0</strong>
                        </div>
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-7 flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-secondary text-xs font-semibold transition-colors hover:bg-foreground hover:text-background"
                >
                    <BellRing class="size-4" /> Send payment reminders
                </button>
            </article>
        </section>

        <section class="mt-4 grid gap-4 xl:grid-cols-[0.92fr_1.38fr]">
            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 390ms"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-base font-semibold tracking-[-0.025em]">
                            Estate performance
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Current monthly schedule
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-secondary px-2.5 py-1 text-[9px] font-bold text-muted-foreground"
                        >{{ portfolio.unit_count }} UNITS</span
                    >
                </div>
                <div class="mt-6 space-y-5">
                    <div v-for="estate in estates" :key="estate.name">
                        <div class="mb-2 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold">
                                    {{ estate.name }}
                                </p>
                                <p
                                    class="mt-1 text-[10px] text-muted-foreground"
                                >
                                    {{ estate.units }} units • UGX
                                    {{ estate.expected }} expected
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="money text-xs font-bold">
                                    {{ estate.collected }}
                                </p>
                                <p
                                    class="mt-1 text-[9px] text-muted-foreground"
                                >
                                    {{ estate.rate }}%
                                </p>
                            </div>
                        </div>
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full rounded-full transition-[width] duration-700"
                                :style="{
                                    width: `${estate.rate}%`,
                                    backgroundColor: estate.color,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </article>

            <article
                class="surface-card dashboard-enter overflow-hidden"
                style="--delay: 440ms"
            >
                <div
                    class="flex items-start justify-between px-5 pt-5 pb-4 sm:px-6 sm:pt-6"
                >
                    <div>
                        <h2 class="text-base font-semibold tracking-[-0.025em]">
                            Recent transactions
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Latest rent activity across all estates
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-xs font-semibold text-accent hover:text-accent/75"
                    >
                        View all
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left">
                        <thead>
                            <tr
                                class="border-y border-border/70 bg-muted/45 text-[9px] font-bold tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                <th class="px-6 py-3">Property</th>
                                <th class="px-4 py-3">Method</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Time</th>
                                <th class="px-6 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in transactions"
                                :key="transaction.unit + transaction.time"
                                class="group border-b border-border/60 text-xs last:border-0 hover:bg-muted/35"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex size-9 items-center justify-center rounded-xl bg-secondary font-bold text-foreground"
                                            >{{
                                                transaction.unit.slice(0, 1)
                                            }}</span
                                        >
                                        <div>
                                            <p class="font-semibold">
                                                {{ transaction.unit }}
                                            </p>
                                            <p
                                                class="mt-1 text-[10px] text-muted-foreground"
                                            >
                                                {{ transaction.tenant }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="flex items-center gap-2 text-muted-foreground"
                                        ><component
                                            :is="transaction.icon"
                                            class="size-3.5"
                                        />{{ transaction.channel }}</span
                                    >
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-bold"
                                        :class="
                                            transaction.status === 'Completed'
                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                        "
                                        ><Check
                                            v-if="
                                                transaction.status ===
                                                'Completed'
                                            "
                                            class="size-3"
                                        /><Clock3 v-else class="size-3" />{{
                                            transaction.status
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-4 text-[10px] text-muted-foreground"
                                >
                                    {{ transaction.time }}
                                </td>
                                <td
                                    class="money px-6 py-4 text-right font-semibold"
                                >
                                    UGX {{ transaction.amount }}
                                </td>
                            </tr>
                            <tr v-if="transactions.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <span
                                        class="mx-auto flex size-11 items-center justify-center rounded-2xl bg-secondary text-muted-foreground"
                                        ><ReceiptText class="size-5"
                                    /></span>
                                    <p class="mt-3 text-xs font-semibold">
                                        No transactions recorded yet
                                    </p>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        Verified payments will appear here when
                                        billing is active.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                class="surface-card card-lift dashboard-enter flex items-center gap-4 p-4"
                style="--delay: 490ms"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-2xl bg-red-500/10 text-red-500"
                    ><ShieldAlert class="size-5"
                /></span>
                <div>
                    <p class="text-sm font-semibold">0 security alerts</p>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        No incidents recorded
                    </p>
                </div>
            </article>
            <article
                class="surface-card card-lift dashboard-enter flex items-center gap-4 p-4"
                style="--delay: 530ms"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500"
                    ><Wrench class="size-5"
                /></span>
                <div>
                    <p class="text-sm font-semibold">0 open requests</p>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Maintenance desk is ready
                    </p>
                </div>
            </article>
            <article
                class="surface-card card-lift dashboard-enter flex items-center gap-4 p-4"
                style="--delay: 570ms"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-500"
                    ><UsersRound class="size-5"
                /></span>
                <div>
                    <p class="text-sm font-semibold">
                        {{ portfolio.setup_required }} units pending
                    </p>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Add tenants and lease details
                    </p>
                </div>
            </article>
            <article
                class="surface-card card-lift dashboard-enter flex items-center gap-4 p-4"
                style="--delay: 610ms"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500"
                    ><Banknote class="size-5"
                /></span>
                <div>
                    <p class="text-sm font-semibold">
                        {{ settlementAccounts.length }} routes ready
                    </p>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Estate allocations configured
                    </p>
                </div>
            </article>
        </section>

        <section class="mt-4 grid gap-4 lg:grid-cols-2">
            <article
                v-for="(account, index) in settlementAccounts"
                :key="account.id"
                class="surface-card dashboard-enter overflow-hidden p-5 sm:p-6"
                :class="
                    index === 0
                        ? 'bg-[#171715] text-white dark:bg-[#efefe9] dark:text-[#171715]'
                        : ''
                "
                :style="{ '--delay': `${650 + index * 40}ms` }"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-[0.14em] uppercase"
                            :class="
                                index === 0
                                    ? 'text-white/45 dark:text-black/45'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ account.owner_name }} account
                        </p>
                        <p class="mt-2 text-sm font-semibold">
                            {{
                                account.estate_names.join(' + ') ||
                                'No estates assigned'
                            }}
                        </p>
                    </div>
                    <component
                        :is="index === 0 ? Banknote : WalletCards"
                        class="size-5 text-accent"
                    />
                </div>
                <div class="mt-8 flex items-end justify-between gap-5">
                    <div>
                        <p
                            class="money text-2xl font-semibold tracking-[-0.05em]"
                        >
                            {{ formatCompactMoney(account.scheduled_rent) }}
                        </p>
                        <p
                            class="mt-1 text-[10px]"
                            :class="
                                index === 0
                                    ? 'text-white/45 dark:text-black/45'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ account.bank_name }} · account ending
                            {{ account.account_last_four }}
                        </p>
                    </div>
                    <span class="money text-sm font-semibold"
                        >0% collected</span
                    >
                </div>
                <div
                    class="mt-4 h-1.5 overflow-hidden rounded-full"
                    :class="
                        index === 0
                            ? 'bg-white/15 dark:bg-black/10'
                            : 'bg-secondary'
                    "
                >
                    <div class="h-full w-0 rounded-full bg-accent" />
                </div>
            </article>
        </section>
    </div>
</template>
