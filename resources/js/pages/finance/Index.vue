<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    Banknote,
    BarChart3,
    Building2,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Copy,
    Download,
    FilePlus2,
    FileText,
    Landmark,
    Link2,
    Mail,
    Plus,
    ReceiptText,
    Search,
    ShieldCheck,
    WalletCards,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Finance', href: '/finance' },
            { title: 'Collections centre', href: '/finance' },
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
    due_date_label: string;
    age_days: number;
    status: string;
    total_amount: number;
    amount_paid: number;
    balance_due: number;
    pdf_url: string;
    payment_link: {
        id: number;
        url: string;
        expires_at: string | null;
        token_last_eight: string;
    } | null;
    tenant: {
        id: number;
        full_name: string;
        phone: string | null;
        email: string | null;
    };
    unit: {
        id: number;
        code: string;
        number: string;
        section: string;
        estate: string;
    };
    settlement_account: {
        id: number;
        label: string;
        owner_name: string;
        account_last_four: string;
    };
};

type Payment = {
    id: number;
    payment_number: string;
    receipt_number: string;
    payment_method: string;
    payment_method_label: string;
    provider_reference: string | null;
    amount: number;
    paid_at: string;
    paid_at_label: string;
    status: string;
    receipt_url: string;
    tenant: { id: number; full_name: string; email: string | null };
    invoice_number: string | null;
    estate: string | null;
    unit_code: string | null;
    settlement_account: {
        id: number;
        label: string;
        account_last_four: string;
    };
};

type Account = {
    id: number;
    label: string;
    owner_name: string;
    bank_name: string;
    account_last_four: string;
    currency: string;
    estates: string[];
    scheduled_monthly: number;
    invoiced: number;
    collected: number;
    outstanding: number;
    overdue: number;
    transaction_count: number;
};

const props = defineProps<{
    summary: {
        scheduled_monthly: number;
        invoiced: number;
        collected: number;
        outstanding: number;
        overdue: number;
        collection_rate: number;
        active_leases: number;
        unbilled_current: number;
    };
    aging: Array<{
        key: string;
        label: string;
        amount: number;
        count: number;
    }>;
    accounts: Account[];
    invoices: Invoice[];
    payments: Payment[];
    trend: Array<{
        key: string;
        label: string;
        invoiced: number;
        collected: number;
    }>;
    billing_periods: Array<{ value: string; label: string }>;
    current_billing_month: string;
    today: string;
}>();

const activeRegister = ref<'invoices' | 'payments'>('invoices');
const search = ref('');
const invoiceStatus = ref('all');
const generateDialogOpen = ref(false);
const paymentDialogOpen = ref(false);

const generateForm = useForm({
    billing_month: props.current_billing_month,
});

const paymentForm = useForm({
    invoice_id: '',
    amount: '',
    payment_method: 'mobile_money',
    provider_reference: '',
    paid_at: props.today,
    payer_phone: '',
    payer_email: '',
    card_brand: '',
    notes: '',
});

const paymentLinkForm = useForm({
    expires_in_days: 14,
});

const outstandingInvoices = computed(() =>
    props.invoices.filter(
        (invoice) =>
            invoice.balance_due > 0 &&
            !['paid', 'void'].includes(invoice.status),
    ),
);

const selectedInvoice = computed(
    () =>
        props.invoices.find(
            (invoice) => invoice.id === Number(paymentForm.invoice_id),
        ) ?? null,
);

watch(
    () => paymentForm.invoice_id,
    () => {
        if (!selectedInvoice.value) {
            return;
        }

        paymentForm.amount = String(selectedInvoice.value.balance_due);
        paymentForm.payer_phone = selectedInvoice.value.tenant.phone ?? '';
        paymentForm.payer_email = selectedInvoice.value.tenant.email ?? '';
    },
);

const filteredInvoices = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.invoices.filter((invoice) => {
        const matchesStatus =
            invoiceStatus.value === 'all' ||
            invoice.status === invoiceStatus.value;
        const matchesSearch =
            !term ||
            [
                invoice.invoice_number,
                invoice.tenant.full_name,
                invoice.unit.code,
                invoice.unit.estate,
                invoice.unit.section,
            ]
                .join(' ')
                .toLowerCase()
                .includes(term);

        return matchesStatus && matchesSearch;
    });
});

