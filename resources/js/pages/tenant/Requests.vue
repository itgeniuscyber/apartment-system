<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock3,
    FileUp,
    Home,
    Image,
    MessageSquareText,
    Plus,
    Send,
    Sparkles,
    Wrench,
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
            { title: 'Resident portal', href: '/dashboard' },
            { title: 'Maintenance requests', href: '/tenant/requests' },
        ],
    },
});

type Update = {
    id: number;
    body: string;
    status: string | null;
    sender: string;
    sender_role: string;
    created_at: string | null;
};

type RequestItem = {
    id: number;
    reference: string;
    category: string;
    title: string;
    description: string;
    priority: string;
    status: string;
    access_permission: boolean;
    preferred_visit_at: string | null;
    evidence_url: string | null;
    assignee: string | null;
    apartment: { code: string; number: string; estate: string } | null;
    created_at: string | null;
    updates: Update[];
};

const props = defineProps<{
    tenant: {
        id: number;
        full_name: string;
        has_active_lease: boolean;
    } | null;
    summary: { open: number; in_progress: number; resolved: number };
    requests: RequestItem[];
}>();

const createOpen = ref(false);
const selected = ref<RequestItem | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const createForm = useForm({
    category: 'plumbing',
    title: '',
    description: '',
    priority: 'normal',
    access_permission: false,
    preferred_visit_at: '',
    evidence: null as File | null,
});

const replyForm = useForm({ body: '' });

const activeRequests = computed(() =>
    props.requests.filter(
        (item) => !['resolved', 'closed'].includes(item.status),
    ),
);

