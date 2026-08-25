<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Activity,
    Clock3,
    KeyRound,
    Mail,
    MessageCircleMore,
    RefreshCw,
    Save,
    Send,
    ShieldCheck,
    Smartphone,
    TriangleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Oversight', href: '/communication-settings' },
            { title: 'Communications', href: '/communication-settings' },
        ],
    },
});

type Settings = {
    in_app_enabled: boolean;
    email_enabled: boolean;
    email_from_name: string | null;
    email_from_address: string | null;
    email_ready: boolean;
    email_provider: string;
    sms_enabled: boolean;
    sms_provider: string;
    sms_sender_id: string | null;
    sms_username: string | null;
    sms_api_key_saved: boolean;
    sms_webhook_url: string | null;
    sms_webhook_token_saved: boolean;
    sms_ready: boolean;
    whatsapp_enabled: boolean;
    whatsapp_provider: string;
    whatsapp_phone_number_id: string | null;
    whatsapp_access_token_saved: boolean;
    whatsapp_api_version: string;
    whatsapp_notice_template: string | null;
    whatsapp_invoice_template: string | null;
    whatsapp_receipt_template: string | null;
    whatsapp_reminder_template: string | null;
    whatsapp_ready: boolean;
    invoice_channels: string[];
    receipt_channels: string[];
    reminder_channels: string[];
    reminder_days_before: number[];
    overdue_repeat_days: number;
};

type Delivery = {
    id: number;
    tenant: string | null;
    event_type: string;
    reference: string | null;
    channel: string;
    provider: string | null;
    recipient: string | null;
    status: string;
    attempts: number;
    error: string | null;
    sent_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    settings: Settings;
    delivery_summary: {
        queued: number;
        sent: number;
        failed: number;
        skipped: number;
    };
    deliveries: Delivery[];
}>();

const form = useForm({
    in_app_enabled: props.settings.in_app_enabled,
    email_enabled: props.settings.email_enabled,
    email_from_name: props.settings.email_from_name ?? '',
    email_from_address: props.settings.email_from_address ?? '',
    sms_enabled: props.settings.sms_enabled,
    sms_provider: props.settings.sms_provider,
    sms_sender_id: props.settings.sms_sender_id ?? '',
    sms_username: props.settings.sms_username ?? '',
    sms_api_key: '',
    sms_webhook_url: props.settings.sms_webhook_url ?? '',
    sms_webhook_token: '',
    whatsapp_enabled: props.settings.whatsapp_enabled,
    whatsapp_provider: props.settings.whatsapp_provider,
    whatsapp_phone_number_id: props.settings.whatsapp_phone_number_id ?? '',
    whatsapp_access_token: '',
    whatsapp_api_version: props.settings.whatsapp_api_version,
    whatsapp_notice_template: props.settings.whatsapp_notice_template ?? '',
    whatsapp_invoice_template: props.settings.whatsapp_invoice_template ?? '',
    whatsapp_receipt_template: props.settings.whatsapp_receipt_template ?? '',
    whatsapp_reminder_template: props.settings.whatsapp_reminder_template ?? '',
    invoice_channels: [...props.settings.invoice_channels],
    receipt_channels: [...props.settings.receipt_channels],
    reminder_channels: [...props.settings.reminder_channels],
    reminder_days_before: [...props.settings.reminder_days_before],
    overdue_repeat_days: props.settings.overdue_repeat_days,
});

const reminderDays = computed({
    get: () => form.reminder_days_before.join(', '),
    set: (value: string) => {
        form.reminder_days_before = Array.from(
            new Set(
                value
                    .split(',')
                    .map((day) => Number(day.trim()))
                    .filter((day) => Number.isInteger(day) && day >= 0),
            ),
        );
    },
});

const channelOptions = [
    { value: 'database', label: 'Dashboard', icon: Activity },
    { value: 'email', label: 'Email', icon: Mail },
    { value: 'sms', label: 'SMS', icon: Smartphone },
    { value: 'whatsapp', label: 'WhatsApp', icon: MessageCircleMore },
];

type ChannelField =
    'invoice_channels' | 'receipt_channels' | 'reminder_channels';

