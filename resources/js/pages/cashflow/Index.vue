<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowLeftRight,
    ArrowUpRight,
    Banknote,
    CalendarDays,
    Download,
    Pencil,
    Plus,
    ReceiptText,
    Repeat2,
    Search,
    Trash2,
    TrendingUp,
    WalletCards,
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
            { title: 'Expenses & revenue', href: '/cashflow' },
        ],
    },
});

type Entry = {
    id: number;
    reference: string;
    type: 'expense' | 'revenue';
    category: string;
    description: string;
    amount: number;
    occurred_on: string;
    occurred_on_label: string;
    estate_id: number | null;
    estate: string | null;
    settlement_account_id: number | null;
    account: string | null;
    counterparty: string | null;
    payment_method: string;
    external_reference: string | null;
    is_recurring: boolean;
    recurrence: string | null;
    notes: string | null;
    has_receipt: boolean;
    receipt_url: string | null;
    created_by: string;
};

const props = defineProps<{
    isSuperAdmin: boolean;
    scopeLabel: string;
    summary: {
        rent_collections: number | null;
        other_revenue: number;
        expenses: number;
        net: number;
        recurring: number;
    };
    entries: Entry[];
    estates: Array<{ id: number; name: string }>;
    accounts: Array<{ id: number; label: string }>;
    categories: { expense: string[]; revenue: string[] };
    today: string;
}>();

const search = ref('');
const typeFilter = ref('all');
const dialogOpen = ref(false);
const editing = ref<Entry | null>(null);
const receiptInput = ref<HTMLInputElement | null>(null);
const form = useForm({
    type: 'expense' as 'expense' | 'revenue',
    category: '',
    description: '',
    amount: '' as string | number,
    occurred_on: props.today,
    estate_id: '' as string | number,
    settlement_account_id: '' as string | number,
    counterparty: '',
    payment_method: 'cash',
    external_reference: '',
    is_recurring: false,
    recurrence: '',
    notes: '',
    receipt: null as File | null,
});

const availableCategories = computed(() => props.categories[form.type]);
const filteredEntries = computed(() => {
    const term = search.value.toLowerCase().trim();

    return props.entries.filter(
        (entry) =>
            (typeFilter.value === 'all' || entry.type === typeFilter.value) &&
            (!term ||
                `${entry.reference} ${entry.description} ${entry.category} ${entry.counterparty ?? ''} ${entry.estate ?? ''}`
                    .toLowerCase()
                    .includes(term)),
    );
});

watch(
    () => form.type,
    () => {
        if (!availableCategories.value.includes(form.category)) {
            form.category = availableCategories.value[0] ?? '';
        }
    },
);

const formatMoney = (value: number): string =>
    `UGX ${new Intl.NumberFormat('en-UG').format(value)}`;
const label = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const openCreate = (type: 'expense' | 'revenue' = 'expense'): void => {
    editing.value = null;
    form.reset();
    form.type = type;
    form.category = props.categories[type][0] ?? '';
    form.occurred_on = props.today;
    form.payment_method = 'cash';
    form.clearErrors();

    if (receiptInput.value) {
        receiptInput.value.value = '';
    }

    dialogOpen.value = true;
};

const openEdit = (entry: Entry): void => {
    editing.value = entry;
    form.type = entry.type;
    form.category = entry.category;
    form.description = entry.description;
    form.amount = entry.amount;
    form.occurred_on = entry.occurred_on;
    form.estate_id = entry.estate_id ?? '';
    form.settlement_account_id = entry.settlement_account_id ?? '';
    form.counterparty = entry.counterparty ?? '';
    form.payment_method = entry.payment_method;
    form.external_reference = entry.external_reference ?? '';
    form.is_recurring = entry.is_recurring;
    form.recurrence = entry.recurrence ?? '';
    form.notes = entry.notes ?? '';
    form.receipt = null;
    form.clearErrors();

    if (receiptInput.value) {
        receiptInput.value.value = '';
    }

    dialogOpen.value = true;
};

