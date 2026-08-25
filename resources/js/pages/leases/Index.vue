<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    CalendarCheck,
    CalendarDays,
    CircleDollarSign,
    Clock3,
    FileCheck2,
    FilePlus2,
    MoreHorizontal,
    Pencil,
    Play,
    Plus,
    ReceiptText,
    Search,
    ShieldCheck,
    Square,
    Trash2,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tenancy', href: '/leases' },
            { title: 'Leases & billing', href: '/leases' },
        ],
    },
});

type TenantOption = {
    id: number;
    full_name: string;
    status: string;
};

type UnitOption = {
    id: number;
    code: string;
    number: string;
    rent_amount: number;
    status: string;
    available: boolean;
    section: string;
    estate: string;
};

type Lease = {
    id: number;
    lease_number: string;
    tenant: { id: number; full_name: string };
    unit: {
        id: number;
        code: string;
        number: string;
        section: string;
        estate: string;
    };
    start_date: string;
    end_date: string | null;
    rent_amount: number;
    deposit_amount: number;
    due_day: number;
    grace_days: number;
    status: string;
    notes: string | null;
    can_edit: boolean;
    can_edit_assignment: boolean;
    can_activate: boolean;
    can_end: boolean;
    can_delete: boolean;
    has_financial_history: boolean;
    next_due_date: string | null;
};

const props = defineProps<{
    summary: {
        total: number;
        active: number;
        draft: number;
        monthly_rent: number;
        deposits: number;
    };
    leases: Lease[];
    tenants: TenantOption[];
    units: UnitOption[];
}>();

const search = ref('');
const leaseDialogOpen = ref(false);
const editingLease = ref<Lease | null>(null);
const actionDialogOpen = ref(false);
const actionLease = ref<Lease | null>(null);
const actionType = ref<'activate' | 'end' | 'delete'>('activate');
const actionProcessing = ref(false);
const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Africa/Kampala',
}).format(new Date());

const leaseForm = useForm({
    tenant_id: '' as number | '',
    unit_id: '' as number | '',
    start_date: today,
    end_date: '',
    rent_amount: 0,
    deposit_amount: 0,
    due_day: 5,
    grace_days: 5,
    status: 'draft',
    notes: '',
});

const endLeaseForm = useForm({
    end_date: today,
});

const groupedUnits = computed(() => {
    const groups = new Map<string, UnitOption[]>();

    for (const unit of props.units) {
        const current = groups.get(unit.estate) ?? [];
        current.push(unit);

        groups.set(unit.estate, current);
    }

    return Array.from(groups, ([estate, units]) => ({ estate, units }));
});

const filteredLeases = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.leases;
    }

    return props.leases.filter((lease) =>
        [
            lease.lease_number,
            lease.tenant.full_name,
            lease.unit.estate,
            lease.unit.section,
            lease.unit.code,
            lease.status,
        ]
            .join(' ')
            .toLowerCase()
            .includes(term),
    );
});

const syncUnitRent = (): void => {
    const unit = props.units.find(
        (item) => item.id === Number(leaseForm.unit_id),
    );

    if (unit) {
        leaseForm.rent_amount = unit.rent_amount;

        if (leaseForm.deposit_amount === 0) {
            leaseForm.deposit_amount = unit.rent_amount;
        }
    }
};

const formatMoney = (value: number): string =>
    `UGX ${new Intl.NumberFormat('en-UG', { maximumFractionDigits: 0 }).format(value)}`;

const formatCompactMoney = (value: number): string => {
    if (value >= 1_000_000) {
        return `UGX ${(value / 1_000_000).toFixed(value % 1_000_000 === 0 ? 0 : 2)}M`;
    }

    return formatMoney(value);
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return 'Open ended';
    }

    return new Intl.DateTimeFormat('en-UG', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
};

const statusClass = (status: string): string => {
    if (status === 'active') {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }

    if (status === 'ended') {
        return 'bg-secondary text-muted-foreground';
    }

    return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
};

