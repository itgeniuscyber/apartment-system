<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BadgeCheck,
    CalendarDays,
    CheckCircle2,
    Clock3,
    ContactRound,
    Download,
    FileKey2,
    Home,
    LockKeyhole,
    Mail,
    MessageCircle,
    MoreHorizontal,
    Pencil,
    Phone,
    Plus,
    Search,
    Send,
    ShieldCheck,
    Trash2,
    Upload,
    UserPlus,
    UsersRound,
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
            { title: 'People', href: '/tenants' },
            { title: 'Tenant management', href: '/tenants' },
        ],
    },
});

type Tenant = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    initials: string;
    email: string | null;
    phone: string | null;
    alternative_phone: string | null;
    status: string;
    preferred_channel: string;
    notes: string | null;
    portal_ready: boolean;
    portal_status: 'active' | 'invited' | 'not_invited' | 'email_required';
    portal_invited_at: string | null;
    can_invite: boolean;
    can_delete: boolean;
    lease_count: number;
    identity: {
        captured: boolean;
        type: string | null;
        type_label: string | null;
        last_four: string | null;
        document_uploaded: boolean;
        verified: boolean;
    };
    next_of_kin: {
        name: string;
        relationship: string;
        phone: string;
        email: string | null;
        address: string | null;
    } | null;
    active_lease: {
        id: number;
        lease_number: string;
        unit_code: string;
        unit_number: string;
        section: string;
        estate: string;
        rent_amount: number;
        due_day: number;
    } | null;
};

const props = defineProps<{
    summary: {
        total: number;
        active: number;
        pending: number;
        portal_ready: number;
        portal_invited: number;
        identity_captured: number;
        identity_verified: number;
    };
    tenants: Tenant[];
}>();

const search = ref('');
const tenantDialogOpen = ref(false);
const editingTenant = ref<Tenant | null>(null);
const deleteDialogOpen = ref(false);
const tenantToDelete = ref<Tenant | null>(null);
const deleteProcessing = ref(false);

const filteredTenants = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.tenants;
    }

    return props.tenants.filter((tenant) =>
        [
            tenant.full_name,
            tenant.email ?? '',
            tenant.phone ?? '',
            tenant.active_lease?.estate ?? '',
            tenant.active_lease?.unit_code ?? '',
        ]
            .join(' ')
            .toLowerCase()
            .includes(term),
    );
});

const tenantForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    alternative_phone: '',
    identity_document_type: 'national_id',
    identity_document_number: '',
    identity_document: null as File | null,
    next_of_kin_name: '',
    next_of_kin_relationship: '',
    next_of_kin_phone: '',
    next_of_kin_email: '',
    next_of_kin_address: '',
    preferred_channel: 'whatsapp',
    notes: '',
});

const formatMoney = (value: number): string =>
    `UGX ${new Intl.NumberFormat('en-UG', { maximumFractionDigits: 0 }).format(value)}`;

const statusLabel = (status: string): string =>
    status.charAt(0).toUpperCase() + status.slice(1);

const portalLabel = (tenant: Tenant): string => {
    if (tenant.portal_status === 'active') {
        return 'Portal active';
    }

    if (tenant.portal_status === 'invited') {
        return 'Activation sent';
    }

    if (tenant.portal_status === 'email_required') {
        return 'Email required';
    }

    return 'Not invited';
};

const portalClass = (tenant: Tenant): string => {
    if (tenant.portal_status === 'active') {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }

    if (tenant.portal_status === 'invited') {
        return 'bg-sky-500/10 text-sky-600 dark:text-sky-400';
    }

    return 'bg-secondary text-muted-foreground';
};

