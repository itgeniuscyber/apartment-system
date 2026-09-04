<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    BellRing,
    ChevronRight,
    Clock3,
    HandCoins,
    Pencil,
    Plus,
    ReceiptText,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
            { title: 'Private finance', href: '/loans' },
            { title: 'Loans', href: '/loans' },
        ],
    },
});

type LoanPayment = {
    id: number;
    amount: number;
    paid_on: string;
    paid_on_label: string;
    payment_method: string;
    reference: string | null;
    notes: string | null;
};
type Loan = {
    id: number;
    reference: string;
    direction: 'borrowed' | 'issued';
    name: string;
    counterparty_name: string;
    counterparty_phone: string | null;
    counterparty_email: string | null;
    tenant_id: number | null;
    tenant: string | null;
    principal_amount: number;
    total_payable: number;
    amount_paid: number;
    balance: number;
    progress: number;
    interest_rate: string | null;
    installment_amount: number | null;
    repayment_frequency: string;
    start_date: string;
    next_due_date: string | null;
    next_due_label: string | null;
    maturity_date: string | null;
    reminder_days_before: number;
    status: string;
    effective_status: string;
    notes: string | null;
    payments: LoanPayment[];
};

const props = defineProps<{
    summary: {
        borrowed_balance: number;
        issued_balance: number;
        paid_total: number;
        due_soon: number;
        overdue: number;
    };
    loans: Loan[];
    tenants: Array<{ id: number; name: string }>;
    today: string;
}>();

const search = ref('');
const directionFilter = ref('all');
const editorOpen = ref(false);
const paymentOpen = ref(false);
const detailOpen = ref(false);
const editing = ref<Loan | null>(null);
const selected = ref<Loan | null>(null);

const loanForm = useForm({
    direction: 'borrowed' as 'borrowed' | 'issued',
    name: '',
    counterparty_name: '',
    counterparty_phone: '',
    counterparty_email: '',
    tenant_id: '' as string | number,
    principal_amount: '' as string | number,
    total_payable: '' as string | number,
    interest_rate: '' as string | number,
    installment_amount: '' as string | number,
    repayment_frequency: 'monthly',
    start_date: props.today,
    next_due_date: '',
    maturity_date: '',
    reminder_days_before: 5,
    status: 'active',
    notes: '',
});
const paymentForm = useForm({
    amount: '' as string | number,
    paid_on: props.today,
    payment_method: 'bank_transfer',
    reference: '',
    next_due_date: '',
    notes: '',
});

const filteredLoans = computed(() => {
    const term = search.value.toLowerCase().trim();

    return props.loans.filter(
        (loan) =>
            (directionFilter.value === 'all' ||
                loan.direction === directionFilter.value) &&
            (!term ||
                `${loan.reference} ${loan.name} ${loan.counterparty_name}`
                    .toLowerCase()
                    .includes(term)),
    );
});
const formatMoney = (value: number): string =>
    `UGX ${new Intl.NumberFormat('en-UG').format(value)}`;