const openLeaseDialog = (lease: Lease | null = null): void => {
    editingLease.value = lease;
    leaseForm.reset();
    leaseForm.clearErrors();

    if (lease) {
        leaseForm.tenant_id = lease.tenant.id;
        leaseForm.unit_id = lease.unit.id;
        leaseForm.start_date = lease.start_date;
        leaseForm.end_date = lease.end_date ?? '';
        leaseForm.rent_amount = lease.rent_amount;
        leaseForm.deposit_amount = lease.deposit_amount;
        leaseForm.due_day = lease.due_day;
        leaseForm.grace_days = lease.grace_days;
        leaseForm.status = lease.status;
        leaseForm.notes = lease.notes ?? '';
    }

    leaseDialogOpen.value = true;
};

const submitLease = (): void => {
    if (editingLease.value) {
        const lease = editingLease.value;

        leaseForm.patch(`/leases/${lease.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                leaseDialogOpen.value = false;
                editingLease.value = null;
                leaseForm.reset();
                toast.success('Lease terms updated');
            },
        });

        return;
    }

    leaseForm.post('/leases', {
        preserveScroll: true,
        onSuccess: () => {
            leaseDialogOpen.value = false;
            leaseForm.reset();
            toast.success('Lease created with rent due-date tracking');
        },
    });
};

const openActionDialog = (
    lease: Lease,
    action: 'activate' | 'end' | 'delete',
): void => {
    actionLease.value = lease;
    actionType.value = action;
    endLeaseForm.reset();
    endLeaseForm.clearErrors();
    actionDialogOpen.value = true;
};

const closeActionDialog = (): void => {
    actionDialogOpen.value = false;
    actionLease.value = null;
    actionProcessing.value = false;
};

const actionError = (errors: Record<string, string>): void => {
    actionProcessing.value = false;
    toast.error(
        Object.values(errors)[0] ?? 'The lease action could not be completed',
    );
};

const confirmLeaseAction = (): void => {
    const lease = actionLease.value;

    if (!lease) {
        return;
    }

    actionProcessing.value = true;

    if (actionType.value === 'activate') {
        router.patch(
            `/leases/${lease.id}/activate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeActionDialog();
                    toast.success('Draft lease activated');
                },
                onError: actionError,
                onFinish: () => (actionProcessing.value = false),
            },
        );

        return;
    }

    if (actionType.value === 'end') {
        endLeaseForm.patch(`/leases/${lease.id}/end`, {
            preserveScroll: true,
            onSuccess: () => {
                closeActionDialog();
                toast.success('Lease ended and apartment released');
            },
            onError: actionError,
            onFinish: () => (actionProcessing.value = false),
        });

        return;
    }

    router.delete(`/leases/${lease.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeActionDialog();
            toast.success('Draft lease deleted');
        },
        onError: actionError,
        onFinish: () => (actionProcessing.value = false),
    });
};
</script>

<template>
    <Head title="Leases & billing" />

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
                    <span>Tenancy</span
                    ><span class="text-muted-foreground/40">/</span
                    ><span class="text-foreground">Leases & billing</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Leases & rent schedules
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Assign residents to units with lease-specific rent,
                    deposits, payment due days, grace periods, dates, and
                    occupancy controls.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <Link
                    href="/tenants"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <UserPlus class="size-4" /> Manage tenants
                </Link>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-4 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,0.22)] transition-all hover:-translate-y-0.5"
                    @click="openLeaseDialog()"
                >
                    <FilePlus2 class="size-4" /> Create lease
                </button>
            </div>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 90ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><FileCheck2 class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Active leases
                </p>
                <p class="money mt-1.5 text-[1.75rem] font-semibold">
                    {{ summary.active }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    {{ summary.total }} total lease records
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 140ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    ><CircleDollarSign class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Active lease rent
                </p>
                <p class="money mt-1.5 text-[1.75rem] font-semibold">
                    {{ formatCompactMoney(summary.monthly_rent) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Monthly tenant billing base
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 190ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                    ><ShieldCheck class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Deposits recorded
                </p>
                <p class="money mt-1.5 text-[1.75rem] font-semibold">
                    {{ formatCompactMoney(summary.deposits) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Terms recorded, collection follows finance
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 240ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    ><Clock3 class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Draft leases
                </p>
                <p class="money mt-1.5 text-[1.75rem] font-semibold">
                    {{ summary.draft }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Do not affect occupancy until active
                </p>
            </article>
        </section>

        <section
            class="surface-card dashboard-enter mt-4 overflow-hidden"
            style="--delay: 290ms"
        >
            <header
                class="flex flex-col justify-between gap-4 border-b border-border/70 p-5 sm:flex-row sm:items-center sm:px-6"
            >
                <div>
                    <h2 class="text-base font-semibold">Lease register</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Unit assignment, terms, due dates, and lease status
                    </p>
                </div>
                <label class="relative block w-full sm:w-72">
                    <Search
                        class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="search"
                        aria-label="Search leases"
                        placeholder="Search leases..."
                        class="h-10 w-full rounded-xl border border-border/70 bg-muted/45 pr-4 pl-10 text-xs outline-none focus:border-accent/50 focus:ring-3 focus:ring-accent/10"
                    />
                </label>
            </header>

            <div v-if="filteredLeases.length" class="divide-y divide-border/70">
                <article
                    v-for="lease in filteredLeases"
                    :key="lease.id"
                    class="grid gap-4 p-5 transition-colors hover:bg-muted/25 sm:px-6 lg:grid-cols-[1.1fr_1fr_0.8fr_0.8fr_auto] lg:items-center"
                >
                    <div class="flex items-center gap-3.5">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                            ><ReceiptText class="size-5"
                        /></span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold">
                                    {{ lease.tenant.full_name }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-1 text-[8px] font-bold uppercase"
                                    :class="statusClass(lease.status)"
                                    >{{ lease.status }}</span
                                >
                            </div>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ lease.lease_number }}
                            </p>
                        </div>
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
                            {{ lease.unit.estate }} · {{ lease.unit.number }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ lease.unit.section }} · {{ lease.unit.code }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Rent schedule
                        </p>
                        <p class="money mt-1 text-xs font-semibold">
                            {{ formatMoney(lease.rent_amount) }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            Due day {{ lease.due_day }} ·
                            {{ lease.grace_days }} grace days
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            {{
                                lease.next_due_date ? 'Next due' : 'Lease dates'
                            }}
                        </p>
                        <p
                            class="mt-1 flex items-center gap-1.5 text-xs font-semibold"
                        >
                            <CalendarCheck class="size-3.5 text-accent" />
                            {{
                                lease.next_due_date ||
                                formatDate(lease.start_date)
                            }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ formatDate(lease.start_date) }} →
                            {{ formatDate(lease.end_date) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2 lg:justify-end">
                        <button
                            v-if="lease.can_activate"
                            type="button"
                            class="inline-flex h-8 items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 text-[10px] font-bold text-emerald-700 transition-all hover:-translate-y-0.5 hover:bg-emerald-500/15 dark:text-emerald-400"
                            title="Activate this draft and reserve its apartment"
                            @click="openActionDialog(lease, 'activate')"
                        >
                            <Play class="size-3.5" /> Activate
                        </button>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-full border border-border/70 bg-card text-muted-foreground transition-all hover:border-accent/35 hover:text-foreground"
                                    :aria-label="`Manage ${lease.lease_number}`"
                                    title="Lease actions"
                                >
                                    <MoreHorizontal class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-48">
                                <DropdownMenuItem
                                    v-if="lease.can_edit"
                                    class="gap-2 text-xs"
                                    @click="openLeaseDialog(lease)"
                                >
                                    <Pencil class="size-3.5" /> Edit lease
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="lease.can_activate"
                                    class="gap-2 text-xs text-emerald-700 focus:text-emerald-700 dark:text-emerald-400 dark:focus:text-emerald-400"
                                    @click="openActionDialog(lease, 'activate')"
                                >
                                    <Play class="size-3.5" /> Activate draft
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="lease.can_end"
                                    class="gap-2 text-xs"
                                    @click="openActionDialog(lease, 'end')"
                                >
                                    <Square class="size-3.5" /> End lease
                                </DropdownMenuItem>
                                <DropdownMenuSeparator
                                    v-if="lease.can_delete"
                                />
                                <DropdownMenuItem
                                    v-if="lease.can_delete"
                                    class="gap-2 text-xs text-destructive focus:text-destructive"
                                    @click="openActionDialog(lease, 'delete')"
                                >
                                    <Trash2 class="size-3.5" /> Delete draft
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="!lease.can_edit && !lease.can_delete"
                                    disabled
                                    class="text-[10px]"
                                >
                                    History preserved · read only
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </article>
            </div>

            <div v-else class="p-8 text-center sm:p-14">
                <span
                    class="mx-auto flex size-14 items-center justify-center rounded-[1.15rem] bg-accent/10 text-accent"
                    ><FileCheck2 class="size-6"
                /></span>
                <h3 class="mt-4 text-base font-semibold">
                    {{
                        search
                            ? 'No leases match that search'
                            : 'No leases created yet'
                    }}
                </h3>
                <p
                    class="mx-auto mt-2 max-w-md text-xs leading-6 text-muted-foreground"
                >
                    {{
                        search
                            ? 'Try the tenant name, lease number, estate, section, or unit code.'
                            : tenants.length
                              ? 'Create the first lease to assign a confirmed tenant to a unit and begin due-date tracking.'
                              : 'Add a tenant profile first, then return here to connect their unit, dates, rent, deposit, and due day.'
                    }}
                </p>
                <button
                    v-if="tenants.length && !search"
                    type="button"
                    class="mt-6 inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                    @click="openLeaseDialog()"
                >
                    <Plus class="size-4" /> Create first lease
                </button>
                <Link
                    v-else-if="!search"
                    href="/tenants"
                    class="mt-6 inline-flex h-10 items-center gap-2 rounded-full bg-foreground px-5 text-xs font-semibold text-background"
                >
                    <UserPlus class="size-4" /> Add a tenant
                </Link>
            </div>
        </section>
    </div>

    <Dialog v-model:open="leaseDialogOpen">
        <DialogContent
            class="max-h-[calc(100svh-2rem)] overflow-y-auto rounded-[1.5rem] border-border/70 p-0 sm:max-w-2xl"
        >
            <DialogHeader
                class="border-b border-border/70 px-6 pt-6 pb-5 text-left"
            >
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><Pencil v-if="editingLease" class="size-5" />
                    <FilePlus2 v-else class="size-5"
                /></span>
                <DialogTitle class="text-xl tracking-[-0.035em]">
                    {{ editingLease ? 'Edit lease terms' : 'Create a lease' }}
                </DialogTitle>
                <DialogDescription class="text-xs leading-5">
                    {{
                        editingLease
                            ? editingLease.can_edit_assignment
                                ? 'Update this draft before activation. The resident, apartment, dates, and rent schedule can still be changed.'
                                : 'Update the dates and rent schedule. End an active lease before moving the resident to another apartment.'
                            : 'Confirm the resident, unit, term, and rent schedule. Active leases reserve the unit immediately; drafts do not.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5 px-6 py-5" @submit.prevent="submitLease">
                <div
                    v-if="!tenants.length"
                    class="rounded-2xl border border-dashed border-border p-6 text-center"
                >
                    <UserPlus class="mx-auto size-5 text-accent" />
                    <p class="mt-3 text-sm font-semibold">
                        A tenant profile is required
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Add the resident before assigning a lease.
                    </p>
                    <Link
                        href="/tenants"
                        class="mt-4 inline-flex h-9 items-center rounded-full bg-foreground px-4 text-[11px] font-semibold text-background"
                        >Go to tenants</Link
                    >
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="lease-tenant">Tenant</Label>
                        <select
                            id="lease-tenant"
                            v-model="leaseForm.tenant_id"
                            :disabled="
                                !!editingLease &&
                                !editingLease.can_edit_assignment
                            "
                            class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        >
                            <option value="" disabled>Select a tenant</option>
                            <option
                                v-for="tenant in tenants"
                                :key="tenant.id"
                                :value="tenant.id"
                            >
                                {{ tenant.full_name }} · {{ tenant.status }}
                            </option>
                        </select>
                        <InputError :message="leaseForm.errors.tenant_id" />
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="lease-unit">Apartment unit</Label>
                        <select
                            id="lease-unit"
                            v-model="leaseForm.unit_id"
                            :disabled="
                                !!editingLease &&
                                !editingLease.can_edit_assignment
                            "
                            class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                            @change="syncUnitRent"
                        >
                            <option value="" disabled>
                                Select an available unit
                            </option>
                            <optgroup
                                v-for="group in groupedUnits"
                                :key="group.estate"
                                :label="group.estate"
                            >
                                <option
                                    v-for="unit in group.units"
                                    :key="unit.id"
                                    :value="unit.id"
                                    :disabled="
                                        !unit.available &&
                                        unit.id !== editingLease?.unit.id
                                    "
                                >
                                    {{ unit.section }} · {{ unit.number }} ·
                                    {{ unit.code }} ·
                                    {{ formatMoney(unit.rent_amount)
                                    }}{{ unit.available ? '' : ' · Occupied' }}
                                </option>
                            </optgroup>
                        </select>
                        <InputError :message="leaseForm.errors.unit_id" />
                    </div>

                    <div class="space-y-2">
                        <Label for="lease-start">Start date</Label>
                        <Input
                            id="lease-start"
                            v-model="leaseForm.start_date"
                            type="date"
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="leaseForm.errors.start_date" />
                    </div>
                    <div class="space-y-2">
                        <Label for="lease-end"
                            >End date
                            <span class="text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="lease-end"
                            v-model="leaseForm.end_date"
                            type="date"
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="leaseForm.errors.end_date" />
                    </div>
                    <div class="space-y-2">
                        <Label for="lease-rent">Monthly rent</Label>
                        <Input
                            id="lease-rent"
                            v-model="leaseForm.rent_amount"
                            type="number"
                            min="0"
                            step="1000"
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="leaseForm.errors.rent_amount" />
                    </div>
                    <div class="space-y-2">
                        <Label for="lease-deposit">Security deposit</Label>
                        <Input
                            id="lease-deposit"
                            v-model="leaseForm.deposit_amount"
                            type="number"
                            min="0"
                            step="1000"
                            class="h-11 rounded-xl"
                        />
                        <InputError
                            :message="leaseForm.errors.deposit_amount"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="lease-due">Rent due day</Label>
                        <Input
                            id="lease-due"
                            v-model="leaseForm.due_day"
                            type="number"
                            min="1"
                            max="28"
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="leaseForm.errors.due_day" />
                    </div>
                    <div class="space-y-2">
                        <Label for="lease-grace">Grace period days</Label>
                        <Input
                            id="lease-grace"
                            v-model="leaseForm.grace_days"
                            type="number"
                            min="0"
                            max="30"
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="leaseForm.errors.grace_days" />
                    </div>
                    <div v-if="!editingLease" class="space-y-2 sm:col-span-2">
                        <Label for="lease-status">Starting status</Label>
                        <select
                            id="lease-status"
                            v-model="leaseForm.status"
                            class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        >
                            <option value="draft">
                                Draft · save terms without occupying the unit
                            </option>
                            <option value="active">
                                Active · reserve unit and activate tenant
                            </option>
                        </select>
                        <InputError :message="leaseForm.errors.status" />
                    </div>
                    <div
                        v-else
                        class="flex items-start gap-3 rounded-2xl border border-border/70 bg-muted/35 p-4 sm:col-span-2"
                    >
                        <ShieldCheck
                            class="mt-0.5 size-4 shrink-0 text-accent"
                        />
                        <p class="text-xs leading-5 text-muted-foreground">
                            Status:
                            <strong class="font-semibold text-foreground">{{
                                editingLease.status
                            }}</strong
                            >. Status changes use the separate Activate or End
                            action so occupancy cannot change accidentally.
                        </p>
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="lease-notes">Internal notes</Label>
                        <textarea
                            id="lease-notes"
                            v-model="leaseForm.notes"
                            rows="3"
                            placeholder="Optional lease notes..."
                            class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        />
                        <InputError :message="leaseForm.errors.notes" />
                    </div>
                </div>

                <div
                    v-if="tenants.length"
                    class="flex items-start gap-3 rounded-2xl bg-muted/55 p-4 text-xs leading-5 text-muted-foreground"
                >
                    <CalendarDays class="mt-0.5 size-4 shrink-0 text-accent" />
                    Rent is due on day {{ leaseForm.due_day }} of each month,
                    followed by {{ leaseForm.grace_days }} grace days. Changes
                    apply to future billing; existing invoices remain unchanged.
                </div>

                <DialogFooter class="border-t border-border/70 pt-5">
                    <button
                        type="button"
                        class="h-10 rounded-full px-4 text-xs font-semibold hover:bg-secondary"
                        @click="leaseDialogOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="tenants.length"
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white disabled:opacity-50"
                        :disabled="leaseForm.processing"
                    >
                        <FileCheck2 class="size-4" />
                        {{
                            leaseForm.processing
                                ? editingLease
                                    ? 'Saving...'
                                    : 'Creating...'
                                : editingLease
                                  ? 'Save changes'
                                  : 'Create lease'
                        }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="actionDialogOpen">
        <DialogContent class="rounded-[1.5rem] border-border/70 sm:max-w-md">
            <DialogHeader class="text-left">
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-2xl"
                    :class="
                        actionType === 'activate'
                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            : actionType === 'delete'
                              ? 'bg-destructive/10 text-destructive'
                              : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                    "
                >
                    <Play v-if="actionType === 'activate'" class="size-5" />
                    <Trash2
                        v-else-if="actionType === 'delete'"
                        class="size-5"
                    />
                    <Square v-else class="size-5" />
                </span>
                <DialogTitle class="text-xl tracking-[-0.035em]">
                    {{
                        actionType === 'activate'
                            ? 'Activate this draft?'
                            : actionType === 'delete'
                              ? 'Delete this draft?'
                              : 'End this active lease?'
                    }}
                </DialogTitle>
                <DialogDescription class="text-xs leading-5">
                    <template v-if="actionType === 'activate'">
                        {{ actionLease?.tenant.full_name }} will become an
                        active resident and {{ actionLease?.unit.code }} will be
                        marked occupied. Finance can then generate rent invoices
                        from these terms.
                    </template>
                    <template v-else-if="actionType === 'end'">
                        The apartment will be released and the resident will be
                        marked former. Existing invoices, receipts, and payment
                        history remain available.
                    </template>
                    <template v-else>
                        This removes only the unused draft. Active and
                        financially linked leases cannot be deleted.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div v-if="actionType === 'end'" class="space-y-2 py-2">
                <Label for="lease-end-action">Final occupancy date</Label>
                <Input
                    id="lease-end-action"
                    v-model="endLeaseForm.end_date"
                    type="date"
                    :min="actionLease?.start_date"
                    :max="today"
                    class="h-11 rounded-xl"
                />
                <InputError :message="endLeaseForm.errors.end_date" />
            </div>

            <div
                class="flex items-start gap-3 rounded-2xl bg-muted/55 p-4 text-xs leading-5 text-muted-foreground"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0 text-accent" />
                {{
                    actionType === 'delete'
                        ? 'Deletion cannot be undone from the dashboard.'
                        : 'This action changes live occupancy and tenant status.'
                }}
            </div>

            <DialogFooter class="mt-2">
                <button
                    type="button"
                    class="h-10 rounded-full px-4 text-xs font-semibold hover:bg-secondary"
                    :disabled="actionProcessing"
                    @click="closeActionDialog"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full px-5 text-xs font-semibold text-white disabled:opacity-50"
                    :class="
                        actionType === 'delete'
                            ? 'bg-destructive'
                            : actionType === 'activate'
                              ? 'bg-emerald-600'
                              : 'bg-accent'
                    "
                    :disabled="actionProcessing"
                    @click="confirmLeaseAction"
                >
                    {{
                        actionProcessing
                            ? 'Working...'
                            : actionType === 'activate'
                              ? 'Activate lease'
                              : actionType === 'delete'
                                ? 'Delete draft'
                                : 'End lease'
                    }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
