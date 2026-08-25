<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    Banknote,
    Building2,
    CheckCircle2,
    CircleDollarSign,
    Grid3X3,
    Layers3,
    MapPin,
    Plus,
    Search,
    Settings2,
    Sparkles,
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
            { title: 'Portfolio', href: '/properties' },
            { title: 'Estates & units', href: '/properties' },
        ],
    },
});

type Unit = {
    id: number;
    code: string;
    number: string;
    rent_amount: number;
    status: string;
    is_active: boolean;
};

type Section = {
    id: number;
    name: string;
    code: string;
    default_rent: number;
    unit_count: number;
    monthly_rent: number;
    units: Unit[];
};

type SettlementAccount = {
    id: number;
    label?: string;
    owner_name: string;
    bank_name: string;
    account_last_four: string;
};

type Estate = {
    id: number;
    name: string;
    code: string;
    location: string | null;
    address: string | null;
    color: string;
    is_active: boolean;
    unit_count: number;
    section_count: number;
    monthly_rent: number;
    setup_required: number;
    settlement_account: SettlementAccount | null;
    sections: Section[];
};

const props = defineProps<{
    summary: {
        estate_count: number;
        section_count: number;
        unit_count: number;
        monthly_rent: number;
        setup_required: number;
    };
    estates: Estate[];
    settlementAccounts: SettlementAccount[];
}>();

const selectedEstateId = ref<number | null>(props.estates[0]?.id ?? null);
const search = ref('');
const estateDialogOpen = ref(false);
const sectionDialogOpen = ref(false);

const selectedEstate = computed(
    () =>
        props.estates.find((estate) => estate.id === selectedEstateId.value) ??
        props.estates[0] ??
        null,
);

const filteredEstates = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.estates;
    }

    return props.estates.filter((estate) =>
        [estate.name, estate.code, estate.location ?? '']
            .join(' ')
            .toLowerCase()
            .includes(term),
    );
});

watch(
    () => props.estates,
    (estates) => {
        if (
            selectedEstateId.value === null ||
            !estates.some((estate) => estate.id === selectedEstateId.value)
        ) {
            selectedEstateId.value = estates[0]?.id ?? null;
        }
    },
);

const estateForm = useForm({
    name: '',
    code: '',
    location: '',
    address: '',
    settlement_account_id: '' as number | '',
    color: '#f26745',
});

const sectionForm = useForm({
    estate_id: (selectedEstateId.value ?? '') as number | '',
    name: '',
    code: '',
    unit_count: 1,
    unit_prefix: '',
    start_number: 1,
    default_rent: 500000,
});

const formatMoney = (value: number): string =>
    `UGX ${new Intl.NumberFormat('en-UG', { maximumFractionDigits: 0 }).format(value)}`;

const unitLabel = (count: number): string =>
    `${count} ${count === 1 ? 'unit' : 'units'}`;

const sectionLabel = (count: number): string =>
    `${count} ${count === 1 ? 'section' : 'sections'}`;

const formatCompactMoney = (value: number): string => {
    if (value >= 1_000_000) {
        return `UGX ${(value / 1_000_000).toFixed(value % 1_000_000 === 0 ? 0 : 2)}M`;
    }

    return formatMoney(value);
};

const statusLabel = (status: string): string =>
    status
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

const openSectionDialog = (estate: Estate): void => {
    sectionForm.reset();
    sectionForm.clearErrors();
    sectionForm.estate_id = estate.id;
    sectionForm.default_rent = 500000;
    sectionForm.unit_count = 1;
    sectionForm.start_number = 1;
    sectionDialogOpen.value = true;
};

const submitEstate = (): void => {
    estateForm.post('/properties', {
        preserveScroll: true,
        onSuccess: () => {
            estateDialogOpen.value = false;
            estateForm.reset();
            toast.success('Estate added to the portfolio');
        },
    });
};

const submitSection = (): void => {
    sectionForm.post('/properties/sections', {
        preserveScroll: true,
        onSuccess: () => {
            sectionDialogOpen.value = false;
            sectionForm.reset();
            toast.success('Section and units created');
        },
    });
};
</script>

