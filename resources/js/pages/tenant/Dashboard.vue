<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BellRing,
    Building2,
    CalendarDays,
    CheckCircle2,
    CircleDollarSign,
    Clock3,
    Home,
    MessageSquareText,
    ReceiptText,
    Sparkles,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My home', href: '/dashboard' }],
    },
});

const props = defineProps<{
    tenant: {
        id: number;
        full_name: string;
        status: string;
        phone: string | null;
        preferred_channel: string;
    } | null;
    lease: {
        id: number;
        lease_number: string;
        rent_amount: number;
        deposit_amount: number;
        due_day: number;
        grace_days: number;
        start_date: string;
        end_date: string | null;
        next_due_date: string;
        unit: {
            code: string;
            number: string;
            section: string;
            estate: string;
        };
    } | null;
    finance: {
        outstanding: number;
        overdue: number;
        open_invoices: number;
        recent_payments: Array<{
            id: number;
            receipt_number: string;
            amount: number;
            paid_at: string;
            method: string;
        }>;
    };
}>();

const page = usePage();
const firstName = computed(
    () =>
        props.tenant?.full_name.split(' ')[0] ??
        page.props.auth.user.name.split(' ')[0],
);

const formatMoney = (value: number): string =>
    `UGX ${new Intl.NumberFormat('en-UG', { maximumFractionDigits: 0 }).format(value)}`;
</script>

