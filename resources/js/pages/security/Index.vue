<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    Ban,
    Check,
    KeyRound,
    Mail,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    ShieldCheck,
    Trash2,
    UserCog,
    UsersRound,
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
            { title: 'Oversight', href: '/security' },
            { title: 'Staff & security', href: '/security' },
        ],
    },
});

type Staff = {
    id: number;
    name: string;
    email: string;
    role: 'super_admin' | 'administrator';
    is_active: boolean;
    permissions: string[];
    email_verified_at: string | null;
    deactivated_at: string | null;
    created_at: string;
    is_current_user: boolean;
};

const props = defineProps<{
    staff: Staff[];
    permissionOptions: Array<{
        key: string;
        label: string;
        description: string;
    }>;
    activity: Array<{
        id: number;
        action: string;
        description: string;
        actor: string;
        created_at: string;
    }>;
}>();

const search = ref('');
const dialogOpen = ref(false);
const editing = ref<Staff | null>(null);
const form = useForm({
    name: '',
    email: '',
    role: 'administrator' as 'administrator' | 'super_admin',
    permissions: [] as string[],
});

const filteredStaff = computed(() => {
    const term = search.value.toLowerCase().trim();

    return props.staff.filter(
        (member) =>
            !term ||
            `${member.name} ${member.email} ${member.role}`
                .toLowerCase()
                .includes(term),
    );
});
const staffError = computed(
    () => (form.errors as Record<string, string | undefined>).staff,
);

const openCreate = (): void => {
    editing.value = null;
    form.reset();
    form.role = 'administrator';
    form.permissions = props.permissionOptions.map((item) => item.key);
    form.clearErrors();
    dialogOpen.value = true;
};

const openEdit = (member: Staff): void => {
    editing.value = member;
    form.name = member.name;
    form.email = member.email;
    form.role = member.role;
    form.permissions = [...member.permissions];
    form.clearErrors();
    dialogOpen.value = true;
};

const save = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.patch(`/security/staff/${editing.value.id}`, options);
    } else {
        form.post('/security/staff', options);
    }
};

const togglePermission = (permission: string): void => {
    form.permissions = form.permissions.includes(permission)
        ? form.permissions.filter((item) => item !== permission)
        : [...form.permissions, permission];
};

const toggleStatus = (member: Staff): void => {
    router.patch(
        `/security/staff/${member.id}/status`,
        { is_active: !member.is_active },
        { preserveScroll: true },
    );
};

const resend = (member: Staff): void => {
    router.post(
        `/security/staff/${member.id}/invitation`,
        {},
        { preserveScroll: true },
    );
};

const remove = (member: Staff): void => {
    if (
        window.confirm(
            `Permanently remove ${member.name}'s staff login? Their recorded activity will remain in the audit log.`,
        )
    ) {
        router.delete(`/security/staff/${member.id}`, {
            preserveScroll: true,
        });
    }
};

const roleLabel = (role: string): string =>
    role === 'super_admin' ? 'Super Admin' : 'Admin';
</script>