const filteredPayments = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.payments;
    }

    return props.payments.filter((payment) =>
        [
            payment.payment_number,
            payment.receipt_number,
            payment.provider_reference ?? '',
            payment.tenant.full_name,
            payment.invoice_number ?? '',
            payment.estate ?? '',
            payment.unit_code ?? '',
        ]
            .join(' ')
            .toLowerCase()
            .includes(term),
    );
});

const trendMax = computed(() =>
    Math.max(
        1,
        ...props.trend.flatMap((month) => [month.invoiced, month.collected]),
    ),
);

const agingTotal = computed(() =>
    props.aging.reduce((sum, bucket) => sum + bucket.amount, 0),
);

const formatMoney = (value: number, compact = false): string => {
    if (compact && value >= 1_000_000) {
        return `UGX ${(value / 1_000_000).toFixed(value % 1_000_000 === 0 ? 0 : 2)}M`;
    }

    return `UGX ${new Intl.NumberFormat('en-UG', { maximumFractionDigits: 0 }).format(value)}`;
};

const pluralize = (count: number, label: string): string =>
    `${count} ${label}${count === 1 ? '' : 's'}`;

const statusLabel = (status: string): string =>
    (
        ({
            sent: 'Sent',
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

const accountProgress = (account: Account): number =>
    account.invoiced > 0
        ? Math.min(
              100,
              Math.round((account.collected / account.invoiced) * 100),
          )
        : 0;

const trendHeight = (value: number): string =>
    `${value === 0 ? 4 : Math.max(8, (value / trendMax.value) * 100)}%`;

const agingWidth = (amount: number): string =>
    `${agingTotal.value === 0 ? 0 : Math.max(3, (amount / agingTotal.value) * 100)}%`;

const openPaymentDialog = (invoice?: Invoice): void => {
    const target = invoice ?? outstandingInvoices.value[0];

    if (!target) {
        toast.info('Generate an invoice before recording a payment');

        return;
    }

    paymentForm.clearErrors();
    paymentForm.invoice_id = String(target.id);
    paymentForm.amount = String(target.balance_due);
    paymentForm.paid_at = props.today;
    paymentDialogOpen.value = true;
};

const createPaymentLink = (invoice: Invoice): void => {
    paymentLinkForm.post(`/finance/invoices/${invoice.id}/payment-links`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Secure payment link created'),
    });
};

const emailInvoice = (invoice: Invoice): void => {
    router.post(
        `/finance/invoices/${invoice.id}/email`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Invoice email queued'),
        },
    );
};

const emailReceipt = (payment: Payment): void => {
    router.post(
        `/finance/payments/${payment.id}/email-receipt`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Receipt email queued'),
        },
    );
};

const copyPaymentLink = async (invoice: Invoice): Promise<void> => {
    if (!invoice.payment_link) {
        return;
    }

    await navigator.clipboard.writeText(invoice.payment_link.url);
    toast.success('Payment link copied');
};

const submitGeneration = (): void => {
    generateForm.post('/finance/invoices/generate', {
        preserveScroll: true,
        onSuccess: () => {
            generateDialogOpen.value = false;
            toast.success('Rent invoice run completed');
        },
    });
};

const submitPayment = (): void => {
    paymentForm.post('/finance/payments', {
        preserveScroll: true,
        onSuccess: () => {
            paymentDialogOpen.value = false;
            paymentForm.reset();
            paymentForm.paid_at = props.today;
            toast.success('Payment recorded and receipt created');
        },
    });
};
</script>