const submitRequest = (): void => {
    createForm.post('/tenant/requests', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
};

const sendReply = (): void => {
    if (!selected.value) {
        return;
    }

    replyForm.post(`/tenant/requests/${selected.value.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            replyForm.reset();
        },
    });
};

const pickEvidence = (event: Event): void => {
    createForm.evidence = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const label = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusClass = (status: string): string =>
    (
        ({
            open: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
            acknowledged: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
            in_progress:
                'bg-violet-500/10 text-violet-600 dark:text-violet-400',
            resolved:
                'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            closed: 'bg-secondary text-muted-foreground',
        }) as Record<string, string>
    )[status] ?? 'bg-secondary text-muted-foreground';
</script>

<template>
    <Head title="Maintenance requests" />

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
                    <span>Resident portal</span><span class="opacity-40">/</span
                    ><span class="text-foreground">Maintenance</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Maintenance requests
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Report an issue, safely attach evidence, and follow every
                    management update in one clear timeline.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_12px_28px_rgba(242,103,69,0.24)] transition-all hover:-translate-y-0.5 disabled:opacity-50"
                :disabled="!tenant"
                @click="createOpen = true"
            >
                <Plus class="size-4" /> New request
            </button>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="(card, index) in [
                    {
                        label: 'Open requests',
                        value: summary.open,
                        note: 'Awaiting action or confirmation',
                        icon: Clock3,
                        color: 'bg-amber-500/10 text-amber-600',
                    },
                    {
                        label: 'In progress',
                        value: summary.in_progress,
                        note: 'Currently being worked on',
                        icon: Wrench,
                        color: 'bg-violet-500/10 text-violet-600',
                    },
                    {
                        label: 'Resolved',
                        value: summary.resolved,
                        note: 'Completed service records',
                        icon: CheckCircle2,
                        color: 'bg-emerald-500/10 text-emerald-600',
                    },
                    {
                        label: 'Current apartment',
                        value:
                            activeRequests[0]?.apartment?.code || 'Connected',
                        note: tenant?.has_active_lease
                            ? 'Active tenancy verified'
                            : 'No active lease found',
                        icon: Home,
                        color: 'bg-sky-500/10 text-sky-600',
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
                class="flex items-center justify-between border-b border-border/70 px-5 py-4 sm:px-6"
            >
                <div>
                    <h2 class="text-base font-semibold">Request history</h2>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Open a request to see updates or reply
                    </p>
                </div>
                <span
                    class="rounded-full bg-secondary px-3 py-1.5 text-[9px] font-bold text-muted-foreground"
                    >{{ requests.length }} total</span
                >
            </div>
            <div v-if="requests.length" class="divide-y divide-border/70">
                <button
                    v-for="requestItem in requests"
                    :key="requestItem.id"
                    type="button"
                    class="grid w-full gap-4 px-5 py-5 text-left transition-colors hover:bg-secondary/50 sm:px-6 lg:grid-cols-[1.25fr_.8fr_.55fr_auto] lg:items-center"
                    @click="selected = requestItem"
                >
                    <div class="flex min-w-0 gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                            ><Wrench class="size-4.5"
                        /></span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-xs font-semibold">
                                    {{ requestItem.title }}
                                </p>
                                <span
                                    v-if="requestItem.priority === 'urgent'"
                                    class="rounded-full bg-red-500/10 px-2 py-0.5 text-[8px] font-bold text-red-600"
                                    >Urgent</span
                                >
                            </div>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ requestItem.reference }} ·
                                {{ label(requestItem.category) }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <p
                            class="text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Apartment
                        </p>
                        <p class="mt-1 text-[11px] font-semibold">
                            {{
                                requestItem.apartment
                                    ? `${requestItem.apartment.estate} · ${requestItem.apartment.number}`
                                    : 'Not linked'
                            }}
                        </p>
                    </div>
                    <div>
                        <span
                            class="rounded-full px-2.5 py-1 text-[9px] font-bold"
                            :class="statusClass(requestItem.status)"
                            >{{ label(requestItem.status) }}</span
                        >
                        <p class="mt-2 text-[9px] text-muted-foreground">
                            {{ requestItem.created_at }}
                        </p>
                    </div>
                    <span class="text-[10px] font-bold text-accent"
                        >View timeline →</span
                    >
                </button>
            </div>
            <div v-else class="px-6 py-16 text-center">
                <Sparkles class="mx-auto size-8 text-muted-foreground/35" />
                <h3 class="mt-4 text-sm font-semibold">
                    No maintenance requests
                </h3>
                <p class="mt-2 text-[11px] text-muted-foreground">
                    Your future requests and updates will be kept here.
                </p>
            </div>
        </section>
    </div>

    <Dialog v-model:open="createOpen">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto rounded-3xl sm:max-w-2xl"
        >
            <DialogHeader
                ><DialogTitle>Report a maintenance issue</DialogTitle
                ><DialogDescription
                    >Give management enough detail to assess and assign the
                    right help.</DialogDescription
                ></DialogHeader
            >
            <form
                class="mt-3 grid gap-4 sm:grid-cols-2"
                @submit.prevent="submitRequest"
            >
                <label class="space-y-2"
                    ><span class="field-label">Category</span
                    ><select
                        v-model="createForm.category"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                    >
                        <option
                            v-for="option in [
                                'plumbing',
                                'electrical',
                                'security',
                                'appliance',
                                'structural',
                                'cleaning',
                                'other',
                            ]"
                            :key="option"
                            :value="option"
                        >
                            {{ label(option) }}
                        </option>
                    </select></label
                >
                <label class="space-y-2"
                    ><span class="field-label">Urgency</span
                    ><select
                        v-model="createForm.priority"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                    >
                        <option value="normal">Normal</option>
                        <option value="urgent">Urgent</option>
                    </select></label
                >
                <label class="space-y-2 sm:col-span-2"
                    ><span class="field-label">Short title</span
                    ><input
                        v-model="createForm.title"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                        placeholder="e.g. Water leaking below kitchen sink" /><InputError
                        :message="createForm.errors.title"
                /></label>
                <label class="space-y-2 sm:col-span-2"
                    ><span class="field-label">What is happening?</span
                    ><textarea
                        v-model="createForm.description"
                        rows="5"
                        class="w-full rounded-2xl border border-border/70 bg-background p-3 text-xs leading-5"
                        placeholder="Describe when it started, what you have observed, and anything urgent management should know." /><InputError
                        :message="createForm.errors.description"
                /></label>
                <label class="space-y-2"
                    ><span class="field-label">Preferred visit time</span
                    ><input
                        v-model="createForm.preferred_visit_at"
                        type="datetime-local"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs" /><InputError
                        :message="createForm.errors.preferred_visit_at"
                /></label>
                <div class="space-y-2">
                    <span class="field-label">Photo or PDF evidence</span
                    ><button
                        type="button"
                        class="flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-secondary/40 px-3 text-[10px] font-semibold"
                        @click="fileInput?.click()"
                    >
                        <FileUp class="size-4" />{{
                            createForm.evidence?.name ||
                            'Choose file (max 8 MB)'
                        }}</button
                    ><input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        class="hidden"
                        @change="pickEvidence"
                    /><InputError :message="createForm.errors.evidence" />
                </div>
                <label
                    class="flex items-start gap-3 rounded-2xl border border-border/70 p-4 sm:col-span-2"
                    ><input
                        v-model="createForm.access_permission"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-[var(--accent)]"
                    /><span
                        ><span class="block text-[11px] font-semibold"
                            >I allow approved maintenance staff to access the
                            unit</span
                        ><span
                            class="mt-1 block text-[9px] leading-4 text-muted-foreground"
                            >Management should still coordinate the visit.
                            Untick this if you must be present.</span
                        ></span
                    ></label
                >
                <DialogFooter class="mt-2 sm:col-span-2"
                    ><button
                        type="button"
                        class="h-10 rounded-full border border-border px-5 text-xs font-semibold"
                        @click="createOpen = false"
                    >
                        Cancel</button
                    ><button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white disabled:opacity-50"
                        :disabled="createForm.processing"
                    >
                        <Send class="size-4" /> Submit request
                    </button></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>

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
            class="max-h-[90vh] overflow-y-auto rounded-3xl sm:max-w-2xl"
        >
            <DialogHeader
                ><div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-[9px] font-bold"
                        :class="statusClass(selected.status)"
                        >{{ label(selected.status) }}</span
                    ><span
                        v-if="selected.priority === 'urgent'"
                        class="rounded-full bg-red-500/10 px-2.5 py-1 text-[9px] font-bold text-red-600"
                        >Urgent</span
                    >
                </div>
                <DialogTitle class="mt-3">{{ selected.title }}</DialogTitle
                ><DialogDescription
                    >{{ selected.reference }} · submitted
                    {{ selected.created_at }}</DialogDescription
                ></DialogHeader
            >
            <div
                class="mt-2 rounded-2xl bg-secondary/55 p-4 text-xs leading-6 text-muted-foreground"
            >
                {{ selected.description }}
            </div>
            <div class="grid gap-3 text-[10px] sm:grid-cols-3">
                <div class="rounded-xl border border-border/70 p-3">
                    <p class="field-label">Apartment</p>
                    <p class="mt-1.5 font-semibold">
                        {{ selected.apartment?.code || 'Not linked' }}
                    </p>
                </div>
                <div class="rounded-xl border border-border/70 p-3">
                    <p class="field-label">Assigned to</p>
                    <p class="mt-1.5 font-semibold">
                        {{ selected.assignee || 'Awaiting assignment' }}
                    </p>
                </div>
                <a
                    v-if="selected.evidence_url"
                    :href="selected.evidence_url"
                    class="flex items-center gap-2 rounded-xl border border-border/70 p-3 font-semibold text-accent"
                    ><Image class="size-4" /> Open evidence</a
                >
            </div>
            <div>
                <h3 class="text-xs font-semibold">Update timeline</h3>
                <div
                    v-if="selected.updates.length"
                    class="mt-3 space-y-3 border-l border-border pl-5"
                >
                    <article
                        v-for="update in selected.updates"
                        :key="update.id"
                        class="relative rounded-2xl border border-border/70 p-4"
                    >
                        <span
                            class="absolute top-5 -left-[1.48rem] size-2 rounded-full bg-accent ring-4 ring-background"
                        />
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[10px] font-semibold">
                                {{ update.sender }}
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
                <p
                    v-else
                    class="mt-3 rounded-2xl border border-dashed border-border p-5 text-center text-[10px] text-muted-foreground"
                >
                    Management updates will appear here.
                </p>
            </div>
            <form
                v-if="!['closed'].includes(selected.status)"
                class="rounded-2xl border border-border/70 p-4"
                @submit.prevent="sendReply"
            >
                <label class="space-y-2"
                    ><span class="field-label">Add information or reply</span
                    ><textarea
                        v-model="replyForm.body"
                        rows="3"
                        class="w-full rounded-xl border border-border/70 bg-background p-3 text-xs"
                        placeholder="Share a useful update with management…" /><InputError
                        :message="replyForm.errors.body"
                /></label>
                <div class="mt-3 flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex h-9 items-center gap-2 rounded-full bg-accent px-4 text-[10px] font-semibold text-white"
                        :disabled="replyForm.processing"
                    >
                        <MessageSquareText class="size-3.5" /> Send update
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
