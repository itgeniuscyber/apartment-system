<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BellRing,
    CheckCircle2,
    MailWarning,
    MessageCircleMore,
    Plus,
    Search,
    Send,
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
            { title: 'Operations', href: '/messages' },
            { title: 'Messages & notices', href: '/messages' },
        ],
    },
});

type Notice = {
    id: number;
    subject: string;
    body: string;
    severity: string;
    audience: string;
    channels: string[];
    recipient_count: number;
    creator: string | null;
    published_at: string;
};
type Message = {
    id: number;
    body: string;
    sender: string;
    sender_role: string;
    read: boolean;
    created_at: string | null;
};
type Thread = {
    id: number;
    subject: string;
    category: string;
    status: string;
    tenant: { id: number; full_name: string };
    last_message_at: string;
    messages: Message[];
};
type Option = {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
};
const props = defineProps<{
    summary: {
        published: number;
        open_threads: number;
        unread_tenant_messages: number;
        delivery_failures: number;
    };
    notices: Notice[];
    threads: Thread[];
    tenants: Option[];
    estates: Array<{ id: number; name: string }>;
}>();
const tab = ref<'conversations' | 'notices'>('conversations');
const search = ref('');
const publishOpen = ref(false);
const selected = ref<Thread | null>(null);
const notice = useForm({
    subject: '',
    body: '',
    severity: 'info',
    audience_type: 'all',
    estate_id: '',
    tenant_id: '',
    channels: ['database', 'email'] as string[],
});
const reply = useForm({ body: '', status: 'open' });
watch(selected, (thread) => {
    if (thread) {
        reply.body = '';
        reply.status = thread.status;
        reply.clearErrors();
    }
});
const filteredThreads = computed(() => {
    const term = search.value.toLowerCase().trim();

    return props.threads.filter(
        (thread) =>
            !term ||
            [thread.subject, thread.tenant.full_name, thread.category]
                .join(' ')
                .toLowerCase()
                .includes(term),
    );
});
const filteredNotices = computed(() => {
    const term = search.value.toLowerCase().trim();

    return props.notices.filter(
        (item) =>
            !term ||
            [item.subject, item.body, item.audience]
                .join(' ')
                .toLowerCase()
                .includes(term),
    );
});
const toggleChannel = (channel: string): void => {
    notice.channels = notice.channels.includes(channel)
        ? notice.channels.filter((item) => item !== channel)
        : [...notice.channels, channel];
};
const publish = (): void =>
    notice.post('/messages/notices', {
        preserveScroll: true,
        onSuccess: () => {
            publishOpen.value = false;
            notice.reset();
        },
    });
