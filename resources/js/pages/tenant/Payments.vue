<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownToLine,
    ArrowUpRight,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    CircleDollarSign,
    Clock3,
    CreditCard,
    FileText,
    Landmark,
    LockKeyhole,
    ReceiptText,
    ShieldCheck,
    Smartphone,
    Sparkles,
    WalletCards,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Resident portal', href: '/dashboard' },
            { title: 'Payments', href: '/tenant/payments' },
        ],
    },
});

type Invoice = {
    id: number;
    invoice_number: string;
    description: string;
    billing_month: string;
    issue_date: string;
    due_date: string;
    status: string;
    total_amount: number;
    amount_paid: number;
    balance_due: number;
    pdf_url: string;
    unit: { code: string; number: string; section: string; estate: string };
    settlement_account: {
        owner_name: string;
        bank_name: string;
        account_last_four: string;
    };
};

type Payment = {
    id: number;
    receipt_number: string;
    payment_number: string;
    payment_method: string;
    payment_method_label: string;
    provider_reference: string | null;
    amount: number;
    paid_at: string;
    invoice_number: string | null;
    estate: string | null;
    unit_code: string | null;
    settlement_account_last_four: string;
    receipt_url: string;
};

const props = defineProps<{
    tenant: { id: number; full_name: string } | null;
    summary: {
        outstanding: number;
        overdue: number;
        paid_total: number;
        next_due_date: string | null;
        next_due_amount: number;
        open_invoices: number;
    };
    invoices: Invoice[];
    payments: Payment[];
    gateway: { ready: boolean; name: string; methods: string[] };
}>();

const activeTab = ref<'invoices' | 'receipts'>('invoices');
const openInvoices = computed(() =>
    props.invoices.filter(
        (invoice) =>
            invoice.balance_due > 0 &&
            !['paid', 'void'].includes(invoice.status),
    ),
);

const formatMoney = (value: number): string =>
    `UGX ${new Intl.NumberFormat('en-UG', { maximumFractionDigits: 0 }).format(value)}`;

const statusLabel = (status: string): string =>
    (
        ({
            sent: 'Awaiting payment',
            overdue: 'Overdue',
            partially_paid: 'Partially paid',
            paid: 'Paid',
            void: 'Void',
        }) as Record<string, string>
    )[status] ?? status;

const statusClasses = (status: string): string =>
    (
        ({
            paid: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            overdue: 'bg-red-500/10 text-red-600 dark:text-red-400',
            partially_paid:
                'bg-amber-500/10 text-amber-600 dark:text-amber-400',
            sent: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
            void: 'bg-secondary text-muted-foreground',
        }) as Record<string, string>
    )[status] ?? 'bg-secondary text-muted-foreground';

const startPayment = (invoice?: Invoice): void => {
    if (!invoice && openInvoices.value.length === 0) {
        toast.info('You do not have an outstanding invoice');

        return;
    }

    if (!props.gateway.ready) {
        toast.info(
            'Online checkout is prepared and will activate when management connects the selected gateway.',
        );

        return;
    }

    toast.info('Your configured payment gateway will open here.');
};
</script>