<template>
    <Head title="Estates & units" />

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
                    <span>Portfolio</span
                    ><span class="text-muted-foreground/40">/</span
                    ><span class="text-foreground">Estates & units</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Your property portfolio
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Organise every location into flexible sections, keep
                    unit-level rent rates, and route collections to the correct
                    settlement account.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                    @click="estateDialogOpen = true"
                >
                    <Building2 class="size-4" /> Add estate
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-4 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,0.22)] transition-all hover:-translate-y-0.5 hover:bg-accent/90 disabled:pointer-events-none disabled:opacity-50"
                    :disabled="!selectedEstate"
                    @click="selectedEstate && openSectionDialog(selectedEstate)"
                >
                    <Plus class="size-4" /> Add section & units
                </button>
            </div>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 90ms"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                        ><Building2 class="size-5"
                    /></span>
                    <span
                        class="rounded-full bg-secondary px-2 py-1 text-[10px] font-bold text-muted-foreground"
                        >{{ summary.estate_count }} locations</span
                    >
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Registered units
                </p>
                <p
                    class="money mt-1.5 text-[1.75rem] font-semibold tracking-[-0.055em]"
                >
                    {{ summary.unit_count }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Grouped into {{ summary.section_count }} flexible sections
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
                        class="rounded-full bg-emerald-500/10 px-2 py-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                        >Live rates</span
                    >
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Monthly scheduled rent
                </p>
                <p
                    class="money mt-1.5 text-[1.75rem] font-semibold tracking-[-0.055em]"
                >
                    {{ formatCompactMoney(summary.monthly_rent) }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Calculated from every active unit
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 190ms"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                        ><Layers3 class="size-5"
                    /></span>
                    <span
                        class="rounded-full bg-secondary px-2 py-1 text-[10px] font-bold text-muted-foreground"
                        >Expandable</span
                    >
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Portfolio structure
                </p>
                <p
                    class="money mt-1.5 text-[1.75rem] font-semibold tracking-[-0.055em]"
                >
                    {{ summary.section_count }} sections
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Upper, lower, middle—or any future layout
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 240ms"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                        ><Settings2 class="size-5"
                    /></span>
                    <span
                        class="rounded-full bg-amber-500/10 px-2 py-1 text-[10px] font-bold text-amber-600 dark:text-amber-400"
                        >Next step</span
                    >
                </div>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Tenant setup required
                </p>
                <p
                    class="money mt-1.5 text-[1.75rem] font-semibold tracking-[-0.055em]"
                >
                    {{ summary.setup_required }} units
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Ready to receive lease and tenant details
                </p>
            </article>
        </section>

        <section class="mt-4 grid gap-4 xl:grid-cols-[0.78fr_1.42fr]">
            <aside
                class="surface-card dashboard-enter overflow-hidden"
                style="--delay: 290ms"
            >
                <div class="border-b border-border/70 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2
                                class="text-base font-semibold tracking-[-0.025em]"
                            >
                                Estate directory
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Select a location to inspect its units
                            </p>
                        </div>
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-secondary text-muted-foreground"
                            ><Grid3X3 class="size-4"
                        /></span>
                    </div>
                    <label class="relative mt-4 block">
                        <Search
                            class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search estates..."
                            class="h-10 w-full rounded-xl border border-border/70 bg-muted/45 pr-4 pl-10 text-xs transition-all outline-none placeholder:text-muted-foreground focus:border-accent/50 focus:ring-3 focus:ring-accent/10"
                        />
                    </label>
                </div>

                <div class="space-y-2 p-3">
                    <button
                        v-for="estate in filteredEstates"
                        :key="estate.id"
                        type="button"
                        class="group relative w-full overflow-hidden rounded-2xl border p-4 text-left transition-all"
                        :class="
                            selectedEstateId === estate.id
                                ? 'border-accent/30 bg-accent/[0.06] shadow-sm'
                                : 'border-transparent hover:border-border/70 hover:bg-muted/45'
                        "
                        @click="selectedEstateId = estate.id"
                    >
                        <span
                            v-if="selectedEstateId === estate.id"
                            class="absolute inset-y-4 left-0 w-1 rounded-r-full"
                            :style="{ backgroundColor: estate.color }"
                        />
                        <div class="flex items-start gap-3.5">
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-2xl font-bold text-white shadow-sm"
                                :style="{ backgroundColor: estate.color }"
                                >{{ estate.code.slice(0, 2) }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <p class="truncate text-sm font-semibold">
                                        {{ estate.name }}
                                    </p>
                                    <ArrowRight
                                        class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                    />
                                </div>
                                <p
                                    class="mt-1 flex items-center gap-1 text-[10px] text-muted-foreground"
                                >
                                    <MapPin class="size-3" />
                                    {{ estate.location || 'Location pending' }}
                                </p>
                                <div
                                    class="mt-3 flex items-center justify-between text-[10px]"
                                >
                                    <span class="text-muted-foreground"
                                        >{{ unitLabel(estate.unit_count) }} ·
                                        {{
                                            sectionLabel(estate.section_count)
                                        }}</span
                                    >
                                    <strong class="money font-semibold">{{
                                        formatCompactMoney(estate.monthly_rent)
                                    }}</strong>
                                </div>
                            </div>
                        </div>
                    </button>

                    <div
                        v-if="filteredEstates.length === 0"
                        class="rounded-2xl border border-dashed border-border p-8 text-center"
                    >
                        <Search class="mx-auto size-5 text-muted-foreground" />
                        <p class="mt-3 text-xs font-semibold">
                            No matching estate
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            Try a different name or code.
                        </p>
                    </div>
                </div>
            </aside>

            <article
                v-if="selectedEstate"
                class="surface-card dashboard-enter overflow-hidden"
                style="--delay: 340ms"
            >
                <header
                    class="relative overflow-hidden border-b border-border/70 p-5 sm:p-6"
                >
                    <div
                        class="absolute -top-20 -right-12 size-56 rounded-full opacity-15 blur-3xl"
                        :style="{ backgroundColor: selectedEstate.color }"
                    />
                    <div
                        class="relative flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
                    >
                        <div class="flex items-start gap-4">
                            <span
                                class="flex size-14 shrink-0 items-center justify-center rounded-[1.15rem] text-lg font-bold text-white shadow-lg"
                                :style="{
                                    backgroundColor: selectedEstate.color,
                                }"
                                >{{ selectedEstate.code.slice(0, 2) }}</span
                            >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2
                                        class="text-2xl font-semibold tracking-[-0.045em]"
                                    >
                                        {{ selectedEstate.name }}
                                    </h2>
                                    <span
                                        class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-[9px] font-bold text-emerald-600 dark:text-emerald-400"
                                        >ACTIVE</span
                                    >
                                </div>
                                <p
                                    class="mt-1.5 flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <MapPin class="size-3.5" />
                                    {{
                                        selectedEstate.address ||
                                        selectedEstate.location ||
                                        'Address pending'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <div
                                class="rounded-xl border border-border/70 bg-card/70 px-3.5 py-2.5 backdrop-blur"
                            >
                                <p
                                    class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Monthly rent
                                </p>
                                <p class="money mt-1 text-sm font-semibold">
                                    {{
                                        formatCompactMoney(
                                            selectedEstate.monthly_rent,
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-border/70 bg-card/70 px-3.5 py-2.5 backdrop-blur"
                            >
                                <p
                                    class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Settlement route
                                </p>
                                <p
                                    class="mt-1 flex items-center gap-1.5 text-xs font-semibold"
                                >
                                    <Banknote class="size-3.5 text-accent" />
                                    <template
                                        v-if="selectedEstate.settlement_account"
                                    >
                                        {{
                                            selectedEstate.settlement_account
                                                .owner_name
                                        }}
                                        ·••{{
                                            selectedEstate.settlement_account
                                                .account_last_four
                                        }}
                                    </template>
                                    <template v-else>Not assigned</template>
                                </p>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="p-4 sm:p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold">
                                Sections & unit map
                            </h3>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                Every section can have independent unit counts
                                and rates.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded-full bg-secondary px-3.5 text-[11px] font-semibold transition-colors hover:bg-foreground hover:text-background"
                            @click="openSectionDialog(selectedEstate)"
                        >
                            <Plus class="size-3.5" /> New section
                        </button>
                    </div>

                    <div class="space-y-3">
                        <section
                            v-for="section in selectedEstate.sections"
                            :key="section.id"
                            class="rounded-2xl border border-border/70 bg-muted/25 p-4 transition-colors hover:bg-muted/40"
                        >
                            <div
                                class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-10 items-center justify-center rounded-xl bg-card text-accent shadow-sm"
                                        ><Layers3 class="size-4.5"
                                    /></span>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-semibold">
                                                {{ section.name }}
                                            </h4>
                                            <span
                                                class="rounded-md bg-secondary px-1.5 py-0.5 text-[8px] font-bold text-muted-foreground"
                                                >{{ section.code }}</span
                                            >
                                        </div>
                                        <p
                                            class="mt-1 text-[10px] text-muted-foreground"
                                        >
                                            {{ unitLabel(section.unit_count) }}
                                            ·
                                            {{
                                                formatCompactMoney(
                                                    section.monthly_rent,
                                                )
                                            }}
                                            scheduled
                                        </p>
                                    </div>
                                </div>
                                <div class="text-left sm:text-right">
                                    <p
                                        class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Default rate
                                    </p>
                                    <p class="money mt-1 text-xs font-semibold">
                                        {{ formatMoney(section.default_rent) }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6"
                            >
                                <button
                                    v-for="unit in section.units"
                                    :key="unit.id"
                                    type="button"
                                    class="group rounded-xl border border-border/60 bg-card p-3 text-left shadow-xs transition-all hover:-translate-y-0.5 hover:border-accent/30 hover:shadow-md"
                                    :title="`${unit.code} · ${statusLabel(unit.status)}`"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span class="text-xs font-bold">{{
                                            unit.number
                                        }}</span>
                                        <i
                                            class="size-2 rounded-full"
                                            :class="
                                                unit.status === 'setup_required'
                                                    ? 'bg-amber-400'
                                                    : 'bg-emerald-500'
                                            "
                                        />
                                    </div>
                                    <p
                                        class="money mt-2 truncate text-[10px] font-semibold text-muted-foreground group-hover:text-foreground"
                                    >
                                        {{ formatMoney(unit.rent_amount) }}
                                    </p>
                                </button>
                            </div>
                        </section>

                        <div
                            v-if="selectedEstate.sections.length === 0"
                            class="rounded-2xl border border-dashed border-border p-10 text-center"
                        >
                            <Sparkles class="mx-auto size-6 text-accent" />
                            <h4 class="mt-3 text-sm font-semibold">
                                This estate is ready for its first section
                            </h4>
                            <p
                                class="mx-auto mt-1 max-w-sm text-xs leading-5 text-muted-foreground"
                            >
                                Add any layout—upper, middle, lower, block,
                                wing, or another structure that suits the
                                property.
                            </p>
                            <button
                                type="button"
                                class="mt-5 inline-flex h-9 items-center gap-2 rounded-full bg-accent px-4 text-[11px] font-semibold text-white"
                                @click="openSectionDialog(selectedEstate)"
                            >
                                <Plus class="size-3.5" /> Add first section
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-4 grid gap-4 md:grid-cols-2">
            <article
                v-for="(account, index) in settlementAccounts"
                :key="account.id"
                class="surface-card card-lift dashboard-enter flex items-center justify-between gap-5 p-5"
                :style="{ '--delay': `${400 + index * 50}ms` }"
            >
                <div class="flex items-center gap-4">
                    <span
                        class="flex size-11 items-center justify-center rounded-2xl"
                        :class="
                            index === 0
                                ? 'bg-[#171715] text-white dark:bg-[#efefe9] dark:text-[#171715]'
                                : 'bg-accent/10 text-accent'
                        "
                        ><component
                            :is="index === 0 ? Banknote : WalletCards"
                            class="size-5"
                    /></span>
                    <div>
                        <p
                            class="text-[9px] font-bold tracking-[0.12em] text-muted-foreground uppercase"
                        >
                            {{ account.label }}
                        </p>
                        <p class="mt-1 text-sm font-semibold">
                            {{ account.owner_name }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ account.bank_name }} · account ending
                            {{ account.account_last_four }}
                        </p>
                    </div>
                </div>
                <CheckCircle2 class="size-5 shrink-0 text-emerald-500" />
            </article>
        </section>
    </div>

    <Dialog v-model:open="estateDialogOpen">
        <DialogContent
            class="max-h-[calc(100svh-2rem)] overflow-y-auto rounded-[1.5rem] border-border/70 p-0 sm:max-w-xl"
        >
            <DialogHeader
                class="border-b border-border/70 px-6 pt-6 pb-5 text-left"
            >
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><Building2 class="size-5"
                /></span>
                <DialogTitle class="text-xl tracking-[-0.035em]"
                    >Add a new estate</DialogTitle
                >
                <DialogDescription class="text-xs leading-5">
                    Create the location first. Its sections and units can be
                    added immediately afterward.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5 px-6 py-5" @submit.prevent="submitEstate">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="estate-name">Estate name</Label>
                        <Input
                            id="estate-name"
                            v-model="estateForm.name"
                            placeholder="e.g. Ntinda"
                            class="h-11 rounded-xl"
                            autofocus
                        />
                        <InputError :message="estateForm.errors.name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="estate-code">Short code</Label>
                        <Input
                            id="estate-code"
                            v-model="estateForm.code"
                            placeholder="e.g. NTI"
                            class="h-11 rounded-xl uppercase"
                        />
                        <InputError :message="estateForm.errors.code" />
                    </div>
                    <div class="space-y-2">
                        <Label for="estate-location">Location</Label>
                        <Input
                            id="estate-location"
                            v-model="estateForm.location"
                            placeholder="Town or area"
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="estateForm.errors.location" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="estate-address"
                            >Full address
                            <span class="text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="estate-address"
                            v-model="estateForm.address"
                            placeholder="Street, zone, landmark..."
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="estateForm.errors.address" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="estate-account">Settlement account</Label>
                        <select
                            id="estate-account"
                            v-model="estateForm.settlement_account_id"
                            class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm transition-all outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        >
                            <option value="">Assign later</option>
                            <option
                                v-for="account in settlementAccounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.owner_name }} ·
                                {{ account.bank_name }} ••{{
                                    account.account_last_four
                                }}
                            </option>
                        </select>
                        <InputError
                            :message="estateForm.errors.settlement_account_id"
                        />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="estate-color">Estate colour</Label>
                        <div
                            class="flex items-center gap-3 rounded-xl border border-input p-2"
                        >
                            <input
                                id="estate-color"
                                v-model="estateForm.color"
                                type="color"
                                class="size-8 cursor-pointer rounded-lg border-0 bg-transparent"
                            />
                            <span class="text-xs text-muted-foreground"
                                >Used to identify the estate across dashboards
                                and reports.</span
                            >
                        </div>
                        <InputError :message="estateForm.errors.color" />
                    </div>
                </div>

                <DialogFooter class="border-t border-border/70 pt-5">
                    <button
                        type="button"
                        class="h-10 rounded-full px-4 text-xs font-semibold hover:bg-secondary"
                        @click="estateDialogOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,0.18)] disabled:opacity-50"
                        :disabled="estateForm.processing"
                    >
                        <Building2 class="size-4" />
                        {{
                            estateForm.processing
                                ? 'Creating...'
                                : 'Create estate'
                        }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="sectionDialogOpen">
        <DialogContent
            class="max-h-[calc(100svh-2rem)] overflow-y-auto rounded-[1.5rem] border-border/70 p-0 sm:max-w-2xl"
        >
            <DialogHeader
                class="border-b border-border/70 px-6 pt-6 pb-5 text-left"
            >
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><Layers3 class="size-5"
                /></span>
                <DialogTitle class="text-xl tracking-[-0.035em]"
                    >Add a flexible section</DialogTitle
                >
                <DialogDescription class="text-xs leading-5">
                    Name the section anything that matches the property. The
                    system will create its units and starting rent history
                    together.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5 px-6 py-5" @submit.prevent="submitSection">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="section-estate">Estate</Label>
                        <select
                            id="section-estate"
                            v-model="sectionForm.estate_id"
                            class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm transition-all outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                        >
                            <option
                                v-for="estate in estates"
                                :key="estate.id"
                                :value="estate.id"
                            >
                                {{ estate.name }}
                            </option>
                        </select>
                        <InputError :message="sectionForm.errors.estate_id" />
                    </div>
                    <div class="space-y-2">
                        <Label for="section-name">Section name</Label>
                        <Input
                            id="section-name"
                            v-model="sectionForm.name"
                            placeholder="e.g. Middle"
                            class="h-11 rounded-xl"
                            autofocus
                        />
                        <InputError :message="sectionForm.errors.name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="section-code">Section code</Label>
                        <Input
                            id="section-code"
                            v-model="sectionForm.code"
                            placeholder="e.g. MID"
                            class="h-11 rounded-xl uppercase"
                        />
                        <InputError :message="sectionForm.errors.code" />
                    </div>
                    <div class="space-y-2">
                        <Label for="unit-count">Number of units</Label>
                        <Input
                            id="unit-count"
                            v-model="sectionForm.unit_count"
                            type="number"
                            min="1"
                            max="200"
                            class="h-11 rounded-xl"
                        />
                        <InputError :message="sectionForm.errors.unit_count" />
                    </div>
                    <div class="space-y-2">
                        <Label for="start-number">Starting number</Label>
                        <Input
                            id="start-number"
                            v-model="sectionForm.start_number"
                            type="number"
                            min="1"
                            max="9999"
                            class="h-11 rounded-xl"
                        />
                        <InputError
                            :message="sectionForm.errors.start_number"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="unit-prefix"
                            >Unit code prefix
                            <span class="text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="unit-prefix"
                            v-model="sectionForm.unit_prefix"
                            placeholder="Auto-generated if blank"
                            class="h-11 rounded-xl uppercase"
                        />
                        <InputError :message="sectionForm.errors.unit_prefix" />
                    </div>
                    <div class="space-y-2">
                        <Label for="default-rent">Starting rent per unit</Label>
                        <Input
                            id="default-rent"
                            v-model="sectionForm.default_rent"
                            type="number"
                            min="0"
                            step="1000"
                            class="h-11 rounded-xl"
                        />
                        <InputError
                            :message="sectionForm.errors.default_rent"
                        />
                    </div>
                </div>

                <div class="rounded-2xl bg-muted/55 p-4">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-card text-accent shadow-sm"
                            ><Sparkles class="size-4"
                        /></span>
                        <div>
                            <p class="text-xs font-semibold">
                                Creation preview
                            </p>
                            <p
                                class="mt-1 text-[11px] leading-5 text-muted-foreground"
                            >
                                {{
                                    unitLabel(
                                        Number(sectionForm.unit_count) || 0,
                                    )
                                }}
                                will be created at
                                <strong class="money text-foreground">{{
                                    formatMoney(
                                        Number(sectionForm.default_rent) || 0,
                                    )
                                }}</strong>
                                each. A dated rent-rate record will preserve the
                                starting amount for future auditing.
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-border/70 pt-5">
                    <button
                        type="button"
                        class="h-10 rounded-full px-4 text-xs font-semibold hover:bg-secondary"
                        @click="sectionDialogOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,0.18)] disabled:opacity-50"
                        :disabled="sectionForm.processing"
                    >
                        <Plus class="size-4" />
                        {{
                            sectionForm.processing
                                ? 'Creating...'
                                : 'Create section & units'
                        }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