const label = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const openCreate = (direction: 'borrowed' | 'issued' = 'borrowed'): void => {
    editing.value = null;
    loanForm.reset();
    loanForm.direction = direction;
    loanForm.start_date = props.today;
    loanForm.repayment_frequency = 'monthly';
    loanForm.reminder_days_before = 5;
    loanForm.status = 'active';
    loanForm.clearErrors();
    editorOpen.value = true;
};
const openEdit = (loan: Loan): void => {
    editing.value = loan;
    loanForm.direction = loan.direction;
    loanForm.name = loan.name;
    loanForm.counterparty_name = loan.counterparty_name;
    loanForm.counterparty_phone = loan.counterparty_phone ?? '';
    loanForm.counterparty_email = loan.counterparty_email ?? '';
    loanForm.tenant_id = loan.tenant_id ?? '';
    loanForm.principal_amount = loan.principal_amount;
    loanForm.total_payable = loan.total_payable;
    loanForm.interest_rate = loan.interest_rate ?? '';
    loanForm.installment_amount = loan.installment_amount ?? '';
    loanForm.repayment_frequency = loan.repayment_frequency;
    loanForm.start_date = loan.start_date;
    loanForm.next_due_date = loan.next_due_date ?? '';
    loanForm.maturity_date = loan.maturity_date ?? '';
    loanForm.reminder_days_before = loan.reminder_days_before;
    loanForm.status = loan.status;
    loanForm.notes = loan.notes ?? '';
    loanForm.clearErrors();
    editorOpen.value = true;
};
const saveLoan = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editorOpen.value = false;
            loanForm.reset();
        },
    };

    if (editing.value) {
        loanForm.patch(`/loans/${editing.value.id}`, options);
    } else {
        loanForm.post('/loans', options);
    }
};
const openPayment = (loan: Loan): void => {
    selected.value = loan;
    paymentForm.reset();
    paymentForm.amount = loan.installment_amount
        ? Math.min(loan.installment_amount, loan.balance)
        : loan.balance;
    paymentForm.paid_on = props.today;
    paymentForm.payment_method = 'bank_transfer';
    paymentForm.clearErrors();
    paymentOpen.value = true;
};
const recordPayment = (): void => {
    if (!selected.value) {
        return;
    }

    paymentForm.post(`/loans/${selected.value.id}/payments`, {
        preserveScroll: true,
        onSuccess: () => {
            paymentOpen.value = false;
            selected.value = null;
            paymentForm.reset();
        },
    });
};
const showDetail = (loan: Loan): void => {
    selected.value = loan;
    detailOpen.value = true;
};
const removeLoan = (loan: Loan): void => {
    if (
        window.confirm(
            `Delete ${loan.reference} and all ${loan.payments.length} payment records?`,
        )
    ) {
        router.delete(`/loans/${loan.id}`, { preserveScroll: true });
    }
};
const removePayment = (loan: Loan, payment: LoanPayment): void => {
    if (
        window.confirm('Remove this payment and recalculate the loan balance?')
    ) {
        router.delete(`/loans/${loan.id}/payments/${payment.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                detailOpen.value = false;
            },
        });
    }
};
const statusClass = (status: string): string =>
    (
        ({
            active: 'bg-sky-500/10 text-sky-600',
            overdue: 'bg-red-500/10 text-red-600',
            paused: 'bg-amber-500/10 text-amber-600',
            settled: 'bg-emerald-500/10 text-emerald-600',
            written_off: 'bg-secondary text-muted-foreground',
        }) as Record<string, string>
    )[status] ?? 'bg-secondary text-muted-foreground';
</script>

<template>
    <Head title="Private loan register" />
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
                    <span>Private finance</span><span class="opacity-40">/</span
                    ><span class="text-foreground">Loans</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Loan management
                </h1>
                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    A Super Admin-only register for money you borrowed and money
                    you issued, with balances, payments, due dates and automatic
                    reminders.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5"
                    @click="openCreate('issued')"
                >
                    <ArrowUpRight class="size-4" /> Loan issued</button
                ><button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,.22)] transition-all hover:-translate-y-0.5"
                    @click="openCreate('borrowed')"
                >
                    <Plus class="size-4" /> Add borrowed loan
                </button>
            </div>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="(card, index) in [
                    {
                        label: 'You still owe',
                        value: formatMoney(summary.borrowed_balance),
                        note: 'Across borrowed loans',
                        icon: ArrowDownLeft,
                        color: 'bg-red-500/10 text-red-600',
                    },
                    {
                        label: 'Owed to you',
                        value: formatMoney(summary.issued_balance),
                        note: 'Outstanding issued loans',
                        icon: ArrowUpRight,
                        color: 'bg-emerald-500/10 text-emerald-600',
                    },
                    {
                        label: 'Payments tracked',
                        value: formatMoney(summary.paid_total),
                        note: 'Paid or collected to date',
                        icon: HandCoins,
                        color: 'bg-accent/10 text-accent',
                    },
                    {
                        label: 'Needs attention',
                        value: summary.overdue,
                        note: `${summary.due_soon} due within 7 days`,
                        icon: BellRing,
                        color: summary.overdue
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
                <p class="money mt-1.5 text-[1.45rem] font-semibold">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">
                    {{ card.note }}
                </p>
            </article>
        </section>

        <section
            class="surface-card dashboard-enter mt-5 overflow-hidden"
            style="--delay: 280ms"
        >
            <header
                class="flex flex-col gap-3 border-b border-border/70 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div>
                    <h2 class="text-base font-semibold">
                        Private loan register
                    </h2>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        {{ filteredLoans.length }} matching loans · Super Admin
                        visibility only
                    </p>
                </div>
                <div class="flex gap-2">
                    <label class="relative"
                        ><Search
                            class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" /><input
                            v-model="search"
                            class="h-10 w-52 rounded-xl border border-border/70 bg-background pr-3 pl-9 text-[10px]"
                            placeholder="Search loans…" /></label
                    ><select
                        v-model="directionFilter"
                        class="h-10 rounded-xl border border-border/70 bg-background px-3 text-[10px]"
                    >
                        <option value="all">All loans</option>
                        <option value="borrowed">Borrowed</option>
                        <option value="issued">Issued</option>
                    </select>
                </div>
            </header>
            <div v-if="filteredLoans.length" class="divide-y divide-border/70">
                <div
                    v-for="loan in filteredLoans"
                    :key="loan.id"
                    class="grid gap-5 px-5 py-5 sm:px-6 xl:grid-cols-[1.1fr_.8fr_.85fr_.65fr_auto] xl:items-center"
                >
                    <div class="flex min-w-0 gap-3">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-2xl"
                            :class="
                                loan.direction === 'borrowed'
                                    ? 'bg-red-500/10 text-red-600'
                                    : 'bg-emerald-500/10 text-emerald-600'
                            "
                            ><ArrowDownLeft
                                v-if="loan.direction === 'borrowed'"
                                class="size-4.5" /><ArrowUpRight
                                v-else
                                class="size-4.5"
                        /></span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-xs font-semibold">
                                    {{ loan.name }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[8px] font-bold"
                                    :class="statusClass(loan.effective_status)"
                                    >{{ label(loan.effective_status) }}</span
                                >
                            </div>
                            <p class="mt-1 text-[9px] text-muted-foreground">
                                {{ loan.reference }} ·
                                {{
                                    loan.direction === 'borrowed'
                                        ? 'Borrowed from'
                                        : 'Issued to'
                                }}
                                {{ loan.counterparty_name }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <p class="field-label">Balance</p>
                        <p class="money mt-1 text-sm font-semibold">
                            {{ formatMoney(loan.balance) }}
                        </p>
                        <div
                            class="mt-2 h-1.5 overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full rounded-full bg-accent transition-all"
                                :style="{ width: `${loan.progress}%` }"
                            />
                        </div>
                        <p class="mt-1 text-[8px] text-muted-foreground">
                            {{ loan.progress }}% completed
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Next action</p>
                        <p
                            class="mt-1 text-[10px] font-semibold"
                            :class="
                                loan.effective_status === 'overdue'
                                    ? 'text-red-600'
                                    : ''
                            "
                        >
                            {{ loan.next_due_label || 'No due date' }}
                        </p>
                        <p class="mt-1 text-[9px] text-muted-foreground">
                            {{
                                loan.installment_amount
                                    ? `${formatMoney(loan.installment_amount)} · `
                                    : ''
                            }}{{ label(loan.repayment_frequency) }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Activity</p>
                        <p class="mt-1 text-[10px] font-semibold">
                            {{ loan.payments.length }} payments
                        </p>
                        <p class="mt-1 text-[9px] text-muted-foreground">
                            {{ formatMoney(loan.amount_paid) }} tracked
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            v-if="
                                loan.balance > 0 &&
                                !['written_off'].includes(loan.status)
                            "
                            class="h-9 rounded-full bg-foreground px-3 text-[9px] font-semibold text-background"
                            @click="openPayment(loan)"
                        >
                            {{
                                loan.direction === 'borrowed'
                                    ? 'Record payment'
                                    : 'Record receipt'
                            }}</button
                        ><button
                            class="flex size-9 items-center justify-center rounded-xl border border-border/70 hover:bg-secondary"
                            title="View history"
                            @click="showDetail(loan)"
                        >
                            <ChevronRight class="size-3.5" /></button
                        ><button
                            class="flex size-9 items-center justify-center rounded-xl border border-border/70 hover:bg-secondary"
                            title="Edit"
                            @click="openEdit(loan)"
                        >
                            <Pencil class="size-3.5" /></button
                        ><button
                            class="flex size-9 items-center justify-center rounded-xl border border-red-500/20 text-red-600 hover:bg-red-500/10"
                            title="Delete"
                            @click="removeLoan(loan)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="px-6 py-16 text-center">
                <span
                    class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-secondary text-muted-foreground"
                    ><HandCoins class="size-5"
                /></span>
                <p class="mt-3 text-xs font-semibold">No loans found</p>
                <p class="mt-1 text-[10px] text-muted-foreground">
                    Add a borrowed or issued loan to begin tracking it.
                </p>
            </div>
        </section>
    </div>

    <Dialog v-model:open="editorOpen"
        ><DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-3xl"
            ><DialogHeader
                ><DialogTitle>{{
                    editing ? 'Edit loan' : 'Add loan'
                }}</DialogTitle
                ><DialogDescription
                    >Track the full amount due—not only the principal—so the
                    live balance remains exact.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-5" @submit.prevent="saveLoan">
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="option in [
                            {
                                value: 'borrowed',
                                label: 'I borrowed money',
                                note: 'Track what you must repay',
                                icon: ArrowDownLeft,
                            },
                            {
                                value: 'issued',
                                label: 'I issued this loan',
                                note: 'Track what someone owes you',
                                icon: ArrowUpRight,
                            },
                        ]"
                        :key="option.value"
                        type="button"
                        class="flex gap-3 rounded-2xl border p-4 text-left"
                        :class="
                            loanForm.direction === option.value
                                ? 'border-accent bg-accent/5'
                                : 'border-border/70'
                        "
                        @click="
                            loanForm.direction = option.value as
                                'borrowed' | 'issued'
                        "
                    >
                        <component
                            :is="option.icon"
                            class="size-4 text-accent"
                        /><span
                            ><span class="block text-xs font-semibold">{{
                                option.label
                            }}</span
                            ><span
                                class="mt-1 block text-[9px] text-muted-foreground"
                                >{{ option.note }}</span
                            ></span
                        >
                    </button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Loan name / purpose</span
                        ><input
                            v-model="loanForm.name"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            placeholder="e.g. Centenary renovation loan"
                            required /><InputError
                            :message="loanForm.errors.name" /></label
                    ><label
                        ><span class="field-label">{{
                            loanForm.direction === 'borrowed'
                                ? 'Lender'
                                : 'Borrower'
                        }}</span
                        ><input
                            v-model="loanForm.counterparty_name"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError
                            :message="loanForm.errors.counterparty_name"
                    /></label>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label
                        ><span class="field-label">Principal (UGX)</span
                        ><input
                            v-model="loanForm.principal_amount"
                            type="number"
                            min="1"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError
                            :message="
                                loanForm.errors.principal_amount
                            " /></label
                    ><label
                        ><span class="field-label">Total to repay/collect</span
                        ><input
                            v-model="loanForm.total_payable"
                            type="number"
                            min="1"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError
                            :message="loanForm.errors.total_payable" /></label
                    ><label
                        ><span class="field-label">Interest rate %</span
                        ><input
                            v-model="loanForm.interest_rate"
                            type="number"
                            step="0.001"
                            min="0"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            placeholder="Optional" /><InputError
                            :message="loanForm.errors.interest_rate"
                    /></label>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label
                        ><span class="field-label">Instalment amount</span
                        ><input
                            v-model="loanForm.installment_amount"
                            type="number"
                            min="1"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            placeholder="Optional" /></label
                    ><label
                        ><span class="field-label">Frequency</span
                        ><select
                            v-model="loanForm.repayment_frequency"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        >
                            <option
                                v-for="frequency in [
                                    'weekly',
                                    'biweekly',
                                    'monthly',
                                    'quarterly',
                                    'custom',
                                ]"
                                :key="frequency"
                                :value="frequency"
                            >
                                {{ label(frequency) }}
                            </option>
                        </select></label
                    ><label
                        ><span class="field-label">Reminder lead time</span>
                        <div class="relative">
                            <input
                                v-model="loanForm.reminder_days_before"
                                type="number"
                                min="0"
                                max="60"
                                class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 pr-14 text-xs"
                            /><span
                                class="absolute top-1/2 right-3 mt-1 -translate-y-1/2 text-[9px] text-muted-foreground"
                                >days</span
                            >
                        </div></label
                    >
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label
                        ><span class="field-label">Start date</span
                        ><input
                            v-model="loanForm.start_date"
                            type="date"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /></label
                    ><label
                        ><span class="field-label">Next due date</span
                        ><input
                            v-model="loanForm.next_due_date"
                            type="date"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs" /></label
                    ><label
                        ><span class="field-label">Maturity date</span
                        ><input
                            v-model="loanForm.maturity_date"
                            type="date"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                    /></label>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label
                        ><span class="field-label">Phone</span
                        ><input
                            v-model="loanForm.counterparty_phone"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs" /></label
                    ><label
                        ><span class="field-label">Email</span
                        ><input
                            v-model="loanForm.counterparty_email"
                            type="email"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs" /></label
                    ><label v-if="loanForm.direction === 'issued'"
                        ><span class="field-label">Link tenant (optional)</span
                        ><select
                            v-model="loanForm.tenant_id"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        >
                            <option value="">Not a tenant</option>
                            <option
                                v-for="tenant in tenants"
                                :key="tenant.id"
                                :value="tenant.id"
                            >
                                {{ tenant.name }}
                            </option>
                        </select></label
                    >
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label v-if="editing"
                        ><span class="field-label">Status</span
                        ><select
                            v-model="loanForm.status"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        >
                            <option
                                v-for="status in [
                                    'active',
                                    'paused',
                                    'settled',
                                    'written_off',
                                ]"
                                :key="status"
                                :value="status"
                            >
                                {{ label(status) }}
                            </option>
                        </select></label
                    ><label
                        ><span class="field-label">Private notes</span
                        ><textarea
                            v-model="loanForm.notes"
                            rows="3"
                            class="mt-2 w-full rounded-xl border border-border bg-background px-3 py-2 text-xs"
                        />
                    </label>
                </div>
                <DialogFooter
                    ><button
                        type="button"
                        class="h-10 rounded-full border border-border px-4 text-xs font-semibold"
                        @click="editorOpen = false"
                    >
                        Cancel</button
                    ><button
                        type="submit"
                        class="h-10 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                        :disabled="loanForm.processing"
                    >
                        {{
                            loanForm.processing
                                ? 'Saving…'
                                : editing
                                  ? 'Save loan'
                                  : 'Add loan'
                        }}
                    </button></DialogFooter
                >
            </form></DialogContent
        ></Dialog
    >

    <Dialog v-model:open="paymentOpen"
        ><DialogContent class="sm:max-w-xl"
            ><DialogHeader
                ><DialogTitle>{{
                    selected?.direction === 'borrowed'
                        ? 'Record loan payment'
                        : 'Record loan receipt'
                }}</DialogTitle
                ><DialogDescription v-if="selected"
                    >{{ selected.reference }} ·
                    {{ formatMoney(selected.balance) }}
                    remaining</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-5" @submit.prevent="recordPayment">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Amount (UGX)</span
                        ><input
                            v-model="paymentForm.amount"
                            type="number"
                            min="1"
                            :max="selected?.balance"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError
                            :message="paymentForm.errors.amount" /></label
                    ><label
                        ><span class="field-label">Payment date</span
                        ><input
                            v-model="paymentForm.paid_on"
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
                            <option
                                v-for="method in [
                                    'bank_transfer',
                                    'mobile_money',
                                    'cash',
                                    'card',
                                    'other',
                                ]"
                                :key="method"
                                :value="method"
                            >
                                {{ label(method) }}
                            </option>
                        </select></label
                    ><label
                        ><span class="field-label">Transaction reference</span
                        ><input
                            v-model="paymentForm.reference"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                    /></label>
                </div>
                <label class="block"
                    ><span class="field-label">Next due date</span
                    ><input
                        v-model="paymentForm.next_due_date"
                        type="date"
                        class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs" />
                    <p class="mt-1 text-[9px] text-muted-foreground">
                        Leave blank if this clears the loan or no next date is
                        known.
                    </p>
                    <InputError
                        :message="paymentForm.errors.next_due_date" /></label
                ><label class="block"
                    ><span class="field-label">Notes</span
                    ><textarea
                        v-model="paymentForm.notes"
                        rows="3"
                        class="mt-2 w-full rounded-xl border border-border bg-background px-3 py-2 text-xs"
                    /></label
                ><DialogFooter
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
                                : 'Record transaction'
                        }}
                    </button></DialogFooter
                >
            </form></DialogContent
        ></Dialog
    >

    <Dialog v-model:open="detailOpen"
        ><DialogContent class="max-h-[88vh] overflow-y-auto sm:max-w-2xl"
            ><DialogHeader
                ><DialogTitle>{{ selected?.name }}</DialogTitle
                ><DialogDescription v-if="selected"
                    >{{ selected.reference }} ·
                    {{
                        selected.direction === 'borrowed'
                            ? 'Borrowed from'
                            : 'Issued to'
                    }}
                    {{ selected.counterparty_name }}</DialogDescription
                ></DialogHeader
            ><template v-if="selected"
                ><div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-secondary p-3">
                        <p class="field-label">Principal</p>
                        <p class="money mt-1 text-[11px] font-semibold">
                            {{ formatMoney(selected.principal_amount) }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-secondary p-3">
                        <p class="field-label">Paid / received</p>
                        <p
                            class="money mt-1 text-[11px] font-semibold text-emerald-600"
                        >
                            {{ formatMoney(selected.amount_paid) }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-secondary p-3">
                        <p class="field-label">Balance</p>
                        <p class="money mt-1 text-[11px] font-semibold">
                            {{ formatMoney(selected.balance) }}
                        </p>
                    </div>
                </div>
                <div>
                    <div class="mb-3 flex items-center gap-2">
                        <ReceiptText class="size-4 text-accent" />
                        <h3 class="text-xs font-semibold">Payment history</h3>
                    </div>
                    <div
                        v-if="selected.payments.length"
                        class="divide-y divide-border/70 rounded-2xl border border-border/70"
                    >
                        <div
                            v-for="payment in selected.payments"
                            :key="payment.id"
                            class="flex items-center justify-between gap-4 p-4"
                        >
                            <div>
                                <p class="money text-xs font-semibold">
                                    {{ formatMoney(payment.amount) }}
                                </p>
                                <p
                                    class="mt-1 text-[9px] text-muted-foreground"
                                >
                                    {{ payment.paid_on_label }} ·
                                    {{ label(payment.payment_method)
                                    }}<span v-if="payment.reference">
                                        · {{ payment.reference }}</span
                                    >
                                </p>
                            </div>
                            <button
                                class="flex size-8 items-center justify-center rounded-xl text-red-600 hover:bg-red-500/10"
                                title="Remove payment"
                                @click="removePayment(selected, payment)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-border p-8 text-center text-[10px] text-muted-foreground"
                    >
                        No payments have been recorded.
                    </div>
                </div>
                <div
                    v-if="selected.next_due_date"
                    class="flex items-center gap-3 rounded-2xl p-4"
                    :class="
                        selected.effective_status === 'overdue'
                            ? 'bg-red-500/10 text-red-600'
                            : 'bg-amber-500/10 text-amber-700'
                    "
                >
                    <AlertTriangle
                        v-if="selected.effective_status === 'overdue'"
                        class="size-5"
                    /><Clock3 v-else class="size-5" />
                    <div>
                        <p class="text-xs font-semibold">
                            {{
                                selected.effective_status === 'overdue'
                                    ? 'Action overdue'
                                    : 'Next action scheduled'
                            }}
                        </p>
                        <p class="mt-1 text-[9px]">
                            {{ selected.next_due_label }} · reminder
                            {{ selected.reminder_days_before }} days before
                        </p>
                    </div>
                </div></template
            ></DialogContent
        ></Dialog
    >
</template>