<template>
    <Head title="My home" />

    <div
        class="mx-auto w-full max-w-[1500px] px-4 py-5 sm:px-6 lg:px-8 lg:py-7"
    >
        <section
            class="dashboard-enter flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
            style="--delay: 20ms"
        >
            <div>
                <p class="mb-2 text-xs font-medium text-muted-foreground">
                    Resident portal
                </p>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Welcome home, {{ firstName }}.
                </h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Your rent, home details, requests, notices, and documents in
                    one secure place.
                </p>
            </div>
            <div class="flex flex-wrap gap-2.5">
                <Link
                    href="/tenant/messages"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <MessageSquareText class="size-4" /> Message management
                </Link>
                <Link
                    href="/tenant/requests"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-4 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,0.22)] transition-all hover:-translate-y-0.5"
                >
                    <Wrench class="size-4" /> New request
                </Link>
            </div>
        </section>

        <template v-if="lease">
            <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    class="surface-card card-lift dashboard-enter p-5"
                    style="--delay: 90ms"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                        ><CircleDollarSign class="size-5"
                    /></span>
                    <p class="mt-5 text-xs font-medium text-muted-foreground">
                        Monthly rent
                    </p>
                    <p
                        class="money mt-1.5 text-[1.55rem] font-semibold tracking-[-0.05em]"
                    >
                        {{ formatMoney(lease.rent_amount) }}
                    </p>
                    <p class="mt-2 text-[11px] text-muted-foreground">
                        Due on day {{ lease.due_day }} · {{ lease.grace_days }}
                        grace days
                    </p>
                </article>

                <article
                    class="surface-card card-lift dashboard-enter p-5"
                    style="--delay: 140ms"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                        ><CalendarDays class="size-5"
                    /></span>
                    <p class="mt-5 text-xs font-medium text-muted-foreground">
                        Next rent date
                    </p>
                    <p
                        class="money mt-1.5 text-[1.55rem] font-semibold tracking-[-0.05em]"
                    >
                        {{ lease.next_due_date }}
                    </p>
                    <p class="mt-2 text-[11px] text-muted-foreground">
                        Payment options activate with billing
                    </p>
                </article>

                <article
                    class="surface-card card-lift dashboard-enter p-5"
                    style="--delay: 190ms"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        ><CheckCircle2 class="size-5"
                    /></span>
                    <p class="mt-5 text-xs font-medium text-muted-foreground">
                        Current balance
                    </p>
                    <p
                        class="money mt-1.5 text-[1.55rem] font-semibold tracking-[-0.05em]"
                    >
                        {{ formatMoney(finance.outstanding) }}
                    </p>
                    <p class="mt-2 text-[11px] text-muted-foreground">
                        {{
                            finance.open_invoices
                                ? `${finance.open_invoices} open ${finance.open_invoices === 1 ? 'invoice' : 'invoices'}`
                                : 'No outstanding rent invoice'
                        }}
                    </p>
                </article>

                <article
                    class="surface-card card-lift dashboard-enter p-5"
                    style="--delay: 240ms"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                        ><Building2 class="size-5"
                    /></span>
                    <p class="mt-5 text-xs font-medium text-muted-foreground">
                        My apartment
                    </p>
                    <p
                        class="mt-1.5 text-[1.55rem] font-semibold tracking-[-0.05em]"
                    >
                        {{ lease.unit.estate }} · {{ lease.unit.number }}
                    </p>
                    <p class="mt-2 text-[11px] text-muted-foreground">
                        {{ lease.unit.section }} section · {{ lease.unit.code }}
                    </p>
                </article>
            </section>

            <section class="mt-4 grid gap-4 xl:grid-cols-[1.3fr_0.7fr]">
                <article
                    class="surface-card dashboard-enter overflow-hidden p-5 sm:p-6"
                    style="--delay: 290ms"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-base font-semibold">
                                Recent activity
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Payments, receipts, and management updates
                            </p>
                        </div>
                        <ReceiptText class="size-5 text-accent" />
                    </div>
                    <div
                        v-if="!finance.recent_payments.length"
                        class="mt-7 rounded-2xl border border-dashed border-border p-10 text-center"
                    >
                        <Clock3 class="mx-auto size-6 text-muted-foreground" />
                        <p class="mt-3 text-sm font-semibold">
                            No activity yet
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Your verified transactions and notices will appear
                            here.
                        </p>
                    </div>
                    <div v-else class="mt-5 divide-y divide-border/70">
                        <div
                            v-for="payment in finance.recent_payments"
                            :key="payment.id"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="size-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold">
                                        {{ payment.receipt_number }}
                                    </p>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        {{ payment.method }} ·
                                        {{ payment.paid_at }}
                                    </p>
                                </div>
                            </div>
                            <p
                                class="money text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ formatMoney(payment.amount) }}
                            </p>
                        </div>
                    </div>
                </article>

                <article
                    class="surface-card dashboard-enter bg-[#171715] p-5 text-white sm:p-6 dark:bg-[#efefe9] dark:text-[#171715]"
                    style="--delay: 340ms"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-white/10 text-accent dark:bg-black/10"
                        ><Home class="size-5"
                    /></span>
                    <p
                        class="mt-6 text-[10px] font-bold tracking-[0.12em] text-white/45 uppercase dark:text-black/45"
                    >
                        Active lease
                    </p>
                    <p class="mt-2 text-lg font-semibold">
                        {{ lease.lease_number }}
                    </p>
                    <p class="mt-2 text-xs text-white/55 dark:text-black/55">
                        Started {{ lease.start_date
                        }}<template v-if="lease.end_date">
                            · Ends {{ lease.end_date }}</template
                        >
                    </p>
                    <Link
                        href="/tenant/documents"
                        class="mt-8 inline-flex items-center gap-2 text-xs font-semibold text-accent"
                    >
                        View lease documents <ArrowRight class="size-3.5" />
                    </Link>
                </article>
            </section>
        </template>

        <section
            v-else
            class="surface-card soft-grid dashboard-enter relative mt-7 overflow-hidden p-7 sm:p-10"
            style="--delay: 90ms"
        >
            <div
                class="absolute -top-20 -right-10 size-64 rounded-full bg-accent/10 blur-3xl"
            />
            <div class="relative max-w-2xl">
                <span
                    class="flex size-12 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><Sparkles class="size-6"
                /></span>
                <p
                    class="mt-6 text-[10px] font-bold tracking-[0.12em] text-accent uppercase"
                >
                    Portal ready
                </p>
                <h2 class="mt-2 text-2xl font-semibold tracking-[-0.04em]">
                    Your account is awaiting a unit assignment
                </h2>
                <p class="mt-3 text-sm leading-7 text-muted-foreground">
                    Management will connect your apartment and lease. Once
                    assigned, this dashboard will show rent, due dates, payment
                    options, receipts, and property information automatically.
                </p>
                <Link
                    href="/tenant/messages"
                    class="mt-7 inline-flex h-10 items-center gap-2 rounded-full bg-foreground px-5 text-xs font-semibold text-background"
                >
                    <BellRing class="size-4" /> Contact management
                </Link>
            </div>
        </section>
    </div>
</template>
