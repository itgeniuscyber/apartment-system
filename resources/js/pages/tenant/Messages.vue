<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    BellRing,
    CheckCheck,
    CircleAlert,
    MailOpen,
    MessageCircleMore,
    Plus,
    Send,
    ShieldCheck,
    Sparkles,
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
            { title: 'Messages & notices', href: '/tenant/messages' },
        ],
    },
});

type Notice = {
    id: number;
    subject: string;
    body: string;
    severity: string;
    from: string;
    published_at: string;
    read: boolean;
};
type Message = {
    id: number;
    body: string;
    sender: string;
    sender_role: string;
    created_at: string | null;
};
type Thread = {
    id: number;
    subject: string;
    category: string;
    status: string;
    last_message_at: string;
    messages: Message[];
};
const props = defineProps<{ notices: Notice[]; threads: Thread[] }>();

const tab = ref<'notices' | 'conversations'>('notices');
const composeOpen = ref(false);
const selected = ref<Thread | null>(null);
const unreadNotices = computed(
    () => props.notices.filter((notice) => !notice.read).length,
);
const openThreads = computed(
    () => props.threads.filter((thread) => thread.status === 'open').length,
);

const compose = useForm({ subject: '', category: 'general', body: '' });
const reply = useForm({ body: '' });

const submit = (): void =>
    compose.post('/tenant/messages', {
        preserveScroll: true,
        onSuccess: () => {
            composeOpen.value = false;
            compose.reset();
            tab.value = 'conversations';
        },
    });