const save = (): void => {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.transform((data) => ({ ...data, _method: 'patch' })).post(
            `/cashflow/${editing.value.id}`,
            options,
        );
    } else {
        form.transform((data) => data).post('/cashflow', options);
    }
};

const remove = (entry: Entry): void => {
    if (window.confirm(`Delete ${entry.reference} from the cashbook?`)) {
        router.delete(`/cashflow/${entry.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Expenses & revenue" />
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
                    ><span class="text-foreground">Cashbook</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Expenses & revenue
                </h1>
                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    Track salaries, security, caretakers, rubbish collection,
                    repairs and every other inflow or outflow with proof and
                    accountability.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5"
                    @click="openCreate('revenue')"
                >
                    <TrendingUp class="size-4 text-emerald-600" /> Add revenue</button
                ><button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,.22)] transition-all hover:-translate-y-0.5"
                    @click="openCreate('expense')"
                >
                    <Plus class="size-4" /> Record expense
                </button>
            </div>
        </section>

        <div
            class="mt-4 inline-flex items-center gap-2 rounded-full bg-secondary px-3 py-1.5 text-[9px] font-semibold text-muted-foreground"
        >
            <WalletCards class="size-3.5" /> Visibility: {{ scopeLabel }}
        </div>

        <section
            class="mt-4 grid gap-4 sm:grid-cols-2"
            :class="isSuperAdmin ? 'xl:grid-cols-4' : 'xl:grid-cols-3'"
        >
            <article
                v-if="isSuperAdmin"
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 80ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600"
                    ><Banknote class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Rent collected this month
                </p>
                <p class="money mt-1.5 text-[1.5rem] font-semibold">
                    {{ formatMoney(summary.rent_collections ?? 0) }}
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">
                    Confirmed tenant payments
                </p>
            </article>
            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 125ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600"
                    ><ArrowDownRight class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Other revenue this month
                </p>
                <p class="money mt-1.5 text-[1.5rem] font-semibold">
                    {{ formatMoney(summary.other_revenue) }}
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">
                    Non-rent income recorded
                </p>
            </article>
            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 170ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-red-500/10 text-red-600"
                    ><ArrowUpRight class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Expenses this month
                </p>
                <p class="money mt-1.5 text-[1.5rem] font-semibold">
                    {{ formatMoney(summary.expenses) }}
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">
                    {{ summary.recurring }} recurring records tracked
                </p>
            </article>
            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 215ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><ArrowLeftRight class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    {{
                        isSuperAdmin
                            ? 'Net cash movement'
                            : 'Net of your entries'
                    }}
                </p>
                <p
                    class="money mt-1.5 text-[1.5rem] font-semibold"
                    :class="
                        summary.net < 0 ? 'text-red-600' : 'text-emerald-600'
                    "
                >
                    {{ formatMoney(summary.net) }}
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">
                    Current calendar month
                </p>
            </article>
        </section>

        <section
            class="surface-card dashboard-enter mt-5 overflow-hidden"
            style="--delay: 260ms"
        >
            <header
                class="flex flex-col gap-3 border-b border-border/70 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div>
                    <h2 class="text-base font-semibold">Cashbook register</h2>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        {{ filteredEntries.length }} traceable records
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <label class="relative"
                        ><Search
                            class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" /><input
                            v-model="search"
                            class="h-10 w-52 rounded-xl border border-border/70 bg-background pr-3 pl-9 text-[10px]"
                            placeholder="Search cashbook…" /></label
                    ><select
                        v-model="typeFilter"
                        class="h-10 rounded-xl border border-border/70 bg-background px-3 text-[10px]"
                    >
                        <option value="all">All activity</option>
                        <option value="expense">Expenses</option>
                        <option value="revenue">Revenue</option>
                    </select>
                </div>
            </header>
            <div
                v-if="filteredEntries.length"
                class="divide-y divide-border/70"
            >
                <div
                    v-for="entry in filteredEntries"
                    :key="entry.id"
                    class="grid gap-4 px-5 py-5 sm:px-6 xl:grid-cols-[1.25fr_.75fr_.6fr_.6fr_auto] xl:items-center"
                >
                    <div class="flex min-w-0 gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl"
                            :class="
                                entry.type === 'expense'
                                    ? 'bg-red-500/10 text-red-600'
                                    : 'bg-emerald-500/10 text-emerald-600'
                            "
                            ><ArrowUpRight
                                v-if="entry.type === 'expense'"
                                class="size-4.5" /><ArrowDownRight
                                v-else
                                class="size-4.5"
                        /></span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-xs font-semibold">
                                    {{ entry.description }}
                                </p>
                                <Repeat2
                                    v-if="entry.is_recurring"
                                    class="size-3 text-accent"
                                />
                            </div>
                            <p class="mt-1 text-[9px] text-muted-foreground">
                                {{ entry.reference }} · {{ entry.category }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <p class="field-label">Where / who</p>
                        <p class="mt-1 text-[10px] font-semibold">
                            {{ entry.counterparty || 'Not specified' }}
                        </p>
                        <p class="mt-1 text-[9px] text-muted-foreground">
                            {{ entry.estate || 'General portfolio'
                            }}<span v-if="isSuperAdmin && entry.created_by">
                                · {{ entry.created_by }}</span
                            >
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Date & method</p>
                        <p class="mt-1 text-[10px] font-semibold">
                            {{ entry.occurred_on_label }}
                        </p>
                        <p class="mt-1 text-[9px] text-muted-foreground">
                            {{ label(entry.payment_method) }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Amount</p>
                        <p
                            class="money mt-1 text-[11px] font-semibold"
                            :class="
                                entry.type === 'expense'
                                    ? 'text-red-600'
                                    : 'text-emerald-600'
                            "
                        >
                            {{ entry.type === 'expense' ? '−' : '+' }}
                            {{ formatMoney(entry.amount) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <a
                            v-if="entry.receipt_url"
                            :href="entry.receipt_url"
                            class="flex size-9 items-center justify-center rounded-xl border border-border/70 hover:bg-secondary"
                            title="Download receipt"
                            ><Download class="size-3.5" /></a
                        ><button
                            class="flex size-9 items-center justify-center rounded-xl border border-border/70 hover:bg-secondary"
                            title="Edit"
                            @click="openEdit(entry)"
                        >
                            <Pencil class="size-3.5" /></button
                        ><button
                            class="flex size-9 items-center justify-center rounded-xl border border-red-500/20 text-red-600 hover:bg-red-500/10"
                            title="Delete"
                            @click="remove(entry)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="px-6 py-16 text-center">
                <span
                    class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-secondary text-muted-foreground"
                    ><ReceiptText class="size-5"
                /></span>
                <p class="mt-3 text-xs font-semibold">
                    No cashbook records found
                </p>
                <p class="mt-1 text-[10px] text-muted-foreground">
                    Record the first expense or additional revenue.
                </p>
            </div>
        </section>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader
                ><DialogTitle>{{
                    editing
                        ? 'Edit cashbook record'
                        : form.type === 'expense'
                          ? 'Record expense'
                          : 'Add revenue'
                }}</DialogTitle
                ><DialogDescription
                    >Keep the date, category, payment proof and responsible
                    party together for reliable reporting.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-5" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Entry type</span
                        ><select
                            v-model="form.type"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        >
                            <option value="expense">Expense / money out</option>
                            <option value="revenue">
                                Other revenue / money in
                            </option></select
                        ><InputError :message="form.errors.type" /></label
                    ><label
                        ><span class="field-label">Category</span
                        ><select
                            v-model="form.category"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required
                        >
                            <option
                                v-for="category in availableCategories"
                                :key="category"
                                :value="category"
                            >
                                {{ category }}
                            </option></select
                        ><InputError :message="form.errors.category"
                    /></label>
                </div>
                <label class="block"
                    ><span class="field-label">Description</span
                    ><input
                        v-model="form.description"
                        class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        placeholder="e.g. August gate security salary"
                        required /><InputError
                        :message="form.errors.description"
                /></label>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label
                        ><span class="field-label">Amount (UGX)</span
                        ><input
                            v-model="form.amount"
                            type="number"
                            min="1"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError
                            :message="form.errors.amount" /></label
                    ><label
                        ><span class="field-label">Transaction date</span
                        ><input
                            v-model="form.occurred_on"
                            type="date"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError
                            :message="form.errors.occurred_on" /></label
                    ><label
                        ><span class="field-label">Payment method</span
                        ><select
                            v-model="form.payment_method"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        >
                            <option
                                v-for="method in [
                                    'cash',
                                    'mobile_money',
                                    'bank_transfer',
                                    'card',
                                    'other',
                                ]"
                                :key="method"
                                :value="method"
                            >
                                {{ label(method) }}
                            </option>
                        </select></label
                    >
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Payee / source</span
                        ><input
                            v-model="form.counterparty"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            placeholder="Person, supplier or income source" /></label
                    ><label
                        ><span class="field-label">Estate</span
                        ><select
                            v-model="form.estate_id"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        >
                            <option value="">General / not assigned</option>
                            <option
                                v-for="estate in estates"
                                :key="estate.id"
                                :value="estate.id"
                            >
                                {{ estate.name }}
                            </option>
                        </select></label
                    >
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label v-if="isSuperAdmin"
                        ><span class="field-label">Settlement account</span
                        ><select
                            v-model="form.settlement_account_id"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        >
                            <option value="">Not assigned</option>
                            <option
                                v-for="account in accounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.label }}
                            </option>
                        </select></label
                    ><label
                        ><span class="field-label">External reference</span
                        ><input
                            v-model="form.external_reference"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            placeholder="Receipt, bank or mobile money ref"
                    /></label>
                </div>
                <div class="rounded-2xl border border-border/70 p-4">
                    <label class="flex cursor-pointer items-center gap-3"
                        ><input
                            v-model="form.is_recurring"
                            type="checkbox"
                            class="size-4 accent-[var(--accent)]"
                        /><span
                            ><span class="block text-xs font-semibold"
                                >Recurring commitment</span
                            ><span
                                class="mt-0.5 block text-[9px] text-muted-foreground"
                                >Flag salaries and repeating costs for
                                follow-up.</span
                            ></span
                        ></label
                    ><select
                        v-if="form.is_recurring"
                        v-model="form.recurrence"
                        class="mt-3 h-10 w-full rounded-xl border border-border bg-background px-3 text-xs"
                        required
                    >
                        <option value="" disabled>Select frequency</option>
                        <option
                            v-for="frequency in [
                                'weekly',
                                'monthly',
                                'quarterly',
                                'annually',
                            ]"
                            :key="frequency"
                            :value="frequency"
                        >
                            {{ label(frequency) }}
                        </option></select
                    ><InputError :message="form.errors.recurrence" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Receipt / proof</span
                        ><input
                            ref="receiptInput"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            class="mt-2 block w-full text-[10px] file:mr-3 file:rounded-full file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-[9px] file:font-semibold"
                            @change="
                                form.receipt =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            " />
                        <p
                            v-if="editing?.has_receipt"
                            class="mt-1 text-[9px] text-emerald-600"
                        >
                            Existing proof remains unless replaced.
                        </p>
                        <InputError :message="form.errors.receipt" /></label
                    ><label
                        ><span class="field-label">Internal notes</span
                        ><textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-2 w-full rounded-xl border border-border bg-background px-3 py-2 text-xs"
                            placeholder="Any supporting context…"
                        />
                    </label>
                </div>
                <DialogFooter
                    ><button
                        type="button"
                        class="h-10 rounded-full border border-border px-4 text-xs font-semibold"
                        @click="dialogOpen = false"
                    >
                        Cancel</button
                    ><button
                        type="submit"
                        class="h-10 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                        :disabled="form.processing"
                    >
                        <CalendarDays
                            v-if="!form.processing"
                            class="mr-1.5 inline size-3.5"
                        />{{
                            form.processing
                                ? 'Saving…'
                                : editing
                                  ? 'Save changes'
                                  : 'Save record'
                        }}
                    </button></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>
</template>