const deliveryPolicies: Array<{
    field: ChannelField;
    title: string;
    note: string;
}> = [
    {
        field: 'invoice_channels',
        title: 'New invoice issued',
        note: 'The invoice PDF is attached to email.',
    },
    {
        field: 'receipt_channels',
        title: 'Payment receipt issued',
        note: 'The official receipt PDF is attached.',
    },
    {
        field: 'reminder_channels',
        title: 'Rent reminders & arrears',
        note: 'Runs from the daily reminder schedule.',
    },
];

const toggleChannel = (field: ChannelField, channel: string): void => {
    const values = form[field];
    form[field] = values.includes(channel)
        ? values.filter((value) => value !== channel)
        : [...values, channel];
};

const hasChannel = (field: ChannelField, channel: string): boolean =>
    form[field].includes(channel);

const save = (): void => {
    form.patch('/communication-settings', {
        preserveScroll: true,
        onSuccess: () => {
            form.sms_api_key = '';
            form.sms_webhook_token = '';
            form.whatsapp_access_token = '';
        },
    });
};

const label = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusClass = (status: string): string =>
    (
        ({
            sent: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            failed: 'bg-red-500/10 text-red-600 dark:text-red-400',
            queued: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
            skipped: 'bg-secondary text-muted-foreground',
        }) as Record<string, string>
    )[status] ?? 'bg-secondary text-muted-foreground';
</script>