<template>
    <Head title="Staff & security" />
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
                    <span>Oversight</span><span class="opacity-40">/</span
                    ><span class="text-foreground">Staff & security</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Staff & access control
                </h1>
                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    Keep private totals and sensitive integrations with Super
                    Admins while giving each Admin only the tools required for
                    their work.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(242,103,69,.22)] transition-all hover:-translate-y-0.5"
                @click="openCreate"
            >
                <Plus class="size-4" /> Add staff member
            </button>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-3">
            <article
                v-for="(card, index) in [
                    {
                        label: 'Super Admins',
                        value: staff.filter(
                            (item) => item.role === 'super_admin',
                        ).length,
                        note: 'Full private access',
                        icon: ShieldCheck,
                        color: 'bg-accent/10 text-accent',
                    },
                    {
                        label: 'Operational Admins',
                        value: staff.filter(
                            (item) => item.role === 'administrator',
                        ).length,
                        note: 'Permission controlled',
                        icon: UsersRound,
                        color: 'bg-sky-500/10 text-sky-600',
                    },
                    {
                        label: 'Active accounts',
                        value: staff.filter((item) => item.is_active).length,
                        note: `${staff.filter((item) => !item.is_active).length} deactivated`,
                        icon: KeyRound,
                        color: 'bg-emerald-500/10 text-emerald-600',
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

        <section class="mt-5 grid gap-5 xl:grid-cols-[1.5fr_.7fr]">
            <article
                class="surface-card dashboard-enter overflow-hidden"
                style="--delay: 240ms"
            >
                <header
                    class="flex flex-col gap-3 border-b border-border/70 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <div>
                        <h2 class="text-base font-semibold">Management team</h2>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ filteredStaff.length }} staff accounts
                        </p>
                    </div>
                    <label class="relative"
                        ><Search
                            class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" /><input
                            v-model="search"
                            class="h-10 w-full rounded-xl border border-border/70 bg-background pr-3 pl-9 text-[10px] sm:w-64"
                            placeholder="Search staff…"
                    /></label>
                </header>
                <div class="divide-y divide-border/70">
                    <div
                        v-for="member in filteredStaff"
                        :key="member.id"
                        class="grid gap-4 px-5 py-5 sm:px-6 xl:grid-cols-[1.1fr_.65fr_1.2fr_auto] xl:items-center"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-2xl font-bold"
                                :class="
                                    member.role === 'super_admin'
                                        ? 'bg-accent/10 text-accent'
                                        : 'bg-sky-500/10 text-sky-600'
                                "
                                >{{
                                    member.name
                                        .split(' ')
                                        .map((part) => part[0])
                                        .join('')
                                        .slice(0, 2)
                                }}</span
                            >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-xs font-semibold">
                                        {{ member.name }}
                                    </p>
                                    <span
                                        v-if="member.is_current_user"
                                        class="rounded-full bg-secondary px-2 py-0.5 text-[8px] font-bold"
                                        >You</span
                                    >
                                </div>
                                <p
                                    class="mt-1 truncate text-[10px] text-muted-foreground"
                                >
                                    {{ member.email }}
                                </p>
                            </div>
                        </div>
                        <div>
                            <p class="field-label">Role</p>
                            <p class="mt-1 text-[11px] font-semibold">
                                {{ roleLabel(member.role) }}
                            </p>
                            <span
                                class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[8px] font-bold"
                                :class="
                                    member.is_active
                                        ? 'bg-emerald-500/10 text-emerald-600'
                                        : 'bg-red-500/10 text-red-600'
                                "
                                >{{
                                    member.is_active ? 'Active' : 'Deactivated'
                                }}</span
                            >
                        </div>
                        <div>
                            <p class="field-label">Access</p>
                            <p
                                class="mt-1 text-[10px] leading-5 text-muted-foreground"
                            >
                                {{
                                    member.role === 'super_admin'
                                        ? 'All financial, staff, loans and system controls'
                                        : `${member.permissions.length} operational permissions · private totals excluded`
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button
                                class="flex size-9 items-center justify-center rounded-xl border border-border/70 transition-colors hover:bg-secondary"
                                title="Edit access"
                                @click="openEdit(member)"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                            <button
                                class="flex size-9 items-center justify-center rounded-xl border border-border/70 transition-colors hover:bg-secondary"
                                title="Resend setup link"
                                :disabled="!member.is_active"
                                @click="resend(member)"
                            >
                                <Mail class="size-3.5" />
                            </button>
                            <button
                                v-if="!member.is_current_user"
                                class="flex size-9 items-center justify-center rounded-xl border border-border/70 transition-colors hover:bg-secondary"
                                :title="
                                    member.is_active
                                        ? 'Deactivate'
                                        : 'Reactivate'
                                "
                                @click="toggleStatus(member)"
                            >
                                <Ban
                                    v-if="member.is_active"
                                    class="size-3.5"
                                /><RefreshCw v-else class="size-3.5" />
                            </button>
                            <button
                                v-if="!member.is_current_user"
                                class="flex size-9 items-center justify-center rounded-xl border border-red-500/20 text-red-600 transition-colors hover:bg-red-500/10"
                                title="Delete staff account"
                                @click="remove(member)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <article
                class="surface-card dashboard-enter overflow-hidden"
                style="--delay: 290ms"
            >
                <header class="border-b border-border/70 p-5">
                    <div class="flex items-center gap-2">
                        <Activity class="size-4 text-accent" />
                        <h2 class="text-sm font-semibold">
                            Sensitive activity
                        </h2>
                    </div>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Permanent accountability trail
                    </p>
                </header>
                <div
                    class="max-h-[610px] divide-y divide-border/70 overflow-y-auto"
                >
                    <div v-for="event in activity" :key="event.id" class="p-4">
                        <div class="flex gap-3">
                            <span
                                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-secondary"
                                ><UserCog class="size-3.5"
                            /></span>
                            <div>
                                <p class="text-[10px] leading-5 font-medium">
                                    {{ event.description }}
                                </p>
                                <p
                                    class="mt-1 text-[9px] text-muted-foreground"
                                >
                                    {{ event.actor }} · {{ event.created_at }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="!activity.length"
                        class="px-5 py-12 text-center text-[10px] text-muted-foreground"
                    >
                        Sensitive changes will appear here.
                    </div>
                </div>
            </article>
        </section>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader
                ><DialogTitle>{{
                    editing ? 'Edit staff access' : 'Invite staff member'
                }}</DialogTitle
                ><DialogDescription>{{
                    editing
                        ? 'Changes take effect on their next request.'
                        : 'A secure password setup link will be emailed automatically.'
                }}</DialogDescription></DialogHeader
            >
            <form class="space-y-5" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        ><span class="field-label">Full name</span
                        ><input
                            v-model="form.name"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError
                            :message="form.errors.name" /></label
                    ><label
                        ><span class="field-label">Email address</span
                        ><input
                            v-model="form.email"
                            type="email"
                            class="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-xs"
                            required /><InputError :message="form.errors.email"
                    /></label>
                </div>
                <div>
                    <span class="field-label">Account role</span>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="option in [
                                {
                                    value: 'administrator',
                                    label: 'Admin',
                                    note: 'Selected operational permissions only',
                                    icon: UserCog,
                                },
                                {
                                    value: 'super_admin',
                                    label: 'Super Admin',
                                    note: 'Everything, including private totals and staff',
                                    icon: ShieldCheck,
                                },
                            ]"
                            :key="option.value"
                            type="button"
                            class="flex items-start gap-3 rounded-2xl border p-4 text-left transition-all"
                            :class="
                                form.role === option.value
                                    ? 'border-accent bg-accent/5'
                                    : 'border-border/70 hover:bg-secondary/50'
                            "
                            @click="
                                form.role = option.value as
                                    'administrator' | 'super_admin'
                            "
                        >
                            <component
                                :is="option.icon"
                                class="mt-0.5 size-4 text-accent"
                            /><span
                                ><span class="block text-xs font-semibold">{{
                                    option.label
                                }}</span
                                ><span
                                    class="mt-1 block text-[9px] leading-4 text-muted-foreground"
                                    >{{ option.note }}</span
                                ></span
                            >
                        </button>
                    </div>
                    <InputError :message="form.errors.role" />
                </div>
                <div v-if="form.role === 'administrator'">
                    <div class="flex items-center justify-between">
                        <span class="field-label">Operational permissions</span
                        ><span class="text-[9px] text-muted-foreground"
                            >{{ form.permissions.length }} selected</span
                        >
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="permission in permissionOptions"
                            :key="permission.key"
                            type="button"
                            class="flex gap-3 rounded-xl border p-3 text-left"
                            :class="
                                form.permissions.includes(permission.key)
                                    ? 'border-accent/40 bg-accent/5'
                                    : 'border-border/70'
                            "
                            @click="togglePermission(permission.key)"
                        >
                            <span
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md"
                                :class="
                                    form.permissions.includes(permission.key)
                                        ? 'bg-accent text-white'
                                        : 'bg-secondary'
                                "
                                ><Check
                                    v-if="
                                        form.permissions.includes(
                                            permission.key,
                                        )
                                    "
                                    class="size-3" /></span
                            ><span
                                ><span
                                    class="block text-[10px] font-semibold"
                                    >{{ permission.label }}</span
                                ><span
                                    class="mt-0.5 block text-[8px] leading-4 text-muted-foreground"
                                    >{{ permission.description }}</span
                                ></span
                            >
                        </button>
                    </div>
                    <InputError :message="form.errors.permissions" />
                </div>
                <InputError :message="staffError" />
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
                        {{
                            form.processing
                                ? 'Saving…'
                                : editing
                                  ? 'Save access'
                                  : 'Create & invite'
                        }}
                    </button></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>
</template>