<template>
    <Head title="Payments & receipts" />

    <div
        class="mx-auto w-full max-w-[1540px] px-4 py-5 sm:px-6 lg:px-8 lg:py-7"
    >
        <section
            class="dashboard-enter flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
            style="--delay: 20ms"
        >
            <div>
                <div
                    class="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground"
                >
                    <span>Resident portal</span>
                    <span class="text-muted-foreground/40">/</span>
                    <span class="text-foreground">Payments</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Payments & receipts
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Review rent due dates, choose a payment method, and keep
                    every verified invoice and receipt together.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_12px_28px_rgba(242,103,69,0.24)] transition-all hover:-translate-y-0.5 disabled:pointer-events-none disabled:opacity-50"
                :disabled="openInvoices.length === 0"
                @click="startPayment(openInvoices[0])"
            >
                <WalletCards class="size-4" /> Pay rent now
                <ArrowUpRight class="size-3.5" />
            </button>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 80ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><CircleDollarSign class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Outstanding balance
                </p>
                <p class="money mt-1.5 text-[1.55rem] font-semibold">
                    {{ formatMoney(summary.outstanding) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    {{ summary.open_invoices }} open
                    {{ summary.open_invoices === 1 ? 'invoice' : 'invoices' }}
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 130ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-red-500/10 text-red-600 dark:text-red-400"
                    ><AlertTriangle class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Overdue amount
                </p>
                <p
                    class="money mt-1.5 text-[1.55rem] font-semibold"
                    :class="
                        summary.overdue ? 'text-red-600 dark:text-red-400' : ''
                    "
                >
                    {{ formatMoney(summary.overdue) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    {{
                        summary.overdue
                            ? 'Please settle as soon as possible'
                            : 'Your account has no overdue rent'
                    }}
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 180ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    ><CheckCircle2 class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Total verified payments
                </p>
                <p class="money mt-1.5 text-[1.55rem] font-semibold">
                    {{ formatMoney(summary.paid_total) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    {{ payments.length }} downloadable
                    {{ payments.length === 1 ? 'receipt' : 'receipts' }}
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 230ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                    ><CalendarDays class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Next due date
                </p>
                <p
                    class="mt-1.5 text-[1.45rem] font-semibold tracking-[-0.04em]"
                >
                    {{ summary.next_due_date ?? 'All clear' }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    <template v-if="summary.next_due_date">
                        {{ formatMoney(summary.next_due_amount) }} due
                    </template>
                    <template v-else>No outstanding rent scheduled</template>
                </p>
            </article>
        </section>

        <section class="mt-4 grid gap-4 xl:grid-cols-[1.25fr_0.75fr]">
            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 280ms"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <Smartphone class="size-4 text-accent" />
                            <h2 class="text-sm font-semibold">
                                Choose how you pay
                            </h2>
                        </div>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            The correct owner account is selected from your
                            apartment automatically
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-[9px] font-bold"
                        :class="
                            gateway.ready
                                ? 'bg-emerald-500/10 text-emerald-600'
                                : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                        "
                    >
                        {{ gateway.ready ? 'Online' : 'Gateway setup' }}
                    </span>
                </header>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <button
                        v-for="method in [
                            {
                                key: 'mobile_money',
                                title: 'Mobile money',
                                note: 'MTN & Airtel ready',
                                icon: Smartphone,
                            },
                            {
                                key: 'bank_transfer',
                                title: 'Bank payment',
                                note: 'Verified transfer',
                                icon: Landmark,
                            },
                            {
                                key: 'card',
                                title: 'Visa / Mastercard',
                                note: 'Secure card checkout',
                                icon: CreditCard,
                            },
                        ]"
                        :key="method.key"
                        type="button"
                        class="group rounded-2xl border border-border/70 bg-muted/25 p-4 text-left transition-all hover:-translate-y-0.5 hover:border-accent/35 hover:bg-accent/[0.035] hover:shadow-md"
                        @click="startPayment(openInvoices[0])"
                    >
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-card text-accent shadow-sm"
                        >
                            <component :is="method.icon" class="size-4" />
                        </span>
                        <span class="mt-4 block text-xs font-semibold">{{
                            method.title
                        }}</span>
                        <span
                            class="mt-1 block text-[10px] text-muted-foreground"
                            >{{ method.note }}</span
                        >
                        <ChevronRight
                            class="mt-3 size-3.5 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-accent"
                        />
                    </button>
                </div>
            </article>

            <article
                class="surface-card dashboard-enter overflow-hidden bg-[#171715] p-5 text-white sm:p-6 dark:bg-[#efefe9] dark:text-[#171715]"
                style="--delay: 330ms"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-white/10 text-accent dark:bg-black/10"
                        ><ShieldCheck class="size-5"
                    /></span>
                    <LockKeyhole
                        class="size-4 text-white/35 dark:text-black/35"
                    />
                </div>
                <p class="mt-6 text-sm font-semibold">Protected collections</p>
                <p
                    class="mt-2 text-xs leading-6 text-white/55 dark:text-black/55"
                >
                    Amount, invoice, tenant, and owner account are fixed by the
                    system. Signed callbacks prevent duplicate or altered
                    payment confirmations.
                </p>
                <div
                    class="mt-5 flex items-center gap-2 rounded-2xl bg-white/[0.06] px-3.5 py-3 text-[10px] text-white/60 dark:bg-black/[0.06] dark:text-black/60"
                >
                    <Sparkles class="size-3.5 text-accent" /> Every confirmed
                    payment creates a receipt
                </div>
            </article>
        </section>

        <section
            class="surface-card dashboard-enter mt-4 overflow-hidden"
            style="--delay: 380ms"
        >
            <header
                class="flex flex-col justify-between gap-4 border-b border-border/70 p-5 sm:flex-row sm:items-center sm:px-6"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1.5 text-[10px] font-bold transition-colors"
                            :class="
                                activeTab === 'invoices'
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-secondary'
                            "
                            @click="activeTab = 'invoices'"
                        >
                            My invoices · {{ invoices.length }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1.5 text-[10px] font-bold transition-colors"
                            :class="
                                activeTab === 'receipts'
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-secondary'
                            "
                            @click="activeTab = 'receipts'"
                        >
                            Receipts · {{ payments.length }}
                        </button>
                    </div>
                    <p class="mt-2 text-[10px] text-muted-foreground">
                        Your private rent statement and verified transaction
                        history
                    </p>
                </div>
                <ReceiptText class="size-5 text-accent" />
            </header>

            <div v-if="activeTab === 'invoices'">
                <div v-if="invoices.length" class="divide-y divide-border/70">
                    <article
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="grid gap-4 p-5 transition-colors hover:bg-muted/20 sm:px-6 lg:grid-cols-[1.2fr_0.9fr_0.8fr_auto] lg:items-center"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xs font-semibold">
                                    {{ invoice.description }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-1 text-[8px] font-bold uppercase"
                                    :class="statusClasses(invoice.status)"
                                >
                                    {{ statusLabel(invoice.status) }}
                                </span>
                            </div>
                            <p class="mt-1.5 text-[10px] text-muted-foreground">
                                {{ invoice.invoice_number }} ·
                                {{ invoice.billing_month }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Due date
                            </p>
                            <p class="mt-1 text-xs font-semibold">
                                {{ invoice.due_date }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ invoice.unit.estate }} ·
                                {{ invoice.unit.code }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Balance
                            </p>
                            <p class="money mt-1 text-xs font-semibold">
                                {{ formatMoney(invoice.balance_due) }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ formatMoney(invoice.amount_paid) }} paid
                            </p>
                        </div>
                        <div class="flex items-center gap-2 lg:justify-end">
                            <a
                                :href="invoice.pdf_url"
                                class="inline-flex size-9 items-center justify-center rounded-full border border-border/70 transition-all hover:-translate-y-0.5 hover:border-accent/40 hover:text-accent"
                                title="Download invoice PDF"
                            >
                                <ArrowDownToLine class="size-4" />
                            </a>
                            <button
                                v-if="
                                    invoice.balance_due > 0 &&
                                    !['paid', 'void'].includes(invoice.status)
                                "
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-full bg-accent px-3.5 text-[10px] font-semibold text-white"
                                @click="startPayment(invoice)"
                            >
                                Pay now <ArrowUpRight class="size-3" />
                            </button>
                        </div>
                    </article>
                </div>
                <div v-else class="p-12 text-center">
                    <FileText class="mx-auto size-6 text-muted-foreground" />
                    <h3 class="mt-3 text-sm font-semibold">No invoices yet</h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Your rent invoices will appear here once management
                        starts billing.
                    </p>
                </div>
            </div>

            <div v-else>
                <div v-if="payments.length" class="divide-y divide-border/70">
                    <article
                        v-for="payment in payments"
                        :key="payment.id"
                        class="grid gap-4 p-5 transition-colors hover:bg-muted/20 sm:px-6 lg:grid-cols-[1.1fr_0.9fr_0.9fr_auto] lg:items-center"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <CheckCircle2 class="size-4 text-emerald-500" />
                                <p class="text-xs font-semibold">
                                    {{ payment.receipt_number }}
                                </p>
                            </div>
                            <p class="mt-1.5 text-[10px] text-muted-foreground">
                                {{ payment.invoice_number }} ·
                                {{ payment.estate }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Payment
                            </p>
                            <p class="mt-1 text-xs font-semibold">
                                {{ payment.payment_method_label }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ payment.paid_at }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Amount received
                            </p>
                            <p
                                class="money mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ formatMoney(payment.amount) }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                Account ••{{
                                    payment.settlement_account_last_four
                                }}
                            </p>
                        </div>
                        <a
                            :href="payment.receipt_url"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-full border border-border/70 px-3.5 text-[10px] font-semibold transition-all hover:-translate-y-0.5 hover:border-accent/40 hover:text-accent"
                        >
                            <ArrowDownToLine class="size-3.5" /> Receipt PDF
                        </a>
                    </article>
                </div>
                <div v-else class="p-12 text-center">
                    <Clock3 class="mx-auto size-6 text-muted-foreground" />
                    <h3 class="mt-3 text-sm font-semibold">
                        No payments recorded
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Confirmed collections and receipts will appear here.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