<template>
    <Head title="Communication settings" />

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
                    <span>Oversight</span><span class="opacity-40">/</span
                    ><span class="text-foreground">Communications</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Communication control centre
                </h1>
                <p
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    Control delivery channels, provider credentials, rent
                    reminders, and financial document notifications without
                    editing code.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-accent px-5 text-xs font-semibold text-white shadow-[0_12px_28px_rgba(242,103,69,0.24)] transition-all hover:-translate-y-0.5 disabled:opacity-50"
                :disabled="form.processing"
                @click="save"
            >
                <RefreshCw v-if="form.processing" class="size-4 animate-spin" />
                <Save v-else class="size-4" /> Save configuration
            </button>
        </section>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="(card, index) in [
                    {
                        title: 'Dashboard alerts',
                        detail: 'Instant and always linked to the right record',
                        ready: settings.in_app_enabled,
                        icon: Activity,
                    },
                    {
                        title: 'Email delivery',
                        detail: `${settings.email_provider} · PDF attachments enabled`,
                        ready: settings.email_enabled && settings.email_ready,
                        icon: Mail,
                    },
                    {
                        title: 'SMS gateway',
                        detail: settings.sms_provider.replaceAll('_', ' '),
                        ready: settings.sms_ready,
                        icon: Smartphone,
                    },
                    {
                        title: 'WhatsApp Cloud',
                        detail: 'Official Meta template messaging',
                        ready: settings.whatsapp_ready,
                        icon: MessageCircleMore,
                    },
                ]"
                :key="card.title"
                class="surface-card card-lift dashboard-enter p-5"
                :style="`--delay: ${80 + index * 45}ms`"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                        ><component :is="card.icon" class="size-5"
                    /></span>
                    <span
                        class="rounded-full px-2.5 py-1 text-[9px] font-bold"
                        :class="
                            card.ready
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                        "
                        >{{ card.ready ? 'Ready' : 'Setup needed' }}</span
                    >
                </div>
                <p class="mt-4 text-sm font-semibold">{{ card.title }}</p>
                <p class="mt-1 text-[11px] leading-5 text-muted-foreground">
                    {{ card.detail }}
                </p>
            </article>
        </section>

        <form
            class="mt-5 grid items-start gap-5 xl:grid-cols-[1.1fr_.9fr]"
            @submit.prevent="save"
        >
            <div class="space-y-5">
                <section
                    class="surface-card dashboard-enter p-5 sm:p-6"
                    style="--delay: 220ms"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                            ><Mail class="size-5"
                        /></span>
                        <div>
                            <h2 class="text-base font-semibold">
                                Dashboard & email
                            </h2>
                            <p
                                class="mt-1 text-[11px] leading-5 text-muted-foreground"
                            >
                                Email uses the server SMTP connection already
                                configured securely.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label
                            class="flex items-center justify-between rounded-2xl border border-border/70 p-4"
                            ><span
                                ><span class="block text-xs font-semibold"
                                    >Instant dashboard alerts</span
                                ><span
                                    class="mt-1 block text-[10px] text-muted-foreground"
                                    >Bell notification and record link</span
                                ></span
                            ><input
                                v-model="form.in_app_enabled"
                                type="checkbox"
                                class="size-4 accent-[var(--accent)]"
                        /></label>
                        <label
                            class="flex items-center justify-between rounded-2xl border border-border/70 p-4"
                            ><span
                                ><span class="block text-xs font-semibold"
                                    >Automatic email</span
                                ><span
                                    class="mt-1 block text-[10px] text-muted-foreground"
                                    >Invoices, receipts, reminders and
                                    notices</span
                                ></span
                            ><input
                                v-model="form.email_enabled"
                                type="checkbox"
                                class="size-4 accent-[var(--accent)]"
                        /></label>
                        <label class="space-y-2"
                            ><span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Sender name</span
                            ><input
                                v-model="form.email_from_name"
                                class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                placeholder="K&R Estates Management" /><InputError
                                :message="form.errors.email_from_name"
                        /></label>
                        <label class="space-y-2"
                            ><span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Sender email</span
                            ><input
                                v-model="form.email_from_address"
                                type="email"
                                class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs" /><InputError
                                :message="form.errors.email_from_address"
                        /></label>
                    </div>
                </section>

                <section
                    class="surface-card dashboard-enter p-5 sm:p-6"
                    style="--delay: 260ms"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400"
                                ><Smartphone class="size-5"
                            /></span>
                            <div>
                                <h2 class="text-base font-semibold">
                                    SMS provider
                                </h2>
                                <p
                                    class="mt-1 text-[11px] leading-5 text-muted-foreground"
                                >
                                    Use Africa's Talking or connect another
                                    provider through a signed webhook.
                                </p>
                            </div>
                        </div>
                        <input
                            v-model="form.sms_enabled"
                            type="checkbox"
                            class="mt-3 size-4 accent-[var(--accent)]"
                        />
                    </div>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label class="space-y-2"
                            ><span class="field-label">Provider</span
                            ><select
                                v-model="form.sms_provider"
                                class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                            >
                                <option value="africas_talking">
                                    Africa's Talking
                                </option>
                                <option value="generic_webhook">
                                    Generic webhook
                                </option>
                            </select></label
                        >
                        <label class="space-y-2"
                            ><span class="field-label">Sender ID</span
                            ><input
                                v-model="form.sms_sender_id"
                                class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                placeholder="KR Estates"
                        /></label>
                        <template
                            v-if="form.sms_provider === 'africas_talking'"
                        >
                            <label class="space-y-2"
                                ><span class="field-label">Username</span
                                ><input
                                    v-model="form.sms_username"
                                    class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                    placeholder="sandbox or production username"
                            /></label>
                            <label class="space-y-2"
                                ><span class="field-label"
                                    >API key
                                    <span
                                        v-if="settings.sms_api_key_saved"
                                        class="text-emerald-600"
                                        >· saved</span
                                    ></span
                                ><input
                                    v-model="form.sms_api_key"
                                    type="password"
                                    class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                    :placeholder="
                                        settings.sms_api_key_saved
                                            ? 'Leave blank to keep saved key'
                                            : 'Paste API key'
                                    "
                            /></label>
                        </template>
                        <template v-else>
                            <label class="space-y-2"
                                ><span class="field-label">HTTPS endpoint</span
                                ><input
                                    v-model="form.sms_webhook_url"
                                    type="url"
                                    class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                    placeholder="https://provider.example/send"
                            /></label>
                            <label class="space-y-2"
                                ><span class="field-label"
                                    >Bearer token
                                    <span
                                        v-if="settings.sms_webhook_token_saved"
                                        class="text-emerald-600"
                                        >· saved</span
                                    ></span
                                ><input
                                    v-model="form.sms_webhook_token"
                                    type="password"
                                    class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                    :placeholder="
                                        settings.sms_webhook_token_saved
                                            ? 'Leave blank to keep saved token'
                                            : 'Optional token'
                                    "
                            /></label>
                        </template>
                    </div>
                    <InputError
                        class="mt-3"
                        :message="
                            form.errors.sms_api_key ||
                            form.errors.sms_webhook_url
                        "
                    />
                </section>

                <section
                    class="surface-card dashboard-enter p-5 sm:p-6"
                    style="--delay: 300ms"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                ><MessageCircleMore class="size-5"
                            /></span>
                            <div>
                                <h2 class="text-base font-semibold">
                                    WhatsApp Business
                                </h2>
                                <p
                                    class="mt-1 text-[11px] leading-5 text-muted-foreground"
                                >
                                    Official Meta Cloud API. Proactive messages
                                    require approved templates.
                                </p>
                            </div>
                        </div>
                        <input
                            v-model="form.whatsapp_enabled"
                            type="checkbox"
                            class="mt-3 size-4 accent-[var(--accent)]"
                        />
                    </div>
                    <div
                        class="mt-4 flex gap-2 rounded-2xl bg-amber-500/8 p-3 text-[10px] leading-5 text-amber-700 dark:text-amber-300"
                    >
                        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                        <p>
                            Each template should use four body placeholders in
                            this order: tenant first name, message title,
                            reference, and message summary.
                        </p>
                    </div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <label class="space-y-2"
                            ><span class="field-label">Phone number ID</span
                            ><input
                                v-model="form.whatsapp_phone_number_id"
                                class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                        /></label>
                        <label class="space-y-2"
                            ><span class="field-label">Graph API version</span
                            ><input
                                v-model="form.whatsapp_api_version"
                                class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                placeholder="v23.0"
                        /></label>
                        <label class="space-y-2 sm:col-span-2"
                            ><span class="field-label"
                                >Permanent access token
                                <span
                                    v-if="settings.whatsapp_access_token_saved"
                                    class="text-emerald-600"
                                    >· encrypted & saved</span
                                ></span
                            ><input
                                v-model="form.whatsapp_access_token"
                                type="password"
                                class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                :placeholder="
                                    settings.whatsapp_access_token_saved
                                        ? 'Leave blank to keep saved token'
                                        : 'Paste Meta access token'
                                "
                        /></label>
                        <label
                            v-for="field in [
                                {
                                    key: 'whatsapp_notice_template',
                                    label: 'Notice template',
                                },
                                {
                                    key: 'whatsapp_invoice_template',
                                    label: 'Invoice template',
                                },
                                {
                                    key: 'whatsapp_receipt_template',
                                    label: 'Receipt template',
                                },
                                {
                                    key: 'whatsapp_reminder_template',
                                    label: 'Reminder template',
                                },
                            ]"
                            :key="field.key"
                            class="space-y-2"
                            ><span class="field-label">{{ field.label }}</span
                            ><input
                                v-model="form[field.key as keyof typeof form]"
                                class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                placeholder="approved_template_name"
                        /></label>
                    </div>
                    <InputError
                        class="mt-3"
                        :message="
                            form.errors.whatsapp_access_token ||
                            form.errors.whatsapp_api_version
                        "
                    />
                </section>
            </div>

            <div class="space-y-5 xl:sticky xl:top-24">
                <section
                    class="surface-card dashboard-enter p-5 sm:p-6"
                    style="--delay: 230ms"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                            ><Send class="size-5"
                        /></span>
                        <div>
                            <h2 class="text-base font-semibold">
                                Automatic delivery rules
                            </h2>
                            <p
                                class="mt-1 text-[11px] leading-5 text-muted-foreground"
                            >
                                Choose exactly where each important financial
                                event is delivered.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 space-y-5">
                        <div
                            v-for="policy in deliveryPolicies"
                            :key="policy.field"
                        >
                            <p class="text-xs font-semibold">
                                {{ policy.title }}
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ policy.note }}
                            </p>
                            <div class="mt-2.5 grid grid-cols-2 gap-2">
                                <button
                                    v-for="channel in channelOptions"
                                    :key="channel.value"
                                    type="button"
                                    class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-[10px] font-semibold transition-all"
                                    :class="
                                        hasChannel(policy.field, channel.value)
                                            ? 'border-accent/40 bg-accent/8 text-accent'
                                            : 'border-border/70 text-muted-foreground hover:bg-secondary'
                                    "
                                    @click="
                                        toggleChannel(
                                            policy.field,
                                            channel.value,
                                        )
                                    "
                                >
                                    <component
                                        :is="channel.icon"
                                        class="size-3.5"
                                    />{{ channel.label }}
                                </button>
                            </div>
                            <InputError
                                class="mt-2"
                                :message="form.errors[policy.field]"
                            />
                        </div>
                        <div
                            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2"
                        >
                            <label class="space-y-2"
                                ><span class="field-label"
                                    >Reminder days before due</span
                                ><input
                                    v-model="reminderDays"
                                    class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 text-xs"
                                    placeholder="7, 3, 0"
                                /><span
                                    class="block text-[9px] text-muted-foreground"
                                    >Comma-separated, including 0 for due
                                    date.</span
                                ></label
                            >
                            <label class="space-y-2"
                                ><span class="field-label"
                                    >Repeat overdue every</span
                                >
                                <div class="relative">
                                    <input
                                        v-model.number="
                                            form.overdue_repeat_days
                                        "
                                        type="number"
                                        min="1"
                                        max="30"
                                        class="h-11 w-full rounded-xl border border-border/70 bg-background px-3 pr-12 text-xs"
                                    /><span
                                        class="absolute top-1/2 right-3 -translate-y-1/2 text-[9px] text-muted-foreground"
                                        >days</span
                                    >
                                </div></label
                            >
                        </div>
                    </div>
                </section>

                <section
                    class="surface-card dashboard-enter overflow-hidden"
                    style="--delay: 280ms"
                >
                    <div
                        class="flex items-center justify-between border-b border-border/70 p-5"
                    >
                        <div>
                            <h2 class="text-base font-semibold">
                                Delivery monitor
                            </h2>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                Latest email, SMS and WhatsApp activity
                            </p>
                        </div>
                        <ShieldCheck class="size-5 text-accent" />
                    </div>
                    <div class="grid grid-cols-4 border-b border-border/70">
                        <div
                            v-for="item in [
                                {
                                    label: 'Sent',
                                    value: delivery_summary.sent,
                                    color: 'text-emerald-600',
                                },
                                {
                                    label: 'Queued',
                                    value: delivery_summary.queued,
                                    color: 'text-amber-600',
                                },
                                {
                                    label: 'Failed',
                                    value: delivery_summary.failed,
                                    color: 'text-red-600',
                                },
                                {
                                    label: 'Skipped',
                                    value: delivery_summary.skipped,
                                    color: 'text-muted-foreground',
                                },
                            ]"
                            :key="item.label"
                            class="border-r border-border/70 px-2 py-4 text-center last:border-0"
                        >
                            <p
                                class="money text-lg font-semibold"
                                :class="item.color"
                            >
                                {{ item.value }}
                            </p>
                            <p
                                class="mt-1 text-[8px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                {{ item.label }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="deliveries.length"
                        class="max-h-[27rem] divide-y divide-border/60 overflow-y-auto"
                    >
                        <div
                            v-for="delivery in deliveries"
                            :key="delivery.id"
                            class="p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-[11px] font-semibold"
                                    >
                                        {{
                                            delivery.tenant || 'Unknown tenant'
                                        }}
                                        · {{ label(delivery.event_type) }}
                                    </p>
                                    <p
                                        class="mt-1 text-[9px] text-muted-foreground"
                                    >
                                        {{ label(delivery.channel) }} ·
                                        {{
                                            delivery.recipient ||
                                            'No destination'
                                        }}
                                        · {{ delivery.created_at }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2 py-1 text-[8px] font-bold"
                                    :class="statusClass(delivery.status)"
                                    >{{ label(delivery.status) }}</span
                                >
                            </div>
                            <p
                                v-if="delivery.error"
                                class="mt-2 line-clamp-2 text-[9px] leading-4 text-red-600"
                            >
                                {{ delivery.error }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="px-6 py-10 text-center">
                        <Clock3
                            class="mx-auto size-6 text-muted-foreground/50"
                        />
                        <p class="mt-3 text-[11px] text-muted-foreground">
                            Delivery records will appear here.
                        </p>
                    </div>
                </section>

                <div
                    class="flex items-center gap-3 rounded-2xl border border-emerald-500/20 bg-emerald-500/7 p-4 text-[10px] leading-5 text-emerald-800 dark:text-emerald-300"
                >
                    <KeyRound class="size-5 shrink-0" />
                    <p>
                        Provider secrets are encrypted in the database and are
                        never returned to this screen after saving.
                    </p>
                </div>
            </div>
        </form>
    </div>
</template>
