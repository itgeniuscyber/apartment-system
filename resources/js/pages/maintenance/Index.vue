<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertOctagon,
    CheckCircle2,
    Clock3,
    Eye,
    MessageSquareText,
    Search,
    Wrench,
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
            { title: 'Operations', href: '/maintenance' },
            { title: 'Maintenance desk', href: '/maintenance' },
        ],
    },
});

type Update = {
    id: number;
    body: string;
    status: string | null;
    internal: boolean;
    sender: string;
    created_at: string | null;
};
type Item = {
    id: number;
    reference: string;
    tenant: { id: number; full_name: string; phone: string | null };
    category: string;
    title: string;
    description: string;
    priority: string;
    status: string;
    access_permission: boolean;
    preferred_visit_at: string | null;
    evidence_url: string | null;
    assigned_to: number | null;
    assignee: string | null;
    apartment: {
        code: string;
        number: string;
        section: string;
        estate: string;
    } | null;
    created_at: string | null;
    updates: Update[];
};

const props = defineProps<{
    summary: {
        open: number;
        in_progress: number;
        urgent: number;
        resolved: number;
    };
    requests: Item[];
    assignees: Array<{ id: number; name: string }>;
}>();
const search = ref('');
const status = ref('active');
const selected = ref<Item | null>(null);
const form = useForm({
    status: 'open',
    priority: 'normal',
    assigned_to: '' as string | number,
    body: '',
    is_internal: false,
});

watch(selected, (item) => {
    if (!item) {
        return;
    }

    form.status = item.status;
    form.priority = item.priority;
    form.assigned_to = item.assigned_to ?? '';
    form.body = '';
    form.is_internal = false;
    form.clearErrors();
});

