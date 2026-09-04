<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    FilePlus2,
    FileText,
    Mail,
    Plus,
    ReceiptText,
    Search,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Finance', href: '/finance' },
            { title: 'Billing operations', href: '/finance' },
        ],
    },
});

type Invoice = {
    id: number;
    invoice_number: string;
    billing_month: string;
    due_date_label: string;
    status: string;
    total_amount: number;
    balance_due: number;
    pdf_url: string;
    tenant: string;
    tenant_email: string | null;
    unit: string;
    estate: string;
};
type Payment = {
    id: number;
    receipt_number: string;
    amount: number;
    paid_at_label: string;
    payment_method_label: string;
    status: string;
    receipt_url: string;
    tenant: string;
    invoice_number: string | null;
    unit: string | null;
    estate: string | null;
};

const props = defineProps<{
    counts: {
        active_leases: number;
        invoices: number;
        payments: number;
        overdue: number;
        unbilled_current: number;
    };
    invoices: Invoice[];
    payments: Payment[];
    billing_periods: Array<{ value: string; label: string }>;
    current_billing_month: string;
    today: string;
}>();

const activeRegister = ref<'invoices' | 'payments'>('invoices');
const search = ref('');
const generateOpen = ref(false);
const paymentOpen = ref(false);
const generateForm = useForm({ billing_month: props.current_billing_month });
const paymentForm = useForm({
    invoice_id: '' as string | number,
    amount: '' as string | number,
    payment_method: 'mobile_money',
    provider_reference: '',
    paid_at: props.today,
    payer_phone: '',
    payer_email: '',
    notes: '',
});

const openInvoices = computed(() =>
    props.invoices.filter(
        (invoice) => invoice.balance_due > 0 && invoice.status !== 'void',
    ),
);
const selectedInvoice = computed(() =>
    openInvoices.value.find(
        (invoice) => invoice.id === Number(paymentForm.invoice_id),
    ),
);
watch(selectedInvoice, (invoice) => {
    if (invoice) {
        paymentForm.amount = invoice.balance_due;
    }
});
const filteredInvoices = computed(() => {
    const term = search.value.toLowerCase().trim();

    return props.invoices.filter(
        (item) =>
            !term ||
            `${item.invoice_number} ${item.tenant} ${item.unit} ${item.estate}`
                .toLowerCase()
                .includes(term),
    );
});
const filteredPayments = computed(() => {
    const term = search.value.toLowerCase().trim();

    return props.payments.filter(
        (item) =>
            !term ||
            `${item.receipt_number} ${item.tenant} ${item.unit ?? ''} ${item.estate ?? ''}`
                .toLowerCase()
                .includes(term),
    );
});
const formatMoney = (value: number): string =>
    `UGX ${new Intl.NumberFormat('en-UG').format(value)}`;
const statusClass = (status: string): string =>
    status === 'paid' || status === 'confirmed'
        ? 'bg-emerald-500/10 text-emerald-600'
        : status === 'overdue'
          ? 'bg-red-500/10 text-red-600'
          : 'bg-amber-500/10 text-amber-600';
const generate = (): void =>
    generateForm.post('/finance/invoices/generate', {
        preserveScroll: true,
        onSuccess: () => (generateOpen.value = false),
    });
const recordPayment = (): void =>
    paymentForm.post('/finance/payments', {
        preserveScroll: true,
        onSuccess: () => {
            paymentOpen.value = false;
            paymentForm.reset();
        },
    });
const emailInvoice = (invoice: Invoice): void =>
    router.post(
        `/finance/invoices/${invoice.id}/email`,
        {},
        { preserveScroll: true },
    );
const emailReceipt = (payment: Payment): void =>
    router.post(
        `/finance/payments/${payment.id}/email-receipt`,
        {},
        { preserveScroll: true },
    );
</script>