<template>
    <Head title="Finance & collections" />

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
                    <span>Finance</span>
                    <span class="text-muted-foreground/40">/</span>
                    <span class="text-foreground">Collections centre</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Finance & collections
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Issue monthly rent, monitor receivables by due date, record
                    every collection, and reconcile the two owner accounts from
                    one secure register.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                    @click="generateDialogOpen = true"
                >
                    <FilePlus2 class="size-4 text-accent" /> Generate invoices
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-4 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,0.22)] transition-all hover:-translate-y-0.5 disabled:pointer-events-none disabled:opacity-50"
                    :disabled="outstandingInvoices.length === 0"
                    @click="openPaymentDialog()"
                >
                    <Plus class="size-4" /> Record payment
                </button>
            </div>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 80ms"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    >
                        <CalendarDays class="size-5" />
                    </span>
                    <span
                        class="rounded-full bg-secondary px-2.5 py-1 text-[9px] font-bold text-muted-foreground"
                    >
                        {{ pluralize(summary.active_leases, 'active lease') }}
                    </span>
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Monthly rent schedule
                </p>
                <p class="money mt-1.5 text-[1.6rem] font-semibold">
                    {{ formatMoney(summary.scheduled_monthly, true) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    {{ pluralize(summary.unbilled_current, 'current lease') }}
                    not yet billed
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 130ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                >
                    <FileText class="size-5" />
                </span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Total rent invoiced
                </p>
                <p class="money mt-1.5 text-[1.6rem] font-semibold">
                    {{ formatMoney(summary.invoiced, true) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    {{ pluralize(invoices.length, 'traceable rent invoice') }}
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 180ms"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    >
                        <CheckCircle2 class="size-5" />
                    </span>
                    <span
                        class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-[9px] font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        {{ summary.collection_rate }}% collected
                    </span>
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Confirmed collections
                </p>
                <p class="money mt-1.5 text-[1.6rem] font-semibold">
                    {{ formatMoney(summary.collected, true) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    {{ pluralize(payments.length, 'recorded transaction') }}
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 230ms"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-red-500/10 text-red-600 dark:text-red-400"
                    >
                        <AlertTriangle class="size-5" />
                    </span>
                    <span
                        v-if="summary.overdue > 0"
                        class="rounded-full bg-red-500/10 px-2.5 py-1 text-[9px] font-bold text-red-600 dark:text-red-400"
                    >
                        {{ formatMoney(summary.overdue, true) }} overdue
                    </span>
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Outstanding balance
                </p>
                <p class="money mt-1.5 text-[1.6rem] font-semibold">
                    {{ formatMoney(summary.outstanding, true) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Current and overdue receivables
                </p>
            </article>
        </section>

        <section class="mt-4 grid gap-4 xl:grid-cols-[1.45fr_1fr]">
            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 280ms"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <BarChart3 class="size-4 text-accent" />
                            <h2 class="text-sm font-semibold">
                                Six-month collection movement
                            </h2>
                        </div>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Rent issued against confirmed payments
                        </p>
                    </div>
                    <div
                        class="flex items-center gap-3 text-[9px] font-semibold"
                    >
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-accent" />
                            Invoiced
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span
                                class="size-2 rounded-full bg-[#171715] dark:bg-[#ecece6]"
                            />
                            Collected
                        </span>
                    </div>
                </header>

                <div
                    class="mt-7 grid h-48 grid-cols-6 items-end gap-3 sm:gap-5"
                >
                    <div
                        v-for="month in trend"
                        :key="month.key"
                        class="flex h-full min-w-0 flex-col justify-end"
                    >
                        <div
                            class="flex h-[calc(100%-1.8rem)] items-end justify-center gap-1.5"
                        >
                            <div
                                class="w-3 rounded-t-full bg-accent/80 transition-all duration-700 sm:w-4"
                                :style="{ height: trendHeight(month.invoiced) }"
                                :title="`Invoiced ${formatMoney(month.invoiced)}`"
                            />
                            <div
                                class="w-3 rounded-t-full bg-[#171715] transition-all duration-700 sm:w-4 dark:bg-[#ecece6]"
                                :style="{
                                    height: trendHeight(month.collected),
                                }"
                                :title="`Collected ${formatMoney(month.collected)}`"
                            />
                        </div>
                        <p
                            class="mt-2 text-center text-[9px] font-semibold text-muted-foreground"
                        >
                            {{ month.label }}
                        </p>
                    </div>
                </div>
            </article>

            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 330ms"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <Clock3 class="size-4 text-accent" />
                            <h2 class="text-sm font-semibold">
                                Receivables aging
                            </h2>
                        </div>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Grouped strictly from each invoice due date
                        </p>
                    </div>
                    <p class="money text-sm font-semibold">
                        {{ formatMoney(agingTotal, true) }}
                    </p>
                </header>

                <div class="mt-6 space-y-3.5">
                    <div v-for="(bucket, index) in aging" :key="bucket.key">
                        <div
                            class="mb-1.5 flex items-center justify-between gap-3"
                        >
                            <span class="text-[10px] font-medium">
                                {{ bucket.label }}
                                <span class="text-muted-foreground"
                                    >· {{ bucket.count }}</span
                                >
                            </span>
                            <span class="money text-[10px] font-semibold">
                                {{ formatMoney(bucket.amount, true) }}
                            </span>
                        </div>
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-700"
                                :class="
                                    [
                                        'bg-sky-500',
                                        'bg-amber-400',
                                        'bg-orange-500',
                                        'bg-red-500',
                                        'bg-red-800',
                                    ][index]
                                "
                                :style="{ width: agingWidth(bucket.amount) }"
                            />
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-4 grid gap-4 xl:grid-cols-2">
            <article
                v-for="(account, index) in accounts"
                :key="account.id"
                class="surface-card card-lift dashboard-enter overflow-hidden p-5 sm:p-6"
                :style="{ '--delay': `${370 + index * 45}ms` }"
            >
                <header class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-11 items-center justify-center rounded-2xl bg-[#171715] text-white dark:bg-[#efefe9] dark:text-[#171715]"
                        >
                            <Landmark class="size-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold">
                                {{ account.owner_name }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ account.bank_name }} ·••{{
                                    account.account_last_four
                                }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-[9px] font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        Routed automatically
                    </span>
                </header>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span
                        v-for="estate in account.estates"
                        :key="estate"
                        class="rounded-full bg-secondary px-2.5 py-1 text-[9px] font-semibold text-muted-foreground"
                    >
                        {{ estate }}
                    </span>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Schedule
                        </p>
                        <p class="money mt-1.5 text-xs font-semibold">
                            {{ formatMoney(account.scheduled_monthly, true) }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Invoiced
                        </p>
                        <p class="money mt-1.5 text-xs font-semibold">
                            {{ formatMoney(account.invoiced, true) }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Collected
                        </p>
                        <p
                            class="money mt-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                        >
                            {{ formatMoney(account.collected, true) }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Outstanding
                        </p>
                        <p
                            class="money mt-1.5 text-xs font-semibold"
                            :class="
                                account.overdue
                                    ? 'text-red-600 dark:text-red-400'
                                    : ''
                            "
                        >
                            {{ formatMoney(account.outstanding, true) }}
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <div
                        class="mb-2 flex items-center justify-between text-[9px] font-semibold"
                    >
                        <span class="text-muted-foreground">
                            {{
                                pluralize(
                                    account.transaction_count,
                                    'confirmed transaction',
                                )
                            }}
                        </span>
                        <span>{{ accountProgress(account) }}% collected</span>
                    </div>
                    <div
                        class="h-1.5 overflow-hidden rounded-full bg-secondary"
                    >
                        <div
                            class="h-full rounded-full bg-accent transition-all duration-700"
                            :style="{ width: `${accountProgress(account)}%` }"
                        />
                    </div>
                </div>
            </article>
        </section>

        <section
            class="surface-card dashboard-enter mt-4 overflow-hidden"
            style="--delay: 470ms"
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
                                activeRegister === 'invoices'
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-secondary'
                            "
                            @click="activeRegister = 'invoices'"
                        >
                            Invoice register · {{ invoices.length }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1.5 text-[10px] font-bold transition-colors"
                            :class="
                                activeRegister === 'payments'
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-secondary'
                            "
                            @click="activeRegister = 'payments'"
                        >
                            Transactions · {{ payments.length }}
                        </button>
                    </div>
                    <p class="mt-2 text-[10px] text-muted-foreground">
                        Complete audit trail across every tenant, unit, and
                        owner account
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <select
                        v-if="activeRegister === 'invoices'"
                        v-model="invoiceStatus"
                        aria-label="Filter invoice status"
                        class="h-10 rounded-xl border border-border/70 bg-muted/45 px-3 text-xs outline-none focus:border-accent/50"
                    >
                        <option value="all">All statuses</option>
                        <option value="sent">Sent</option>
                        <option value="partially_paid">Partially paid</option>
                        <option value="overdue">Overdue</option>
                        <option value="paid">Paid</option>
                    </select>
                    <label class="relative block w-full sm:w-64">
                        <Search
                            class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="search"
                            type="search"
                            aria-label="Search finance register"
                            placeholder="Search register..."
                            class="h-10 w-full rounded-xl border border-border/70 bg-muted/45 pr-4 pl-10 text-xs outline-none focus:border-accent/50 focus:ring-3 focus:ring-accent/10"
                        />
                    </label>
                </div>
            </header>

            <div v-if="activeRegister === 'invoices'">
                <div
                    v-if="filteredInvoices.length"
                    class="divide-y divide-border/70"
                >
                    <article
                        v-for="invoice in filteredInvoices"
                        :key="invoice.id"
                        class="grid gap-4 p-5 transition-colors hover:bg-muted/25 sm:px-6 lg:grid-cols-[1.15fr_1fr_0.8fr_0.9fr_auto] lg:items-center"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-xs font-semibold">
                                    {{ invoice.tenant.full_name }}
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
                                Apartment
                            </p>
                            <p
                                class="mt-1 flex items-center gap-1.5 text-xs font-semibold"
                            >
                                <Building2 class="size-3.5 text-accent" />
                                {{ invoice.unit.estate }} ·
                                {{ invoice.unit.number }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ invoice.unit.section }} ·
                                {{ invoice.unit.code }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Due date
                            </p>
                            <p class="mt-1 text-xs font-semibold">
                                {{ invoice.due_date_label }}
                            </p>
                            <p
                                class="mt-1 text-[10px]"
                                :class="
                                    invoice.age_days
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    invoice.age_days
                                        ? `${invoice.age_days} days overdue`
                                        : 'Current'
                                }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Balance due
                            </p>
                            <p class="money mt-1 text-xs font-semibold">
                                {{ formatMoney(invoice.balance_due) }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ formatMoney(invoice.amount_paid) }} received
                            </p>
                        </div>

                        <div class="flex items-center gap-2 lg:justify-end">
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-1.5 text-[9px] font-bold text-muted-foreground"
                                :title="invoice.settlement_account.owner_name"
                            >
                                <ShieldCheck class="size-3" /> ••{{
                                    invoice.settlement_account.account_last_four
                                }}
                            </span>
                            <a
                                :href="invoice.pdf_url"
                                class="inline-flex size-8 items-center justify-center rounded-full border border-border/70 transition-all hover:-translate-y-0.5 hover:border-accent/40 hover:text-accent"
                                title="Download invoice PDF"
                            >
                                <Download class="size-3.5" />
                            </a>
                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full border border-border/70 transition-all hover:-translate-y-0.5 hover:border-accent/40 hover:text-accent disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="!invoice.tenant.email"
                                :title="
                                    invoice.tenant.email
                                        ? `Email invoice to ${invoice.tenant.email}`
                                        : 'Tenant has no email address'
                                "
                                @click="emailInvoice(invoice)"
                            >
                                <Mail class="size-3.5" />
                            </button>
                            <button
                                v-if="invoice.payment_link"
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full border border-accent/25 bg-accent/[0.06] text-accent transition-all hover:-translate-y-0.5 hover:bg-accent/10"
                                :title="`Copy payment link · expires ${invoice.payment_link.expires_at}`"
                                @click="copyPaymentLink(invoice)"
                            >
                                <Copy class="size-3.5" />
                            </button>
                            <button
                                v-else-if="
                                    invoice.balance_due > 0 &&
                                    !['paid', 'void'].includes(invoice.status)
                                "
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full border border-border/70 transition-all hover:-translate-y-0.5 hover:border-accent/40 hover:text-accent disabled:opacity-50"
                                title="Create secure payment link"
                                :disabled="paymentLinkForm.processing"
                                @click="createPaymentLink(invoice)"
                            >
                                <Link2 class="size-3.5" />
                            </button>
                            <button
                                v-if="
                                    invoice.balance_due > 0 &&
                                    !['paid', 'void'].includes(invoice.status)
                                "
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full border border-border/70 transition-all hover:-translate-y-0.5 hover:border-accent/40 hover:text-accent"
                                title="Record payment"
                                @click="openPaymentDialog(invoice)"
                            >
                                <ArrowUpRight class="size-3.5" />
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else class="p-10 text-center sm:p-14">
                    <span
                        class="mx-auto flex size-14 items-center justify-center rounded-[1.15rem] bg-accent/10 text-accent"
                    >
                        <ReceiptText class="size-6" />
                    </span>
                    <h3 class="mt-4 text-base font-semibold">
                        {{
                            invoices.length
                                ? 'No invoices match these filters'
                                : 'No rent invoices yet'
                        }}
                    </h3>
                    <p
                        class="mx-auto mt-2 max-w-md text-xs leading-6 text-muted-foreground"
                    >
                        {{
                            invoices.length
                                ? 'Try another tenant, unit, estate, invoice number, or status.'
                                : 'When active leases are ready, generate the selected month once. Existing invoices are safely skipped.'
                        }}
                    </p>
                    <button
                        v-if="!invoices.length"
                        type="button"
                        class="mt-6 inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                        @click="generateDialogOpen = true"
                    >
                        <FilePlus2 class="size-4" /> Generate first invoices
                    </button>
                </div>
            </div>

            <div v-else>
                <div
                    v-if="filteredPayments.length"
                    class="divide-y divide-border/70"
                >
                    <article
                        v-for="payment in filteredPayments"
                        :key="payment.id"
                        class="grid gap-4 p-5 transition-colors hover:bg-muted/25 sm:px-6 lg:grid-cols-[1.1fr_0.9fr_0.9fr_0.9fr_auto] lg:items-center"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex size-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                >
                                    <Banknote class="size-4" />
                                </span>
                                <div>
                                    <p class="text-xs font-semibold">
                                        {{ payment.tenant.full_name }}
                                    </p>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        {{ payment.receipt_number }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p
                                class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Payment method
                            </p>
                            <p class="mt-1 text-xs font-semibold">
                                {{ payment.payment_method_label }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{
                                    payment.provider_reference ||
                                    'Manual record'
                                }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Allocation
                            </p>
                            <p class="mt-1 text-xs font-semibold">
                                {{ payment.invoice_number }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ payment.estate }} · {{ payment.unit_code }}
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
                                {{ payment.paid_at_label }}
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-2 lg:justify-self-end"
                        >
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-1.5 text-[9px] font-bold text-muted-foreground"
                            >
                                <Landmark class="size-3" /> ••{{
                                    payment.settlement_account.account_last_four
                                }}
                            </span>
                            <a
                                :href="payment.receipt_url"
                                class="inline-flex size-8 items-center justify-center rounded-full border border-border/70 transition-all hover:-translate-y-0.5 hover:border-accent/40 hover:text-accent"
                                title="Download receipt PDF"
                            >
                                <Download class="size-3.5" />
                            </a>
                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full border border-border/70 transition-all hover:-translate-y-0.5 hover:border-accent/40 hover:text-accent disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="!payment.tenant.email"
                                :title="
                                    payment.tenant.email
                                        ? `Email receipt to ${payment.tenant.email}`
                                        : 'Tenant has no email address'
                                "
                                @click="emailReceipt(payment)"
                            >
                                <Mail class="size-3.5" />
                            </button>
                        </div>
                    </article>
                </div>
                <div v-else class="p-10 text-center sm:p-14">
                    <span
                        class="mx-auto flex size-14 items-center justify-center rounded-[1.15rem] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    >
                        <WalletCards class="size-6" />
                    </span>
                    <h3 class="mt-4 text-base font-semibold">
                        No payment transactions yet
                    </h3>
                    <p
                        class="mx-auto mt-2 max-w-md text-xs leading-6 text-muted-foreground"
                    >
                        Confirmed mobile money, bank, card, link, cash, and
                        other collections will appear here with receipt and
                        settlement routing.
                    </p>
                </div>
            </div>
        </section>
    </div>

    <Dialog v-model:open="generateDialogOpen">
        <DialogContent
            class="rounded-[1.5rem] border-border/70 p-0 sm:max-w-lg"
        >
            <DialogHeader
                class="border-b border-border/70 px-6 pt-6 pb-5 text-left"
            >
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                >
                    <FilePlus2 class="size-5" />
                </span>
                <DialogTitle class="text-xl tracking-[-0.035em]"
                    >Generate monthly rent</DialogTitle
                >
                <DialogDescription class="text-xs leading-5">
                    Create one rent invoice for every eligible active lease in
                    the selected month.
                </DialogDescription>
            </DialogHeader>

            <form
                class="space-y-5 px-6 py-5"
                @submit.prevent="submitGeneration"
            >
                <div class="space-y-2">
                    <Label for="billing-month">Billing month</Label>
                    <select
                        id="billing-month"
                        v-model="generateForm.billing_month"
                        class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                    >
                        <option
                            v-for="period in billing_periods"
                            :key="period.value"
                            :value="period.value"
                        >
                            {{ period.label }}
                        </option>
                    </select>
                    <InputError :message="generateForm.errors.billing_month" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-muted/55 p-4">
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Eligible leases
                        </p>
                        <p class="money mt-1.5 text-lg font-semibold">
                            {{ summary.active_leases }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-muted/55 p-4">
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Current unbilled
                        </p>
                        <p class="money mt-1.5 text-lg font-semibold">
                            {{ summary.unbilled_current }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-start gap-3 rounded-2xl bg-emerald-500/[0.07] p-4 text-xs leading-5 text-muted-foreground"
                >
                    <ShieldCheck
                        class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    Safe to run again: invoices that already exist for the lease
                    and month are skipped. Owner routing comes from the unit’s
                    estate.
                </div>

                <DialogFooter class="border-t border-border/70 pt-5">
                    <button
                        type="button"
                        class="h-10 rounded-full px-4 text-xs font-semibold hover:bg-secondary"
                        @click="generateDialogOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white disabled:opacity-50"
                        :disabled="generateForm.processing"
                    >
                        <FilePlus2 class="size-4" />
                        {{
                            generateForm.processing
                                ? 'Generating...'
                                : 'Generate invoices'
                        }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="paymentDialogOpen">
        <DialogContent
            class="max-h-[calc(100svh-2rem)] overflow-y-auto rounded-[1.5rem] border-border/70 p-0 sm:max-w-2xl"
        >
            <DialogHeader
                class="border-b border-border/70 px-6 pt-6 pb-5 text-left"
            >
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                >
                    <WalletCards class="size-5" />
                </span>
                <DialogTitle class="text-xl tracking-[-0.035em]"
                    >Record a rent payment</DialogTitle
                >
                <DialogDescription class="text-xs leading-5">
                    Allocate a confirmed collection to its invoice and estate
                    settlement account.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5 px-6 py-5" @submit.prevent="submitPayment">
                <div class="space-y-2">
                    <Label for="payment-invoice">Outstanding invoice</Label>
                    <select
                        id="payment-invoice"
                        v-model="paymentForm.invoice_id"
                        class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                    >
                        <option value="" disabled>Select an invoice</option>
                        <option
                            v-for="invoice in outstandingInvoices"
                            :key="invoice.id"
                            :value="String(invoice.id)"
                        >
                            {{ invoice.invoice_number }} ·
                            {{ invoice.tenant.full_name }} ·
                            {{ invoice.unit.estate }}
                            {{ invoice.unit.number }} ·
                            {{ formatMoney(invoice.balance_due) }}
                        </option>
                    </select>
                    <InputError :message="paymentForm.errors.invoice_id" />
                </div>

                <div
                    v-if="selectedInvoice"
                    class="grid gap-3 rounded-[1.2rem] border border-border/70 bg-muted/25 p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div>
                        <p class="text-xs font-semibold">
                            {{ selectedInvoice.tenant.full_name }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ selectedInvoice.unit.estate }} ·
                            {{ selectedInvoice.unit.section }} ·
                            {{ selectedInvoice.unit.code }}
                        </p>
                    </div>
                    <div class="sm:text-right">
                        <p class="money text-sm font-semibold">
                            {{ formatMoney(selectedInvoice.balance_due) }}
                        </p>
                        <p class="mt-1 text-[9px] text-muted-foreground">
                            Due {{ selectedInvoice.due_date_label }}
                        </p>
                    </div>
                    <div
                        class="flex items-center gap-2 border-t border-border/60 pt-3 sm:col-span-2"
                    >
                        <Landmark class="size-3.5 text-accent" />
                        <p class="text-[10px] text-muted-foreground">
                            Route to
                            <span class="font-semibold text-foreground">{{
                                selectedInvoice.settlement_account.owner_name
                            }}</span>
                            · account ending
                            {{
                                selectedInvoice.settlement_account
                                    .account_last_four
                            }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="payment-amount"
                            >Amount received (UGX)</Label
                        >
                        <Input
                            id="payment-amount"
                            v-model="paymentForm.amount"
                            type="number"
                            min="1"
                            inputmode="numeric"
                            class="h-11 rounded-xl"
                            placeholder="0"
                        />
                        <InputError :message="paymentForm.errors.amount" />
                    </div>
                    <div class="space-y-2">
                        <Label for="payment-date">Payment date</Label>
                        <Input
                            id="payment-date"
                            v-model="paymentForm.paid_at"
                            type="date"
                            :max="today"
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="paymentForm.errors.paid_at" />
                    </div>
                    <div class="space-y-2">
                        <Label for="payment-method">Payment method</Label>
                        <select
                            id="payment-method"
                            v-model="paymentForm.payment_method"
                            class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        >
                            <option value="mobile_money">Mobile money</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="card">Visa / Mastercard</option>
                            <option value="payment_link">Payment link</option>
                            <option value="cash">Cash</option>
                            <option value="other">Other</option>
                        </select>
                        <InputError
                            :message="paymentForm.errors.payment_method"
                        />
                    </div>
                    <div
                        v-if="paymentForm.payment_method === 'card'"
                        class="space-y-2"
                    >
                        <Label for="card-brand">Card brand</Label>
                        <select
                            id="card-brand"
                            v-model="paymentForm.card_brand"
                            class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        >
                            <option value="" disabled>Select card brand</option>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                        </select>
                        <InputError :message="paymentForm.errors.card_brand" />
                    </div>
                    <div
                        v-if="
                            !['cash', 'other'].includes(
                                paymentForm.payment_method,
                            )
                        "
                        class="space-y-2"
                    >
                        <Label for="provider-reference"
                            >Provider reference</Label
                        >
                        <Input
                            id="provider-reference"
                            v-model="paymentForm.provider_reference"
                            autocomplete="off"
                            class="h-11 rounded-xl"
                            placeholder="Transaction or bank reference"
                        />
                        <InputError
                            :message="paymentForm.errors.provider_reference"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="payer-phone">Payer phone</Label>
                        <Input
                            id="payer-phone"
                            v-model="paymentForm.payer_phone"
                            type="tel"
                            class="h-11 rounded-xl"
                            placeholder="Optional"
                        />
                        <InputError :message="paymentForm.errors.payer_phone" />
                    </div>
                    <div class="space-y-2">
                        <Label for="payer-email">Payer email</Label>
                        <Input
                            id="payer-email"
                            v-model="paymentForm.payer_email"
                            type="email"
                            class="h-11 rounded-xl"
                            placeholder="Optional"
                        />
                        <InputError :message="paymentForm.errors.payer_email" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="payment-notes">Internal notes</Label>
                        <textarea
                            id="payment-notes"
                            v-model="paymentForm.notes"
                            rows="2"
                            placeholder="Optional reconciliation notes..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        />
                        <InputError :message="paymentForm.errors.notes" />
                    </div>
                </div>

                <div
                    class="flex items-start gap-3 rounded-2xl bg-muted/55 p-4 text-xs leading-5 text-muted-foreground"
                >
                    <ReceiptText class="mt-0.5 size-4 shrink-0 text-accent" />
                    Confirmation creates an immutable payment record,
                    allocation, payment number, and receipt number. Gateway
                    callbacks will use the same ledger later.
                </div>

                <DialogFooter class="border-t border-border/70 pt-5">
                    <button
                        type="button"
                        class="h-10 rounded-full px-4 text-xs font-semibold hover:bg-secondary"
                        @click="paymentDialogOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white disabled:opacity-50"
                        :disabled="paymentForm.processing"
                    >
                        <ReceiptText class="size-4" />
                        {{
                            paymentForm.processing
                                ? 'Recording...'
                                : 'Confirm & create receipt'
                        }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