const filtered = computed(() => {
    const term = search.value.toLowerCase().trim();

    return props.requests.filter((item) => {
        const statusMatch =
            status.value === 'all' ||
            (status.value === 'active'
                ? !['resolved', 'closed'].includes(item.status)
                : item.status === status.value);

        return (
            statusMatch &&
            (!term ||
                [
                    item.reference,
                    item.title,
                    item.tenant.full_name,
                    item.apartment?.estate ?? '',
                    item.apartment?.code ?? '',
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(term))
        );
    });
});

const save = (): void => {
    if (!selected.value) {
        return;
    }

    form.patch(`/maintenance/${selected.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            form.reset();
        },
    });
};
const label = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const statusClass = (value: string): string =>
    (
        ({
            open: 'bg-amber-500/10 text-amber-600',
            acknowledged: 'bg-sky-500/10 text-sky-600',
            in_progress: 'bg-violet-500/10 text-violet-600',
            resolved: 'bg-emerald-500/10 text-emerald-600',
            closed: 'bg-secondary text-muted-foreground',
        }) as Record<string, string>
    )[value] ?? 'bg-secondary text-muted-foreground';
</script>

<template>
    <Head title="Maintenance desk" />
    <div
        class="mx-auto w-full max-w-[1540px] px-4 py-5 sm:px-6 lg:px-8 lg:py-7"
    >
        <section class="dashboard-enter" style="--delay: 20ms">
            <div
                class="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground"
            >
                <span>Operations</span><span class="opacity-40">/</span
                ><span class="text-foreground">Maintenance</span>
            </div>
            <h1
                class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
            >
                Maintenance desk
            </h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                Triage tenant issues, assign responsible staff, preserve private
                notes, and keep residents updated from request to resolution.
            </p>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="(card, index) in [
                    {
                        label: 'Open queue',
                        value: summary.open,
                        note: 'New or acknowledged',
                        icon: Clock3,
                        color: 'bg-amber-500/10 text-amber-600',
                    },
                    {
                        label: 'In progress',
                        value: summary.in_progress,
                        note: 'Assigned and underway',
                        icon: Wrench,
                        color: 'bg-violet-500/10 text-violet-600',
                    },
                    {
                        label: 'Urgent attention',
                        value: summary.urgent,
                        note: 'Urgent or emergency',
                        icon: AlertOctagon,
                        color: 'bg-red-500/10 text-red-600',
                    },
                    {
                        label: 'Resolved records',
                        value: summary.resolved,
                        note: 'Resolved and closed',
                        icon: CheckCircle2,
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
                <p class="money mt-1.5 text-[1.55rem] font-semibold">
                    {{ card.value }}
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">
                    {{ card.note }}
                </p>
            </article>
        </section>

        <section
            class="surface-card dashboard-enter mt-5 overflow-hidden"
            style="--delay: 260ms"
        >
            <div
                class="flex flex-col gap-3 border-b border-border/70 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div>
                    <h2 class="text-base font-semibold">Service register</h2>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        {{ filtered.length }} matching requests
                    </p>
                </div>
                <div class="flex gap-2">
                    <label class="relative"
                        ><Search
                            class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" /><input
                            v-model="search"
                            class="h-10 w-52 rounded-xl border border-border/70 bg-background pr-3 pl-9 text-[10px]"
                            placeholder="Search requests…" /></label
                    ><select
                        v-model="status"
                        class="h-10 rounded-xl border border-border/70 bg-background px-3 text-[10px]"
                    >
                        <option value="active">Active</option>
                        <option value="all">All statuses</option>
                        <option
                            v-for="option in [
                                'open',
                                'acknowledged',
                                'in_progress',
                                'resolved',
                                'closed',
                            ]"
                            :key="option"
                            :value="option"
                        >
                            {{ label(option) }}
                        </option>
                    </select>
                </div>
            </div>
            <div v-if="filtered.length" class="divide-y divide-border/70">
                <button
                    v-for="item in filtered"
                    :key="item.id"
                    type="button"
                    class="grid w-full gap-4 px-5 py-5 text-left transition-colors hover:bg-secondary/50 sm:px-6 xl:grid-cols-[1.15fr_.8fr_.55fr_.55fr_auto] xl:items-center"
                    @click="selected = item"
                >
                    <div class="flex min-w-0 gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                            ><Wrench class="size-4.5"
                        /></span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold">
                                {{ item.title }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ item.reference }} ·
                                {{ label(item.category) }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <p class="field-label">Resident</p>
                        <p class="mt-1 text-[11px] font-semibold">
                            {{ item.tenant.full_name }}
                        </p>
                        <p class="mt-1 text-[9px] text-muted-foreground">
                            {{
                                item.apartment
                                    ? `${item.apartment.estate} · ${item.apartment.code}`
                                    : 'Unit not linked'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="field-label">Priority</p>
                        <p
                            class="mt-1 text-[10px] font-semibold"
                            :class="
                                ['urgent', 'emergency'].includes(item.priority)
                                    ? 'text-red-600'
                                    : ''
                            "
                        >
                            {{ label(item.priority) }}
                        </p>
                    </div>
                    <div>
                        <span
                            class="rounded-full px-2.5 py-1 text-[9px] font-bold"
                            :class="statusClass(item.status)"
                            >{{ label(item.status) }}</span
                        >
                        <p class="mt-2 text-[9px] text-muted-foreground">
                            {{ item.created_at }}
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 text-[10px] font-bold text-accent"
                        ><Eye class="size-3.5" /> Manage</span
                    >
                </button>
            </div>
            <div
                v-else
                class="px-6 py-16 text-center text-[11px] text-muted-foreground"
            >
                No maintenance requests match this view.
            </div>
        </section>
    </div>

    <Dialog
        :open="selected !== null"
        @update:open="
            (open) => {
                if (!open) selected = null;
            }
        "
    >
        <DialogContent
            v-if="selected"
            class="max-h-[92vh] overflow-y-auto rounded-3xl sm:max-w-3xl"
        >
            <DialogHeader
                ><div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-[9px] font-bold"
                        :class="statusClass(selected.status)"
                        >{{ label(selected.status) }}</span
                    ><span
                        v-if="
                            ['urgent', 'emergency'].includes(selected.priority)
                        "
                        class="rounded-full bg-red-500/10 px-2.5 py-1 text-[9px] font-bold text-red-600"
                        >{{ label(selected.priority) }}</span
                    >
                </div>
                <DialogTitle class="mt-3">{{ selected.title }}</DialogTitle
                ><DialogDescription
                    >{{ selected.reference }} ·
                    {{ selected.tenant.full_name }} ·
                    {{
                        selected.apartment?.code || 'Unit not linked'
                    }}</DialogDescription
                ></DialogHeader
            >
            <div
                class="rounded-2xl bg-secondary/55 p-4 text-xs leading-6 text-muted-foreground"
            >
                {{ selected.description }}
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-border/70 p-3 text-[10px]">
                    <p class="field-label">Access</p>
                    <p class="mt-1.5 font-semibold">
                        {{
                            selected.access_permission
                                ? 'Tenant permission granted'
                                : 'Coordinate before entry'
                        }}
                    </p>
                </div>
                <div class="rounded-xl border border-border/70 p-3 text-[10px]">
                    <p class="field-label">Preferred visit</p>
                    <p class="mt-1.5 font-semibold">
                        {{ selected.preferred_visit_at || 'No preference' }}
                    </p>
                </div>
                <a
                    v-if="selected.evidence_url"
                    :href="selected.evidence_url"
                    class="flex items-center justify-center gap-2 rounded-xl border border-border/70 p-3 text-[10px] font-semibold text-accent"
                    ><Eye class="size-4" /> View evidence</a
                >
            </div>
            <form
                class="grid gap-4 rounded-2xl border border-border/70 p-4 sm:grid-cols-3"
                @submit.prevent="save"
            >
                <label class="space-y-2"
                    ><span class="field-label">Status</span
                    ><select
                        v-model="form.status"
                        class="h-10 w-full rounded-xl border border-border/70 bg-background px-3 text-[10px]"
                    >
                        <option
                            v-for="option in [
                                'open',
                                'acknowledged',
                                'in_progress',
                                'resolved',
                                'closed',
                            ]"
                            :key="option"
                            :value="option"
                        >
                            {{ label(option) }}
                        </option>
                    </select></label
                >
                <label class="space-y-2"
                    ><span class="field-label">Priority</span
                    ><select
                        v-model="form.priority"
                        class="h-10 w-full rounded-xl border border-border/70 bg-background px-3 text-[10px]"
                    >
                        <option
                            v-for="option in [
                                'low',
                                'normal',
                                'urgent',
                                'emergency',
                            ]"
                            :key="option"
                            :value="option"
                        >
                            {{ label(option) }}
                        </option>
                    </select></label
                >
                <label class="space-y-2"
                    ><span class="field-label">Assign to</span
                    ><select
                        v-model="form.assigned_to"
                        class="h-10 w-full rounded-xl border border-border/70 bg-background px-3 text-[10px]"
                    >
                        <option value="">Unassigned</option>
                        <option
                            v-for="person in assignees"
                            :key="person.id"
                            :value="person.id"
                        >
                            {{ person.name }}
                        </option>
                    </select></label
                >
                <label class="space-y-2 sm:col-span-3"
                    ><span class="field-label">Update or internal note</span
                    ><textarea
                        v-model="form.body"
                        rows="3"
                        class="w-full rounded-xl border border-border/70 bg-background p-3 text-xs"
                        placeholder="Explain progress, an appointment, or the resolution…" /><InputError
                        :message="form.errors.body"
                /></label>
                <label
                    class="flex items-start gap-3 rounded-xl bg-secondary/60 p-3 sm:col-span-3"
                    ><input
                        v-model="form.is_internal"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-[var(--accent)]"
                    /><span
                        ><span class="block text-[10px] font-semibold"
                            >Private management note</span
                        ><span
                            class="mt-1 block text-[9px] text-muted-foreground"
                            >Internal notes are never shown or sent to the
                            tenant.</span
                        ></span
                    ></label
                >
            </form>
            <div>
                <h3 class="text-xs font-semibold">Complete activity</h3>
                <div
                    v-if="selected.updates.length"
                    class="mt-3 max-h-64 space-y-3 overflow-y-auto border-l border-border pl-5"
                >
                    <article
                        v-for="update in selected.updates"
                        :key="update.id"
                        class="relative rounded-2xl border p-4"
                        :class="
                            update.internal
                                ? 'border-amber-500/25 bg-amber-500/5'
                                : 'border-border/70'
                        "
                    >
                        <span
                            class="absolute top-5 -left-[1.48rem] size-2 rounded-full bg-accent ring-4 ring-background"
                        />
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[10px] font-semibold">
                                {{ update.sender }}
                                <span
                                    v-if="update.internal"
                                    class="ml-1 text-amber-600"
                                    >· Internal</span
                                >
                            </p>
                            <p class="text-[9px] text-muted-foreground">
                                {{ update.created_at }}
                            </p>
                        </div>
                        <p
                            class="mt-2 text-[11px] leading-5 text-muted-foreground"
                        >
                            {{ update.body }}
                        </p>
                    </article>
                </div>
            </div>
            <DialogFooter
                ><button
                    type="button"
                    class="h-10 rounded-full border border-border px-5 text-xs font-semibold"
                    @click="selected = null"
                >
                    Cancel</button
                ><button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white disabled:opacity-50"
                    :disabled="form.processing"
                    @click="save"
                >
                    <MessageSquareText class="size-4" /> Save &
                    {{ form.is_internal ? 'keep private' : 'notify tenant' }}
                </button></DialogFooter
            >
        </DialogContent>
    </Dialog>
</template>