const openTenantDialog = (tenant: Tenant | null = null): void => {
    editingTenant.value = tenant;
    tenantForm.reset();
    tenantForm.clearErrors();

    if (tenant) {
        tenantForm.first_name = tenant.first_name;
        tenantForm.last_name = tenant.last_name;
        tenantForm.email = tenant.email ?? '';
        tenantForm.phone = tenant.phone ?? '';
        tenantForm.alternative_phone = tenant.alternative_phone ?? '';
        tenantForm.identity_document_type =
            tenant.identity.type ?? 'national_id';
        tenantForm.identity_document_number = '';
        tenantForm.identity_document = null;
        tenantForm.next_of_kin_name = tenant.next_of_kin?.name ?? '';
        tenantForm.next_of_kin_relationship =
            tenant.next_of_kin?.relationship ?? '';
        tenantForm.next_of_kin_phone = tenant.next_of_kin?.phone ?? '';
        tenantForm.next_of_kin_email = tenant.next_of_kin?.email ?? '';
        tenantForm.next_of_kin_address = tenant.next_of_kin?.address ?? '';
        tenantForm.preferred_channel = tenant.preferred_channel;
        tenantForm.notes = tenant.notes ?? '';
    }

    tenantDialogOpen.value = true;
};

const submitTenant = (): void => {
    const tenant = editingTenant.value;
    const target = tenant ? `/tenants/${tenant.id}` : '/tenants';

    tenantForm
        .transform((data) => (tenant ? { ...data, _method: 'patch' } : data))
        .post(target, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                tenantDialogOpen.value = false;
                editingTenant.value = null;
                tenantForm.reset();
                toast.success(
                    tenant
                        ? 'Tenant profile updated'
                        : 'Tenant profile and portal onboarding created',
                );
            },
            onFinish: () => tenantForm.transform((data) => data),
        });
};

const openDeleteDialog = (tenant: Tenant): void => {
    tenantToDelete.value = tenant;
    deleteDialogOpen.value = true;
};

const closeDeleteDialog = (): void => {
    deleteDialogOpen.value = false;
    tenantToDelete.value = null;
    deleteProcessing.value = false;
};

const deleteTenant = (): void => {
    if (!tenantToDelete.value) {
        return;
    }

    deleteProcessing.value = true;
    router.delete(`/tenants/${tenantToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteDialog();
            toast.success('Empty tenant profile deleted');
        },
        onError: (errors) => {
            deleteProcessing.value = false;
            toast.error(
                Object.values(errors)[0] ??
                    'This tenant profile could not be deleted',
            );
        },
        onFinish: () => (deleteProcessing.value = false),
    });
};

const sendPortalInvite = (tenant: Tenant): void => {
    router.post(
        `/tenants/${tenant.id}/portal-invitation`,
        {},
        {
            preserveScroll: true,
            onSuccess: () =>
                toast.success(
                    tenant.portal_status === 'invited'
                        ? `A fresh activation link was sent to ${tenant.full_name}`
                        : `Portal invitation sent to ${tenant.full_name}`,
                ),
        },
    );
};

const setIdentityDocument = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    tenantForm.identity_document = input.files?.[0] ?? null;
};

const verifyIdentity = (tenant: Tenant): void => {
    router.patch(
        `/tenants/${tenant.id}/identity/verify`,
        {},
        {
            preserveScroll: true,
            onSuccess: () =>
                toast.success(`${tenant.full_name}'s identity verified`),
        },
    );
};
</script>