const sendReply = (): void => {
    if (!selected.value) {
        return;
    }

    reply.post(`/messages/threads/${selected.value.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            reply.reset();
        },
    });
};
const openThread = (thread: Thread): void => {
    selected.value = thread;
    router.patch(
        `/messages/threads/${thread.id}/read`,
        {},
        { preserveScroll: true },
    );
};
const label = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const severityClass = (severity: string): string =>
    (
        ({
            urgent: 'bg-red-500/10 text-red-600',
            warning: 'bg-amber-500/10 text-amber-600',
            success: 'bg-emerald-500/10 text-emerald-600',
            info: 'bg-sky-500/10 text-sky-600',
        }) as Record<string, string>
    )[severity] ?? 'bg-secondary text-muted-foreground';
</script>

<template>
    <Head title="Messages & notices" />
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
                    <span>Operations</span><span class="opacity-40">/</span
                    ><span class="text-foreground">Messages</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Messages & notices
                </h1>
                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    Publish targeted announcements and manage private tenant
                    conversations with delivery visibility.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_12px_28px_rgba(242,103,69,0.24)] transition-all hover:-translate-y-0.5"
                @click="publishOpen = true"
            >
                <Plus class="size-4" /> Publish notice
            </button>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="(card, index) in [
                    {
                        label: 'Published notices',
                        value: summary.published,
                        note: 'Recorded announcements',
                        icon: BellRing,
                        color: 'bg-accent/10 text-accent',
                    },
                    {
                        label: 'Open conversations',
                        value: summary.open_threads,
                        note: 'Tenant support threads',
                        icon: MessageCircleMore,
                        color: 'bg-sky-500/10 text-sky-600',
                    },
                    {
                        label: 'Unread tenant replies',
                        value: summary.unread_tenant_messages,
                        note: 'Need management review',
                        icon: MailWarning,
                        color: 'bg-amber-500/10 text-amber-600',
                    },
                    {
                        label: 'Delivery failures',
                        value: summary.delivery_failures,
                        note: 'Review in Communications',
                        icon: summary.delivery_failures
                            ? AlertTriangle
                            : CheckCircle2,
                        color: summary.delivery_failures
                            ? 'bg-red-500/10 text-red-600'
                            : 'bg-emerald-500/10 text-emerald-600',
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
                <div class="inline-flex rounded-full bg-secondary p-1">
                    <button
                        v-for="option in [
                            {
                                key: 'conversations',
                                label: 'Tenant conversations',
                            },
                            { key: 'notices', label: 'Notice history' },
                        ]"
                        :key="option.key"
                        type="button"
                        class="rounded-full px-4 py-2 text-[10px] font-semibold transition-all"
                        :class="
                            tab === option.key
                                ? 'bg-background shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="tab = option.key as 'conversations' | 'notices'"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <label class="relative"
                    ><Search
                        class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" /><input
                        v-model="search"
                        class="h-10 w-56 rounded-xl border border-border/70 bg-background pr-3 pl-9 text-[10px]"
                        placeholder="Search messages…"
                /></label>
            </div>
            <div v-if="tab === 'conversations'">
                <div
                    v-if="filteredThreads.length"
                    class="divide-y divide-border/70"
                >
                    <button
                        v-for="thread in filteredThreads"
                        :key="thread.id"
                        type="button"
                        class="grid w-full gap-3 px-5 py-5 text-left transition-colors hover:bg-secondary/50 sm:grid-cols-[1fr_.7fr_auto] sm:items-center sm:px-6"
                        @click="openThread(thread)"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-xs font-semibold">
                                    {{ thread.subject }}
                                </p>
                                <span
                                    v-if="
                                        thread.messages.some(
                                            (message) =>
                                                message.sender_role ===
                                                    'tenant' && !message.read,
                                        )
                                    "
                                    class="size-2 rounded-full bg-accent"
                                />
                            </div>
                            <p
                                class="mt-1 line-clamp-1 text-[10px] text-muted-foreground"
                            >
                                {{ thread.messages.at(-1)?.body }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold">
                                {{ thread.tenant.full_name }}
                            </p>
                            <p class="mt-1 text-[9px] text-muted-foreground">
                                {{ label(thread.category) }} ·
                                {{ thread.last_message_at }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-[9px] font-bold"
                            :class="
                                thread.status === 'open'
                                    ? 'bg-emerald-500/10 text-emerald-600'
                                    : 'bg-secondary text-muted-foreground'
                            "
                            >{{ label(thread.status) }}</span
                        >
                    </button>
                </div>
                <div
                    v-else
                    class="px-6 py-16 text-center text-[11px] text-muted-foreground"
                >
                    No conversations match this search.
                </div>
            </div>
            <div v-else>
                <div
                    v-if="filteredNotices.length"
                    class="divide-y divide-border/70"
                >
                    <article
                        v-for="item in filteredNotices"
                        :key="item.id"
                        class="px-5 py-5 sm:px-6"
                    >
                        <div
                            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
                        >
                            <div class="max-w-4xl">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-xs font-semibold">
                                        {{ item.subject }}
                                    </h2>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[8px] font-bold"
                                        :class="severityClass(item.severity)"
                                        >{{ label(item.severity) }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 text-[9px] text-muted-foreground"
                                >
                                    {{ item.audience }} ·
                                    {{ item.recipient_count }} recipients ·
                                    {{ item.published_at }}
                                </p>
                                <p
                                    class="mt-3 line-clamp-3 text-[11px] leading-5 whitespace-pre-line text-muted-foreground"
                                >
                                    {{ item.body }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-1.5">
                                <span
                                    v-for="channel in item.channels"
                                    :key="channel"
                                    class="rounded-full bg-secondary px-2 py-1 text-[8px] font-semibold text-muted-foreground"
                                    >{{ label(channel) }}</span
                                >
                            </div>
                        </div>
                    </article>
                </div>
                <div
                    v-else
                    class="px-6 py-16 text-center text-[11px] text-muted-foreground"
                >
                    No notices match this search.
                </div>
            </div>
        </section>
    </div>

    <Dialog v-model:open="publishOpen"
        ><DialogContent
            class="max-h-[92vh] overflow-y-auto rounded-3xl sm:max-w-2xl"
            ><DialogHeader
                ><DialogTitle>Publish a management notice</DialogTitle
                ><DialogDescription
                    >Choose a precise audience and delivery channels. Publishing
                    is immediate and cannot be recalled from email, SMS, or
                    WhatsApp.</DialogDescription
                ></DialogHeader
            >
            <form
                class="mt-3 grid gap-4 sm:grid-cols-2"
                @submit.prevent="publish"
            >
                <label class="space-y-2 sm:col-span-2"
                    ><span class="field-label">Subject</span
                    ><input
                        v-model="notice.subject"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                        placeholder="Important update for residents" /><InputError
                        :message="notice.errors.subject" /></label
                ><label class="space-y-2"
                    ><span class="field-label">Audience</span
                    ><select
                        v-model="notice.audience_type"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                    >
                        <option value="all">All tenants</option>
                        <option value="estate">One estate</option>
                        <option value="tenant">One tenant</option>
                    </select></label
                ><label class="space-y-2"
                    ><span class="field-label">Importance</span
                    ><select
                        v-model="notice.severity"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                    >
                        <option
                            v-for="option in [
                                'info',
                                'success',
                                'warning',
                                'urgent',
                            ]"
                            :key="option"
                            :value="option"
                        >
                            {{ label(option) }}
                        </option>
                    </select></label
                ><label
                    v-if="notice.audience_type === 'estate'"
                    class="space-y-2 sm:col-span-2"
                    ><span class="field-label">Estate</span
                    ><select
                        v-model="notice.estate_id"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                    >
                        <option value="">Choose estate</option>
                        <option
                            v-for="estate in estates"
                            :key="estate.id"
                            :value="estate.id"
                        >
                            {{ estate.name }}
                        </option></select
                    ><InputError :message="notice.errors.estate_id" /></label
                ><label
                    v-if="notice.audience_type === 'tenant'"
                    class="space-y-2 sm:col-span-2"
                    ><span class="field-label">Tenant</span
                    ><select
                        v-model="notice.tenant_id"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                    >
                        <option value="">Choose tenant</option>
                        <option
                            v-for="tenant in tenants"
                            :key="tenant.id"
                            :value="tenant.id"
                        >
                            {{ tenant.full_name }} ·
                            {{
                                tenant.email ||
                                tenant.phone ||
                                'no external contact'
                            }}
                        </option></select
                    ><InputError :message="notice.errors.tenant_id" /></label
                ><label class="space-y-2 sm:col-span-2"
                    ><span class="field-label">Notice</span
                    ><textarea
                        v-model="notice.body"
                        rows="6"
                        class="w-full rounded-2xl border border-border/70 bg-background p-3 text-xs leading-5"
                        placeholder="Write the full notice…" /><InputError
                        :message="notice.errors.body"
                /></label>
                <div class="space-y-2 sm:col-span-2">
                    <span class="field-label">Delivery channels</span>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button
                            v-for="channel in [
                                'database',
                                'email',
                                'sms',
                                'whatsapp',
                            ]"
                            :key="channel"
                            type="button"
                            class="rounded-xl border px-3 py-2.5 text-[10px] font-semibold transition-all"
                            :class="
                                notice.channels.includes(channel)
                                    ? 'border-accent/40 bg-accent/8 text-accent'
                                    : 'border-border/70 text-muted-foreground'
                            "
                            @click="toggleChannel(channel)"
                        >
                            {{
                                channel === 'database'
                                    ? 'Dashboard'
                                    : label(channel)
                            }}
                        </button>
                    </div>
                    <InputError :message="notice.errors.channels" />
                </div>
                <div
                    class="flex gap-2 rounded-2xl bg-amber-500/8 p-3 text-[10px] leading-5 text-amber-700 sm:col-span-2"
                >
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    <p>
                        Only configured channels are sent. Missing tenant email
                        or phone contacts are safely recorded as skipped in the
                        delivery monitor.
                    </p>
                </div>
                <DialogFooter class="sm:col-span-2"
                    ><button
                        type="button"
                        class="h-10 rounded-full border border-border px-5 text-xs font-semibold"
                        @click="publishOpen = false"
                    >
                        Cancel</button
                    ><button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white disabled:opacity-50"
                        :disabled="notice.processing"
                    >
                        <Send class="size-4" /> Publish now
                    </button></DialogFooter
                >
            </form></DialogContent
        ></Dialog
    >

    <Dialog
        :open="selected !== null"
        @update:open="
            (open) => {
                if (!open) selected = null;
            }
        "
        ><DialogContent
            v-if="selected"
            class="max-h-[92vh] overflow-y-auto rounded-3xl sm:max-w-2xl"
            ><DialogHeader
                ><div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-2 py-1 text-[8px] font-bold"
                        :class="
                            selected.status === 'open'
                                ? 'bg-emerald-500/10 text-emerald-600'
                                : 'bg-secondary text-muted-foreground'
                        "
                        >{{ label(selected.status) }}</span
                    ><span class="text-[9px] text-muted-foreground">{{
                        selected.tenant.full_name
                    }}</span>
                </div>
                <DialogTitle class="mt-2">{{ selected.subject }}</DialogTitle
                ><DialogDescription
                    >{{ label(selected.category) }} · last activity
                    {{ selected.last_message_at }}</DialogDescription
                ></DialogHeader
            >
            <div
                class="max-h-[26rem] space-y-3 overflow-y-auto rounded-2xl bg-secondary/45 p-4"
            >
                <article
                    v-for="message in selected.messages"
                    :key="message.id"
                    class="flex"
                    :class="
                        message.sender_role === 'management'
                            ? 'justify-end'
                            : 'justify-start'
                    "
                >
                    <div
                        class="max-w-[85%] rounded-2xl p-3"
                        :class="
                            message.sender_role === 'management'
                                ? 'bg-accent text-white'
                                : 'border border-border/70 bg-background'
                        "
                    >
                        <p
                            class="text-[9px] font-bold"
                            :class="
                                message.sender_role === 'management'
                                    ? 'text-white/75'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ message.sender }} · {{ message.created_at }}
                        </p>
                        <p
                            class="mt-1.5 text-[11px] leading-5 whitespace-pre-line"
                        >
                            {{ message.body }}
                        </p>
                    </div>
                </article>
            </div>
            <form class="space-y-3" @submit.prevent="sendReply">
                <textarea
                    v-model="reply.body"
                    rows="3"
                    class="w-full rounded-2xl border border-border/70 bg-background p-3 text-xs"
                    placeholder="Write management reply…"
                /><InputError :message="reply.errors.body" />
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <label
                        class="flex items-center gap-2 text-[10px] font-semibold"
                        ><input
                            v-model="reply.status"
                            type="checkbox"
                            true-value="closed"
                            false-value="open"
                            class="size-4 accent-[var(--accent)]"
                        />
                        Close conversation after this reply</label
                    ><button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                    >
                        <Send class="size-4" /> Send & notify tenant
                    </button>
                </div>
            </form></DialogContent
        ></Dialog
    >
</template>