<template>
    <Head title="Billing operations" />
    <div
        class="mx-auto w-full max-w-[1540px] px-4 py-5 sm:px-6 lg:px-8 lg:py-7"
    >
        <section
            class="dashboard-enter flex flex-col justify-between gap-5 xl:flex-row xl:items-end"
            style="--delay: 20ms"
        >
            <div>
                <div
                    class="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground"
                >
                    <span>Finance</span><span class="opacity-40">/</span
                    ><span class="text-foreground">Billing operations</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Rent billing workspace
                </h1>
                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    Issue invoices, record tenant payments and send documents.
                    Portfolio totals and owner account analytics remain private
                    to Super Admins.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    class="inline-flex h-11 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm"
                    @click="generateOpen = true"
                >
                    <FilePlus2 class="size-4" /> Generate invoices</button
                ><button
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,.22)]"
                    @click="paymentOpen = true"
                >
                    <Plus class="size-4" /> Record payment
                </button>
            </div>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="(card, index) in [
                    {
                        label: 'Active leases',
                        value: counts.active_leases,
                        note: `${counts.unbilled_current} awaiting this month’s invoice`,
                        icon: FileText,
                        color: 'bg-accent/10 text-accent',
                    },
                    {
                        label: 'Invoices issued',
                        value: counts.invoices,
                        note: 'Traceable billing documents',
                        icon: ReceiptText,
                        color: 'bg-sky-500/10 text-sky-600',
                    },
                    {
                        label: 'Payments recorded',
                        value: counts.payments,
                        note: 'Confirmed rent transactions',
                        icon: CheckCircle2,
                        color: 'bg-emerald-500/10 text-emerald-600',
                    },
                    {
                        label: 'Overdue invoices',
                        value: counts.overdue,
                        note: 'Require tenant follow-up',
                        icon: AlertTriangle,
                        color: counts.overdue
                            ? 'bg-red-500/10 text-red-600'
                            : 'bg-amber-500/10 text-amber-600',
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
                <p class="money mt-1.5 text-[1.6rem] font-semibold">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">
                    {{ card.note }}
                </p>
            </article>
        </section>

        <div
            class="mt-4 flex items-center gap-2 rounded-2xl border border-sky-500/15 bg-sky-500/5 px-4 py-3 text-[10px] text-sky-700 dark:text-sky-300"
        >
            <ShieldCheck class="size-4 shrink-0" /><span
                ><strong>Operational view:</strong> individual invoice amounts
                are available for correct billing, while collection totals,
                trends, settlement accounts and complete financial analytics are
                not sent to this account.</span
            >
        </div>

        <section
            class="surface-card dashboard-enter mt-5 overflow-hidden"
            style="--delay: 280ms"
        >
            <header
                class="flex flex-col gap-3 border-b border-border/70 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div class="flex rounded-xl bg-secondary p-1">
                    <button
                        class="rounded-lg px-3 py-2 text-[10px] font-semibold"
                        :class="
                            activeRegister === 'invoices'
                                ? 'bg-card shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="activeRegister = 'invoices'"
                    >
                        Invoices ({{ invoices.length }})</button
                    ><button
                        class="rounded-lg px-3 py-2 text-[10px] font-semibold"
                        :class="
                            activeRegister === 'payments'
                                ? 'bg-card shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="activeRegister = 'payments'"
                    >
                        Payments ({{ payments.length }})
                    </button>
                </div>
                <label class="relative"
                    ><Search
                        class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" /><input
                        v-model="search"
                        class="h-10 w-60 rounded-xl border border-border/70 bg-background pr-3 pl-9 text-[10px]"
                        placeholder="Search records…"
                /></label>
            </header>
            <div
                v-if="activeRegister === 'invoices'"
                class="divide-y divide-border/70"
            >
                <div
                    v-for="invoice in filteredInvoices"
                    :key="invoice.id"
                    class="grid gap-4 px-5 py-5 sm:px-6 xl:grid-cols-[1.1fr_.75fr_.65fr_.65fr_auto] xl:items-center"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-xs font-semibold">
                                {{ invoice.tenant }}
                            </p>
                            <span
                                class="rounded-full px-2 py-0.5 text-[8px] font-bold"
                                :class="statusClass(invoice.status)"
                                >{{ invoice.status }}</span
                            >
                        </div>
                        <p class="mt-1 text-[9px] text-muted-foreground">
                            {{ invoice.invoice_number }} ·
                            {{ invoice.billing_month }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Apartment</p>
                        <p class="mt-1 text-[10px] font-semibold">
                            {{ invoice.estate }} · {{ invoice.unit }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Due</p>
                        <p class="mt-1 text-[10px] font-semibold">
                            {{ invoice.due_date_label }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Balance</p>
                        <p class="money mt-1 text-[10px] font-semibold">
                            {{ formatMoney(invoice.balance_due) }}
                        </p>
                    </div>
                    <div class="flex gap-1.5">
                        <a
                            :href="invoice.pdf_url"
                            class="flex size-9 items-center justify-center rounded-xl border border-border/70 hover:bg-secondary"
                            title="Download invoice"
                            ><Download class="size-3.5" /></a
                        ><button
                            class="flex size-9 items-center justify-center rounded-xl border border-border/70 hover:bg-secondary"
                            title="Email invoice"
                            :disabled="!invoice.tenant_email"
                            @click="emailInvoice(invoice)"
                        >
                            <Mail class="size-3.5" />
                        </button>
                    </div>
                </div>
                <div
                    v-if="!filteredInvoices.length"
                    class="py-14 text-center text-[10px] text-muted-foreground"
                >
                    No matching invoices.
                </div>
            </div>
            <div v-else class="divide-y divide-border/70">
                <div
                    v-for="payment in filteredPayments"
                    :key="payment.id"
                    class="grid gap-4 px-5 py-5 sm:px-6 xl:grid-cols-[1.1fr_.75fr_.65fr_.65fr_auto] xl:items-center"
                >
                    <div>
                        <p class="text-xs font-semibold">
                            {{ payment.tenant }}
                        </p>
                        <p class="mt-1 text-[9px] text-muted-foreground">
                            {{ payment.receipt_number }} ·
                            {{ payment.invoice_number || 'Unallocated' }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Apartment</p>
                        <p class="mt-1 text-[10px] font-semibold">
                            {{ payment.estate || '—' }} ·
                            {{ payment.unit || '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Paid on</p>
                        <p class="mt-1 text-[10px] font-semibold">
                            {{ payment.paid_at_label }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Amount</p>
                        <p
                            class="money mt-1 text-[10px] font-semibold text-emerald-600"
                        >
                            {{ formatMoney(payment.amount) }}
                        </p>
                    </div>
                    <div class="flex gap-1.5">
                        <a
                            :href="payment.receipt_url"
                            class="flex size-9 items-center justify-center rounded-xl border border-border/70 hover:bg-secondary"
                            title="Download receipt"
                            ><Download class="size-3.5" /></a
                        ><button
                            class="flex size-9 items-center justify-center rounded-xl border border-border/70 hover:bg-secondary"
                            title="Email receipt"
                            @click="emailReceipt(payment)"
                        >
                            <Mail class="size-3.5" />
                        </button>
                    </div>
                </div>
                <div
                    v-if="!filteredPayments.length"
                    class="py-14 text-center text-[10px] text-muted-foreground"
                >
                    No matching payments.
                </div>
            </div>
        </section>
    </div>

    <Dialog v-model:open="generateOpen"
        ><DialogContent class="sm:max-w-md"
            ><DialogHeader
                ><DialogTitle>Generate monthly invoices</DialogTitle
                ><DialogDescription
                    >Only active leases not already billed for this month will
                    receive an invoice.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-5" @submit.prevent="generate">
                <label class="block"
                    ><span class="field-label">Billing month</span
                    ><select
                        v-model="generateForm.billing_month"
                        class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                    >
                        <option
                            v-for="period in billing_periods"
                            :key="period.value"
                            :value="period.value"
                        >
                            {{ period.label }}
                        </option></select
                    ><InputError
                        :message="generateForm.errors.billing_month" /></label
                ><DialogFooter
                    ><button
                        type="button"
                        class="h-10 rounded-full border border-border px-4 text-xs font-semibold"
                        @click="generateOpen = false"
                    >
                        Cancel</button
                    ><button
                        type="submit"
                        class="h-10 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                        :disabled="generateForm.processing"
                    >
                        {{
                            generateForm.processing
                                ? 'Generating…'
                                : 'Generate invoices'
                        }}
                    </button></DialogFooter
                >
            </form></DialogContent
        ></Dialog
    >

    <Dialog v-model:open="paymentOpen"
        ><DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl"
            ><DialogHeader
                ><DialogTitle>Record tenant payment</DialogTitle
                ><DialogDescription
                    >Select an open invoice so the payment is allocated and the
                    balance is updated correctly.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-5" @submit.prevent="recordPayment">
                <label class="block"
                    ><span class="field-label">Invoice</span
                    ><select
                        v-model="paymentForm.invoice_id"
                        class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        required
                    >
                        <option value="" disabled>
                            Select an open invoice
                        </option>
                        <option
                            v-for="invoice in openInvoices"
                            :key="invoice.id"
                            :value="invoice.id"
                        >
                            {{ invoice.invoice_number }} ·
                            {{ invoice.tenant }} ·
                            {{ formatMoney(invoice.balance_due) }}
                        </option></select
                    ><InputError :message="paymentForm.errors.invoice_id"
                /></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Amount (UGX)</span
                        ><input
                            v-model="paymentForm.amount"
                            type="number"
                            min="1"
                            :max="selectedInvoice?.balance_due"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError
                            :message="paymentForm.errors.amount" /></label
                    ><label
                        ><span class="field-label">Paid date</span
                        ><input
                            v-model="paymentForm.paid_at"
                            type="date"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required
                    /></label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Method</span
                        ><select
                            v-model="paymentForm.payment_method"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        >
                            <option value="mobile_money">Mobile money</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="other">Other</option>
                        </select></label
                    ><label
                        ><span class="field-label">Provider reference</span
                        ><input
                            v-model="paymentForm.provider_reference"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                    /></label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Payer phone</span
                        ><input
                            v-model="paymentForm.payer_phone"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs" /></label
                    ><label
                        ><span class="field-label">Payer email</span
                        ><input
                            v-model="paymentForm.payer_email"
                            type="email"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                    /></label>
                </div>
                <DialogFooter
                    ><button
                        type="button"
                        class="h-10 rounded-full border border-border px-4 text-xs font-semibold"
                        @click="paymentOpen = false"
                    >
                        Cancel</button
                    ><button
                        type="submit"
                        class="h-10 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                        :disabled="paymentForm.processing"
                    >
                        {{
                            paymentForm.processing
                                ? 'Recording…'
                                : 'Record payment'
                        }}
                    </button></DialogFooter
                >
            </form></DialogContent
        ></Dialog
    >
</template>