<template>
    <Head title="Tenant management" />

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
                    <span>People</span
                    ><span class="text-muted-foreground/40">/</span
                    ><span class="text-foreground">Tenants</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Tenant management
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Keep resident contact details, portal access, lease history,
                    apartment assignments, and communication preferences
                    together.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <Link
                    href="/leases"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-border/70 bg-card px-4 text-xs font-semibold shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <CalendarDays class="size-4" /> View leases
                </Link>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-4 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,0.22)] transition-all hover:-translate-y-0.5"
                    @click="openTenantDialog()"
                >
                    <UserPlus class="size-4" /> Add tenant
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
                    ><UsersRound class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Tenant profiles
                </p>
                <p class="money mt-1.5 text-[1.75rem] font-semibold">
                    {{ summary.total }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Complete resident directory
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 140ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    ><CheckCircle2 class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Active residents
                </p>
                <p class="money mt-1.5 text-[1.75rem] font-semibold">
                    {{ summary.active }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Connected to an active lease
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 190ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    ><Clock3 class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Awaiting assignment
                </p>
                <p class="money mt-1.5 text-[1.75rem] font-semibold">
                    {{ summary.pending }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Ready for lease onboarding
                </p>
            </article>

            <article
                class="surface-card card-lift dashboard-enter p-5"
                style="--delay: 240ms"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                    ><ShieldCheck class="size-5"
                /></span>
                <p class="mt-5 text-xs font-medium text-muted-foreground">
                    Identity captured
                </p>
                <p class="money mt-1.5 text-[1.75rem] font-semibold">
                    {{ summary.identity_captured }}
                </p>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    {{ summary.identity_verified }} verified by management
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
                    <h2 class="text-base font-semibold">Resident directory</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Contact, assignment, rent, due-day, and portal status
                    </p>
                </div>
                <label class="relative block w-full sm:w-72">
                    <Search
                        class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="search"
                        aria-label="Search tenants"
                        placeholder="Search tenants..."
                        class="h-10 w-full rounded-xl border border-border/70 bg-muted/45 pr-4 pl-10 text-xs transition-all outline-none focus:border-accent/50 focus:ring-3 focus:ring-accent/10"
                    />
                </label>
            </header>

            <div
                v-if="filteredTenants.length"
                class="divide-y divide-border/70"
            >
                <article
                    v-for="tenant in filteredTenants"
                    :key="tenant.id"
                    class="grid gap-4 p-5 transition-colors hover:bg-muted/25 sm:px-6 lg:grid-cols-[1.15fr_0.9fr_0.8fr_1fr] lg:items-center"
                >
                    <div class="flex items-center gap-3.5">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-[#171715] text-xs font-bold text-white dark:bg-[#efefe9] dark:text-[#171715]"
                            >{{ tenant.initials }}</span
                        >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-sm font-semibold">
                                    {{ tenant.full_name }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-1 text-[8px] font-bold uppercase"
                                    :class="
                                        tenant.status === 'active'
                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                    "
                                    >{{ statusLabel(tenant.status) }}</span
                                >
                            </div>
                            <p
                                class="mt-1 flex items-center gap-1.5 truncate text-[10px] text-muted-foreground"
                            >
                                <Mail v-if="tenant.email" class="size-3" />
                                <Phone v-else class="size-3" />
                                {{ tenant.email || tenant.phone }}
                            </p>
                            <p
                                v-if="tenant.next_of_kin"
                                class="mt-1 flex items-center gap-1.5 truncate text-[10px] text-muted-foreground"
                            >
                                <ContactRound class="size-3" />
                                Next of kin: {{ tenant.next_of_kin.name }} ·
                                {{ tenant.next_of_kin.relationship }}
                            </p>
                        </div>
                    </div>

                    <div v-if="tenant.active_lease">
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Apartment
                        </p>
                        <p
                            class="mt-1 flex items-center gap-1.5 text-xs font-semibold"
                        >
                            <Home class="size-3.5 text-accent" />
                            {{ tenant.active_lease.estate }} ·
                            {{ tenant.active_lease.unit_number }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ tenant.active_lease.section }} ·
                            {{ tenant.active_lease.unit_code }}
                        </p>
                    </div>
                    <div v-else>
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Apartment
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Not assigned yet
                        </p>
                    </div>

                    <div v-if="tenant.active_lease">
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Lease rent
                        </p>
                        <p class="money mt-1 text-xs font-semibold">
                            {{ formatMoney(tenant.active_lease.rent_amount) }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            Due on day {{ tenant.active_lease.due_day }}
                        </p>
                    </div>
                    <div v-else>
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Lease
                        </p>
                        <Link
                            href="/leases"
                            class="mt-1 inline-flex text-xs font-semibold text-accent"
                            >Create assignment</Link
                        >
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 lg:justify-end"
                    >
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-[9px] font-bold"
                            :class="
                                tenant.identity.verified
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : tenant.identity.captured
                                      ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                      : 'bg-secondary text-muted-foreground'
                            "
                        >
                            <BadgeCheck
                                v-if="tenant.identity.verified"
                                class="size-3"
                            />
                            <FileKey2 v-else class="size-3" />
                            {{
                                tenant.identity.verified
                                    ? 'ID verified'
                                    : tenant.identity.captured
                                      ? 'ID review pending'
                                      : 'ID not captured'
                            }}
                            <span v-if="tenant.identity.last_four">
                                ·••{{ tenant.identity.last_four }}
                            </span>
                        </span>
                        <a
                            v-if="tenant.identity.document_uploaded"
                            :href="`/tenants/${tenant.id}/identity-document`"
                            class="inline-flex size-7 items-center justify-center rounded-full border border-border/70 bg-card text-muted-foreground transition-colors hover:text-accent"
                            title="Download private identity document"
                        >
                            <Download class="size-3.5" />
                        </a>
                        <button
                            v-if="
                                tenant.identity.captured &&
                                !tenant.identity.verified
                            "
                            type="button"
                            class="inline-flex h-7 items-center gap-1 rounded-full border border-border/70 px-2.5 text-[9px] font-bold transition-colors hover:border-emerald-500/30 hover:text-emerald-600"
                            @click="verifyIdentity(tenant)"
                        >
                            <BadgeCheck class="size-3" /> Verify
                        </button>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-[9px] font-bold"
                            :class="portalClass(tenant)"
                            :title="
                                tenant.portal_invited_at
                                    ? `Last activation sent ${tenant.portal_invited_at}`
                                    : undefined
                            "
                        >
                            <ShieldCheck
                                v-if="tenant.portal_status === 'active'"
                                class="size-3"
                            />
                            <Mail
                                v-else-if="tenant.portal_status === 'invited'"
                                class="size-3"
                            />
                            <Clock3 v-else class="size-3" />
                            {{ portalLabel(tenant) }}
                        </span>
                        <button
                            v-if="!tenant.portal_ready && tenant.can_invite"
                            type="button"
                            class="inline-flex h-7 items-center gap-1 rounded-full border border-border/70 px-2.5 text-[9px] font-bold transition-colors hover:border-accent/40 hover:text-accent"
                            :title="
                                tenant.portal_status === 'invited'
                                    ? 'Invalidate the old link and send a fresh one'
                                    : 'Send secure portal activation link'
                            "
                            @click="sendPortalInvite(tenant)"
                        >
                            <Send class="size-3" />
                            {{
                                tenant.portal_status === 'invited'
                                    ? 'Resend'
                                    : 'Invite'
                            }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-7 items-center gap-1 rounded-full border border-border/70 px-2.5 text-[9px] font-bold transition-colors hover:border-accent/40 hover:text-accent"
                            @click="openTenantDialog(tenant)"
                        >
                            <Pencil class="size-3" /> Edit
                        </button>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="inline-flex size-7 items-center justify-center rounded-full border border-border/70 bg-card text-muted-foreground transition-colors hover:text-foreground"
                                    :aria-label="`Manage ${tenant.full_name}`"
                                    title="More tenant actions"
                                >
                                    <MoreHorizontal class="size-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-52">
                                <DropdownMenuItem
                                    class="gap-2 text-xs"
                                    @click="openTenantDialog(tenant)"
                                >
                                    <Pencil class="size-3.5" /> Edit full
                                    profile
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-if="tenant.can_delete"
                                    class="gap-2 text-xs text-destructive focus:text-destructive"
                                    @click="openDeleteDialog(tenant)"
                                >
                                    <Trash2 class="size-3.5" /> Delete empty
                                    profile
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-else
                                    disabled
                                    class="text-[10px] leading-4"
                                >
                                    Deletion locked · lease or finance history
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </article>
            </div>

            <div v-else class="p-8 text-center sm:p-14">
                <span
                    class="mx-auto flex size-14 items-center justify-center rounded-[1.15rem] bg-accent/10 text-accent"
                    ><UsersRound class="size-6"
                /></span>
                <h3 class="mt-4 text-base font-semibold">
                    {{
                        search
                            ? 'No tenants match that search'
                            : 'No tenant records yet'
                    }}
                </h3>
                <p
                    class="mx-auto mt-2 max-w-md text-xs leading-6 text-muted-foreground"
                >
                    {{
                        search
                            ? 'Try a different name, phone, email, estate, or unit code.'
                            : 'Add the first resident profile without inventing occupancy. A lease can be connected when their unit and dates are confirmed.'
                    }}
                </p>
                <button
                    v-if="!search"
                    type="button"
                    class="mt-6 inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                    @click="openTenantDialog()"
                >
                    <Plus class="size-4" /> Add first tenant
                </button>
            </div>
        </section>
    </div>

    <Dialog v-model:open="tenantDialogOpen">
        <DialogContent
            class="max-h-[calc(100svh-2rem)] overflow-y-auto rounded-[1.5rem] border-border/70 p-0 sm:max-w-3xl"
        >
            <DialogHeader
                class="border-b border-border/70 px-6 pt-6 pb-5 text-left"
            >
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><Pencil v-if="editingTenant" class="size-5" />
                    <UserPlus v-else class="size-5"
                /></span>
                <DialogTitle class="text-xl tracking-[-0.035em]">
                    {{
                        editingTenant
                            ? 'Edit tenant profile'
                            : 'Add a tenant profile'
                    }}
                </DialogTitle>
                <DialogDescription class="text-xs leading-5">
                    {{
                        editingTenant
                            ? 'Update contact, login email, private identification, next-of-kin, and management notes. Lease and payment history remain separate.'
                            : 'Record the resident first. Unit assignment and lease terms stay separate so occupancy is never assumed. When an email is supplied, the tenant receives a secure link to choose their own portal password.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5 px-6 py-5" @submit.prevent="submitTenant">
                <section class="space-y-4">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="flex size-8 items-center justify-center rounded-xl bg-accent/10 text-accent"
                        >
                            <UsersRound class="size-4" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold">
                                Resident details
                            </h3>
                            <p class="text-[10px] text-muted-foreground">
                                Primary contact and communication preferences
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="tenant-first-name">First name</Label>
                            <Input
                                id="tenant-first-name"
                                v-model="tenantForm.first_name"
                                placeholder="First name"
                                class="h-11 rounded-xl"
                                autofocus
                            />
                            <InputError
                                :message="tenantForm.errors.first_name"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="tenant-last-name">Last name</Label>
                            <Input
                                id="tenant-last-name"
                                v-model="tenantForm.last_name"
                                placeholder="Last name"
                                class="h-11 rounded-xl"
                            />
                            <InputError
                                :message="tenantForm.errors.last_name"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="tenant-email"
                                >Email address (portal login)</Label
                            >
                            <Input
                                id="tenant-email"
                                v-model="tenantForm.email"
                                type="email"
                                placeholder="tenant@example.com"
                                class="h-11 rounded-xl"
                            />
                            <p
                                class="text-[10px] leading-4 text-muted-foreground"
                            >
                                {{
                                    editingTenant
                                        ? 'Changing this also changes the tenant’s portal login email.'
                                        : 'A one-time activation link is sent here. The administrator never creates or sees the tenant’s password.'
                                }}
                            </p>
                            <InputError :message="tenantForm.errors.email" />
                        </div>
                        <div class="space-y-2">
                            <Label for="tenant-phone">Phone / WhatsApp</Label>
                            <Input
                                id="tenant-phone"
                                v-model="tenantForm.phone"
                                type="tel"
                                placeholder="+256 7..."
                                class="h-11 rounded-xl"
                            />
                            <InputError :message="tenantForm.errors.phone" />
                        </div>
                        <div class="space-y-2">
                            <Label for="tenant-alt-phone"
                                >Alternative phone</Label
                            >
                            <Input
                                id="tenant-alt-phone"
                                v-model="tenantForm.alternative_phone"
                                type="tel"
                                placeholder="Optional"
                                class="h-11 rounded-xl"
                            />
                            <InputError
                                :message="tenantForm.errors.alternative_phone"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="tenant-channel"
                                >Preferred contact</Label
                            >
                            <select
                                id="tenant-channel"
                                v-model="tenantForm.preferred_channel"
                                class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                            >
                                <option value="whatsapp">WhatsApp</option>
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                            </select>
                            <InputError
                                :message="tenantForm.errors.preferred_channel"
                            />
                        </div>
                    </div>
                </section>

                <section
                    class="space-y-4 rounded-[1.25rem] border border-border/70 bg-muted/25 p-4 sm:p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex size-8 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                            >
                                <FileKey2 class="size-4" />
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold">
                                    Security & identification
                                </h3>
                                <p class="text-[10px] text-muted-foreground">
                                    Add an ID number, a document, or both
                                </p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[9px] font-bold text-emerald-600 dark:text-emerald-400"
                        >
                            <LockKeyhole class="size-3" /> Private
                        </span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="identity-type">Document type</Label>
                            <select
                                id="identity-type"
                                v-model="tenantForm.identity_document_type"
                                class="h-11 w-full rounded-xl border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                            >
                                <option value="national_id">National ID</option>
                                <option value="passport">Passport</option>
                                <option value="driving_permit">
                                    Driving permit
                                </option>
                                <option value="refugee_id">Refugee ID</option>
                                <option value="other">Other document</option>
                            </select>
                            <InputError
                                :message="
                                    tenantForm.errors.identity_document_type
                                "
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="identity-number"
                                >ID / document number</Label
                            >
                            <Input
                                id="identity-number"
                                v-model="tenantForm.identity_document_number"
                                :placeholder="
                                    editingTenant?.identity.last_four
                                        ? `Leave blank to keep ID ending ${editingTenant.identity.last_four}`
                                        : 'e.g. national ID number'
                                "
                                class="h-11 rounded-xl"
                                autocomplete="off"
                            />
                            <InputError
                                :message="
                                    tenantForm.errors.identity_document_number
                                "
                            />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="identity-document"
                                >Identity document</Label
                            >
                            <label
                                for="identity-document"
                                class="flex min-h-20 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-border bg-background px-4 py-3 transition-colors hover:border-accent/50 hover:bg-accent/[0.025]"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent"
                                >
                                    <Upload class="size-4" />
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-xs font-semibold"
                                    >
                                        {{
                                            tenantForm.identity_document
                                                ?.name ||
                                            (editingTenant?.identity
                                                .document_uploaded
                                                ? 'Choose a replacement document'
                                                : 'Choose a PDF or image')
                                        }}
                                    </span>
                                    <span
                                        class="mt-1 block text-[10px] text-muted-foreground"
                                    >
                                        PDF, JPG or PNG · maximum 5 MB · stored
                                        privately<span v-if="editingTenant">
                                            · leave unchanged to keep the
                                            current file</span
                                        >
                                    </span>
                                </span>
                            </label>
                            <input
                                id="identity-document"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                class="sr-only"
                                @change="setIdentityDocument"
                            />
                            <InputError
                                :message="tenantForm.errors.identity_document"
                            />
                        </div>
                    </div>
                </section>

                <section
                    class="space-y-4 rounded-[1.25rem] border border-border/70 p-4 sm:p-5"
                >
                    <div class="flex items-center gap-2.5">
                        <span
                            class="flex size-8 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                        >
                            <ContactRound class="size-4" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold">Next of kin</h3>
                            <p class="text-[10px] text-muted-foreground">
                                Required trusted contact for emergencies
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="next-of-kin-name">Full name</Label>
                            <Input
                                id="next-of-kin-name"
                                v-model="tenantForm.next_of_kin_name"
                                placeholder="Next of kin's name"
                                class="h-11 rounded-xl"
                            />
                            <InputError
                                :message="tenantForm.errors.next_of_kin_name"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="next-of-kin-relationship"
                                >Relationship</Label
                            >
                            <Input
                                id="next-of-kin-relationship"
                                v-model="tenantForm.next_of_kin_relationship"
                                placeholder="e.g. Spouse, parent, sibling"
                                class="h-11 rounded-xl"
                            />
                            <InputError
                                :message="
                                    tenantForm.errors.next_of_kin_relationship
                                "
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="next-of-kin-phone">Phone number</Label>
                            <Input
                                id="next-of-kin-phone"
                                v-model="tenantForm.next_of_kin_phone"
                                type="tel"
                                placeholder="+256 7..."
                                class="h-11 rounded-xl"
                            />
                            <InputError
                                :message="tenantForm.errors.next_of_kin_phone"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="next-of-kin-email">Email address</Label>
                            <Input
                                id="next-of-kin-email"
                                v-model="tenantForm.next_of_kin_email"
                                type="email"
                                placeholder="Optional"
                                class="h-11 rounded-xl"
                            />
                            <InputError
                                :message="tenantForm.errors.next_of_kin_email"
                            />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="next-of-kin-address">Address</Label>
                            <textarea
                                id="next-of-kin-address"
                                v-model="tenantForm.next_of_kin_address"
                                rows="2"
                                placeholder="Optional home or work address"
                                class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                            />
                            <InputError
                                :message="tenantForm.errors.next_of_kin_address"
                            />
                        </div>
                    </div>
                </section>

                <div class="space-y-2">
                    <Label for="tenant-notes">Management notes</Label>
                    <textarea
                        id="tenant-notes"
                        v-model="tenantForm.notes"
                        rows="3"
                        placeholder="Optional internal notes..."
                        class="w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
                    />
                    <InputError :message="tenantForm.errors.notes" />
                </div>

                <div
                    class="flex items-start gap-3 rounded-2xl bg-muted/55 p-4 text-xs leading-5 text-muted-foreground"
                >
                    <MessageCircle class="mt-0.5 size-4 shrink-0 text-accent" />
                    At least one email or phone is required. Email enables the
                    resident portal invitation. Identity numbers are encrypted,
                    uploaded files stay outside the public website, and
                    verification is recorded separately by management.
                </div>

                <DialogFooter class="border-t border-border/70 pt-5">
                    <button
                        type="button"
                        class="h-10 rounded-full px-4 text-xs font-semibold hover:bg-secondary"
                        @click="tenantDialogOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white disabled:opacity-50"
                        :disabled="tenantForm.processing"
                    >
                        <Pencil v-if="editingTenant" class="size-4" />
                        <UserPlus v-else class="size-4" />
                        {{
                            tenantForm.processing
                                ? editingTenant
                                    ? 'Saving...'
                                    : 'Creating...'
                                : editingTenant
                                  ? 'Save profile'
                                  : 'Create tenant'
                        }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent class="rounded-[1.5rem] border-border/70 sm:max-w-md">
            <DialogHeader class="text-left">
                <span
                    class="mb-2 flex size-11 items-center justify-center rounded-2xl bg-destructive/10 text-destructive"
                >
                    <Trash2 class="size-5" />
                </span>
                <DialogTitle class="text-xl tracking-[-0.035em]">
                    Delete this tenant profile?
                </DialogTitle>
                <DialogDescription class="text-xs leading-5">
                    {{ tenantToDelete?.full_name }} has no lease or financial
                    history. Their unused portal login will also be removed.
                </DialogDescription>
            </DialogHeader>

            <div
                class="flex items-start gap-3 rounded-2xl bg-destructive/5 p-4 text-xs leading-5 text-muted-foreground"
            >
                <AlertTriangle
                    class="mt-0.5 size-4 shrink-0 text-destructive"
                />
                Once a tenant has a lease, invoice, payment, or payment link,
                deletion is locked to preserve the audit history.
            </div>

            <DialogFooter class="mt-2">
                <button
                    type="button"
                    class="h-10 rounded-full px-4 text-xs font-semibold hover:bg-secondary"
                    :disabled="deleteProcessing"
                    @click="closeDeleteDialog"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-destructive px-5 text-xs font-semibold text-white disabled:opacity-50"
                    :disabled="deleteProcessing"
                    @click="deleteTenant"
                >
                    <Trash2 class="size-4" />
                    {{ deleteProcessing ? 'Deleting...' : 'Delete profile' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
