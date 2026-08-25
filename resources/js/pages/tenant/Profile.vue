<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    CheckCircle2,
    ContactRound,
    Download,
    FileKey2,
    LockKeyhole,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Resident portal', href: '/dashboard' },
            { title: 'My profile', href: '/tenant/profile' },
        ],
    },
});

type TenantProfile = {
    full_name: string;
    email: string | null;
    phone: string | null;
    alternative_phone: string | null;
    preferred_channel: string;
    status: string;
    portal_activated_at: string | null;
    identity: {
        captured: boolean;
        type: string | null;
        last_four: string | null;
        document_uploaded: boolean;
        uploaded_at: string | null;
        verified: boolean;
        verified_at: string | null;
    };
    next_of_kin: {
        name: string | null;
        relationship: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
    };
    lease: {
        lease_number: string;
        estate: string;
        section: string;
        unit_code: string;
    } | null;
};

defineProps<{ tenant: TenantProfile }>();

const channelLabel = (channel: string): string =>
    channel === 'sms' ? 'SMS' : channel === 'email' ? 'Email' : 'WhatsApp';
</script>

<template>
    <Head title="My resident profile" />

    <div
        class="mx-auto w-full max-w-[1500px] px-4 py-5 sm:px-6 lg:px-8 lg:py-7"
    >
        <section
            class="dashboard-enter flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
        >
            <div>
                <div
                    class="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground"
                >
                    <span>Resident portal</span>
                    <span class="text-muted-foreground/40">/</span>
                    <span class="text-foreground">My profile</span>
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.055em] sm:text-[2.35rem]"
                >
                    Your resident profile
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Review the contact, identification, next-of-kin, and
                    apartment details held securely for your tenancy.
                </p>
            </div>
            <span
                class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-500/10 px-3.5 py-2 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
            >
                <LockKeyhole class="size-3.5" /> Portal protected
            </span>
        </section>

        <section class="mt-7 grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 80ms"
            >
                <div
                    class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-4">
                        <span
                            class="flex size-14 shrink-0 items-center justify-center rounded-[1.25rem] bg-[#171715] text-white dark:bg-[#efefe9] dark:text-[#171715]"
                        >
                            <UserRound class="size-6" />
                        </span>
                        <div>
                            <p
                                class="text-xl font-semibold tracking-[-0.035em]"
                            >
                                {{ tenant.full_name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Registered resident
                            </p>
                        </div>
                    </div>
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1.5 text-[9px] font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        <CheckCircle2 class="size-3" /> Portal active
                    </span>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-muted/45 p-4">
                        <p
                            class="flex items-center gap-2 text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            <Mail class="size-3.5 text-accent" /> Email
                        </p>
                        <p class="mt-2 text-sm font-semibold break-all">
                            {{ tenant.email || 'Not recorded' }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-muted/45 p-4">
                        <p
                            class="flex items-center gap-2 text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            <Phone class="size-3.5 text-accent" /> Phone /
                            WhatsApp
                        </p>
                        <p class="mt-2 text-sm font-semibold">
                            {{ tenant.phone || 'Not recorded' }}
                        </p>
                        <p
                            v-if="tenant.alternative_phone"
                            class="mt-1 text-[10px] text-muted-foreground"
                        >
                            Alternative: {{ tenant.alternative_phone }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-muted/45 p-4 sm:col-span-2">
                        <p
                            class="flex items-center gap-2 text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            <MessageCircle class="size-3.5 text-accent" />
                            Preferred contact
                        </p>
                        <p class="mt-2 text-sm font-semibold">
                            {{ channelLabel(tenant.preferred_channel) }}
                        </p>
                    </div>
                </div>
            </article>

            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 130ms"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400"
                        >
                            <ShieldCheck class="size-5" />
                        </span>
                        <div>
                            <h2 class="text-base font-semibold">
                                Account security
                            </h2>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Your private resident access
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-6 space-y-3">
                    <div
                        class="flex items-center justify-between gap-4 rounded-2xl border border-border/70 p-4"
                    >
                        <div>
                            <p class="text-xs font-semibold">
                                Password protection
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                Your password is never visible to management
                            </p>
                        </div>
                        <BadgeCheck class="size-5 shrink-0 text-emerald-500" />
                    </div>
                    <div
                        class="flex items-center justify-between gap-4 rounded-2xl border border-border/70 p-4"
                    >
                        <div>
                            <p class="text-xs font-semibold">
                                Portal activated
                            </p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                {{ tenant.portal_activated_at || 'Active' }}
                            </p>
                        </div>
                        <LockKeyhole class="size-5 shrink-0 text-accent" />
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-4 grid gap-4 xl:grid-cols-3">
            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 180ms"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    >
                        <FileKey2 class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold">Identification</h2>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Protected security record
                        </p>
                    </div>
                </div>

                <div v-if="tenant.identity.captured" class="mt-6 space-y-3">
                    <div class="rounded-2xl bg-muted/45 p-4">
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Document
                        </p>
                        <p class="mt-2 text-sm font-semibold">
                            {{ tenant.identity.type || 'Identity document' }}
                            <span
                                v-if="tenant.identity.last_four"
                                class="text-muted-foreground"
                            >
                                · •••• {{ tenant.identity.last_four }}</span
                            >
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-[9px] font-bold"
                            :class="
                                tenant.identity.verified
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                            "
                        >
                            <BadgeCheck class="size-3" />
                            {{
                                tenant.identity.verified
                                    ? 'Verified by management'
                                    : 'Review pending'
                            }}
                        </span>
                        <a
                            v-if="tenant.identity.document_uploaded"
                            href="/tenant/profile/identity-document"
                            class="inline-flex items-center gap-1.5 rounded-full border border-border/70 px-2.5 py-1.5 text-[9px] font-bold transition-colors hover:text-accent"
                        >
                            <Download class="size-3" /> My document
                        </a>
                    </div>
                </div>
                <div
                    v-else
                    class="mt-6 rounded-2xl bg-amber-500/[0.07] p-4 text-xs leading-5 text-amber-700 dark:text-amber-300"
                >
                    Identification has not yet been recorded. Contact management
                    to complete your security profile.
                </div>
            </article>

            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 230ms"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400"
                    >
                        <ContactRound class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold">Next of kin</h2>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Emergency contact on file
                        </p>
                    </div>
                </div>

                <div v-if="tenant.next_of_kin.name" class="mt-6 space-y-3">
                    <div>
                        <p class="text-sm font-semibold">
                            {{ tenant.next_of_kin.name }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ tenant.next_of_kin.relationship }}
                        </p>
                    </div>
                    <div
                        class="space-y-2 border-t border-border/70 pt-4 text-xs"
                    >
                        <p
                            v-if="tenant.next_of_kin.phone"
                            class="flex items-center gap-2"
                        >
                            <Phone class="size-3.5 text-accent" />
                            {{ tenant.next_of_kin.phone }}
                        </p>
                        <p
                            v-if="tenant.next_of_kin.email"
                            class="flex items-center gap-2"
                        >
                            <Mail class="size-3.5 text-accent" />
                            {{ tenant.next_of_kin.email }}
                        </p>
                        <p
                            v-if="tenant.next_of_kin.address"
                            class="flex items-start gap-2 leading-5"
                        >
                            <MapPin
                                class="mt-0.5 size-3.5 shrink-0 text-accent"
                            />
                            {{ tenant.next_of_kin.address }}
                        </p>
                    </div>
                </div>
                <div
                    v-else
                    class="mt-6 rounded-2xl bg-amber-500/[0.07] p-4 text-xs leading-5 text-amber-700 dark:text-amber-300"
                >
                    No next-of-kin record is available. Contact management to
                    update it.
                </div>
            </article>

            <article
                class="surface-card dashboard-enter p-5 sm:p-6"
                style="--delay: 280ms"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    >
                        <Building2 class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold">
                            Apartment assignment
                        </h2>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Your active lease location
                        </p>
                    </div>
                </div>

                <div v-if="tenant.lease" class="mt-6 space-y-3">
                    <div class="rounded-2xl bg-muted/45 p-4">
                        <p
                            class="text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            Estate & unit
                        </p>
                        <p class="mt-2 text-sm font-semibold">
                            {{ tenant.lease.estate }} ·
                            {{ tenant.lease.unit_code }}
                        </p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ tenant.lease.section }} section
                        </p>
                    </div>
                    <p class="text-[10px] text-muted-foreground">
                        Lease reference: {{ tenant.lease.lease_number }}
                    </p>
                </div>
                <div
                    v-else
                    class="mt-6 rounded-2xl bg-muted/45 p-4 text-xs leading-5 text-muted-foreground"
                >
                    Your resident account is ready. Management will connect an
                    apartment when your lease is confirmed.
                </div>
            </article>
        </section>
    </div>
</template>