const sendReply = (): void => {
    if (!selected.value) {
        return;
    }

    reply.post(`/tenant/messages/${selected.value.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            reply.reset();
        },
    });
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
                    <span>Resident portal</span><span class="opacity-40">/</span
                    ><span class="text-foreground">Messages</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Messages & notices
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Official announcements and a secure conversation channel
                    with K&R Estates management.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_12px_28px_rgba(242,103,69,0.24)] transition-all hover:-translate-y-0.5"
                @click="composeOpen = true"
            >
                <Plus class="size-4" /> Message management
            </button>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-3">
            <article
                v-for="(card, index) in [
                    {
                        label: 'Management notices',
                        value: notices.length,
                        note: `${unreadNotices} newly received`,
                        icon: BellRing,
                        color: 'bg-accent/10 text-accent',
                    },
                    {
                        label: 'Open conversations',
                        value: openThreads,
                        note: 'Available for your reply',
                        icon: MessageCircleMore,
                        color: 'bg-sky-500/10 text-sky-600',
                    },
                    {
                        label: 'Secure delivery',
                        value: 'Active',
                        note: 'Messages stay linked to your profile',
                        icon: ShieldCheck,
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
            style="--delay: 240ms"
        >
            <div
                class="flex items-center justify-between border-b border-border/70 px-5 py-4 sm:px-6"
            >
                <div class="inline-flex rounded-full bg-secondary p-1">
                    <button
                        v-for="option in [
                            { key: 'notices', label: 'Notices' },
                            { key: 'conversations', label: 'Conversations' },
                        ]"
                        :key="option.key"
                        type="button"
                        class="rounded-full px-4 py-2 text-[10px] font-semibold transition-all"
                        :class="
                            tab === option.key
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="tab = option.key as 'notices' | 'conversations'"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <span class="text-[9px] font-semibold text-muted-foreground"
                    >Dashboard messages are private to your account</span
                >
            </div>
            <div v-if="tab === 'notices'">
                <div v-if="notices.length" class="divide-y divide-border/70">
                    <article
                        v-for="notice in notices"
                        :key="notice.id"
                        class="px-5 py-5 sm:px-6"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="flex min-w-0 gap-3">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl"
                                    :class="severityClass(notice.severity)"
                                    ><CircleAlert class="size-4.5"
                                /></span>
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2 class="text-xs font-semibold">
                                            {{ notice.subject }}
                                        </h2>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[8px] font-bold"
                                            :class="
                                                severityClass(notice.severity)
                                            "
                                            >{{ label(notice.severity) }}</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-[9px] text-muted-foreground"
                                    >
                                        {{ notice.from }} ·
                                        {{ notice.published_at }}
                                    </p>
                                    <p
                                        class="mt-3 max-w-4xl text-[11px] leading-6 whitespace-pre-line text-muted-foreground"
                                    >
                                        {{ notice.body }}
                                    </p>
                                </div>
                            </div>
                            <CheckCheck
                                class="size-4 shrink-0 text-emerald-500"
                            />
                        </div>
                    </article>
                </div>
                <div v-else class="px-6 py-16 text-center">
                    <Sparkles class="mx-auto size-8 text-muted-foreground/35" />
                    <p class="mt-3 text-[11px] text-muted-foreground">
                        There are no management notices yet.
                    </p>
                </div>
            </div>
            <div v-else>
                <div v-if="threads.length" class="divide-y divide-border/70">
                    <button
                        v-for="thread in threads"
                        :key="thread.id"
                        type="button"
                        class="grid w-full gap-3 px-5 py-5 text-left transition-colors hover:bg-secondary/50 sm:grid-cols-[1fr_auto] sm:items-center sm:px-6"
                        @click="selected = thread"
                    >
                        <div class="flex min-w-0 gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600"
                                ><MailOpen class="size-4.5"
                            /></span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-xs font-semibold">
                                        {{ thread.subject }}
                                    </p>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[8px] font-bold"
                                        :class="
                                            thread.status === 'open'
                                                ? 'bg-emerald-500/10 text-emerald-600'
                                                : 'bg-secondary text-muted-foreground'
                                        "
                                        >{{ label(thread.status) }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 line-clamp-1 text-[10px] text-muted-foreground"
                                >
                                    {{ thread.messages.at(-1)?.body }} ·
                                    {{ thread.last_message_at }}
                                </p>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold text-accent"
                            >Open conversation →</span
                        >
                    </button>
                </div>
                <div v-else class="px-6 py-16 text-center">
                    <MessageCircleMore
                        class="mx-auto size-8 text-muted-foreground/35"
                    />
                    <p class="mt-3 text-[11px] text-muted-foreground">
                        Start a secure message whenever you need management
                        help.
                    </p>
                </div>
            </div>
        </section>
    </div>

    <Dialog v-model:open="composeOpen"
        ><DialogContent class="rounded-3xl sm:max-w-xl"
            ><DialogHeader
                ><DialogTitle>Message management</DialogTitle
                ><DialogDescription
                    >Use this for rent, security, neighbour concerns, or a
                    general question. Maintenance faults belong in
                    Requests.</DialogDescription
                ></DialogHeader
            >
            <form class="mt-3 space-y-4" @submit.prevent="submit">
                <label class="space-y-2"
                    ><span class="field-label">Subject</span
                    ><input
                        v-model="compose.subject"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                        placeholder="How can management help?" /><InputError
                        :message="compose.errors.subject" /></label
                ><label class="space-y-2"
                    ><span class="field-label">Category</span
                    ><select
                        v-model="compose.category"
                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                    >
                        <option
                            v-for="category in [
                                'general',
                                'rent',
                                'security',
                                'neighbour',
                                'other',
                            ]"
                            :key="category"
                            :value="category"
                        >
                            {{ label(category) }}
                        </option>
                    </select></label
                ><label class="space-y-2"
                    ><span class="field-label">Message</span
                    ><textarea
                        v-model="compose.body"
                        rows="6"
                        class="w-full rounded-2xl border border-border/70 bg-background p-3 text-xs leading-5"
                        placeholder="Write your message…" /><InputError
                        :message="compose.errors.body" /></label
                ><DialogFooter
                    ><button
                        type="button"
                        class="h-10 rounded-full border border-border px-5 text-xs font-semibold"
                        @click="composeOpen = false"
                    >
                        Cancel</button
                    ><button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                        :disabled="compose.processing"
                    >
                        <Send class="size-4" /> Send securely
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
            class="max-h-[90vh] overflow-y-auto rounded-3xl sm:max-w-2xl"
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
                        label(selected.category)
                    }}</span>
                </div>
                <DialogTitle class="mt-2">{{ selected.subject }}</DialogTitle
                ><DialogDescription
                    >Last activity
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
                        message.sender_role === 'tenant'
                            ? 'justify-end'
                            : 'justify-start'
                    "
                >
                    <div
                        class="max-w-[85%] rounded-2xl p-3"
                        :class="
                            message.sender_role === 'tenant'
                                ? 'bg-accent text-white'
                                : 'border border-border/70 bg-background'
                        "
                    >
                        <p
                            class="text-[9px] font-bold"
                            :class="
                                message.sender_role === 'tenant'
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
            <form
                v-if="selected.status === 'open'"
                class="space-y-3"
                @submit.prevent="sendReply"
            >
                <textarea
                    v-model="reply.body"
                    rows="3"
                    class="w-full rounded-2xl border border-border/70 bg-background p-3 text-xs"
                    placeholder="Write a reply…"
                /><InputError :message="reply.errors.body" />
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white"
                    >
                        <Send class="size-4" /> Send reply
                    </button>
                </div>
            </form>
            <p
                v-else
                class="rounded-2xl bg-secondary p-4 text-center text-[10px] text-muted-foreground"
            >
                Management has closed this conversation. Start a new message if
                you need further assistance.
            </p></DialogContent
        ></Dialog
    >
</template>
