<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    CreditCard,
    FileText,
    Landmark,
    LockKeyhole,
    ShieldCheck,
    Smartphone,
} from '@lucide/vue';
import PublicPaymentLayout from '@/layouts/PublicPaymentLayout.vue';

defineOptions({ layout: PublicPaymentLayout });

const props = defineProps<{
    paymentLink: {
        status: string;
        usable: boolean;
        expires_at: string | null;
        amount: number;
        currency: string;
    };
    invoice: {
        invoice_number: string;
        description: string;
        billing_month: string;
        due_date: string;
        status: string;
        total_amount: number;
        amount_paid: number;
        balance_due: number;
        tenant_first_name: string;
        unit: { code: string; number: string; estate: string };
        settlement_account: {
            owner_name: string;
            bank_name: string;
            account_last_four: string;
        };
    };
    gateway: { ready: boolean; name: string; methods: string[] };
    support: { email: string };
}>();

const formatMoney = (value: number): string =>
    `${props.paymentLink.currency} ${new Intl.NumberFormat('en-UG', { maximumFractionDigits: 0 }).format(value)}`;
</script>

<template>
    <Head :title="`Pay ${invoice.invoice_number}`" />

    <main
        class="relative min-h-screen overflow-hidden bg-[#f4f3ef] px-4 py-6 text-[#1b1a18] sm:px-6 sm:py-10 dark:bg-[#11110f] dark:text-[#f2f1ec]"
    >
        <div
            class="pointer-events-none absolute -top-32 -right-28 size-[34rem] rounded-full bg-[#f26745]/10 blur-3xl"
        />
        <div
            class="pointer-events-none absolute -bottom-40 -left-36 size-[30rem] rounded-full bg-amber-300/10 blur-3xl"
        />

        <div class="relative mx-auto max-w-5xl">
            <header class="mb-7 flex items-center justify-between gap-4">
                <Link href="/login" class="flex items-center gap-2.5">
                    <span
                        class="flex size-9 items-center justify-center rounded-xl bg-[#171715] text-xs font-bold text-white shadow-lg dark:bg-[#efefe9] dark:text-[#171715]"
                        >K&amp;R</span
                    >
                    <span>
                        <span class="block text-sm font-semibold tracking-tight"
                            >K&amp;R Estates</span
                        >
                        <span
                            class="block text-[9px] text-black/45 dark:text-white/45"
                            >Secure resident payment</span
                        >
                    </span>
                </Link>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-black/5 bg-white/70 px-3 py-1.5 text-[9px] font-bold text-black/55 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.06] dark:text-white/55"
                >
                    <LockKeyhole class="size-3 text-[#f26745]" /> Protected link
                </span>
            </header>

            <section
                v-if="!paymentLink.usable"
                class="mx-auto max-w-xl rounded-[2rem] border border-black/5 bg-white p-7 text-center shadow-[0_24px_80px_rgba(38,33,25,0.1)] sm:p-10 dark:border-white/10 dark:bg-[#1b1b18]"
            >
                <span
                    v-if="invoice.balance_due === 0"
                    class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600"
                    ><CheckCircle2 class="size-6"
                /></span>
                <span
                    v-else
                    class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600"
                    ><AlertTriangle class="size-6"
                /></span>
                <h1 class="mt-5 text-2xl font-semibold tracking-[-0.04em]">
                    {{
                        invoice.balance_due === 0
                            ? 'This invoice is already paid'
                            : 'This payment link is no longer active'
                    }}
                </h1>
                <p
                    class="mt-3 text-sm leading-6 text-black/50 dark:text-white/50"
                >
                    No payment can be made from this link. Sign in to your
                    resident portal for the latest balance or ask management for
                    a new secure link.
                </p>
                <Link
                    href="/login"
                    class="mt-7 inline-flex h-11 items-center gap-2 rounded-full bg-[#171715] px-5 text-xs font-semibold text-white dark:bg-[#efefe9] dark:text-[#171715]"
                >
                    Open resident portal <ArrowRight class="size-3.5" />
                </Link>
            </section>

            <section v-else class="grid gap-5 lg:grid-cols-[1fr_0.72fr]">
                <article
                    class="rounded-[2rem] border border-black/5 bg-white p-6 shadow-[0_24px_80px_rgba(38,33,25,0.1)] sm:p-8 dark:border-white/10 dark:bg-[#1b1b18]"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div>
                            <p
                                class="text-[9px] font-bold tracking-[0.14em] text-[#f26745] uppercase"
                            >
                                Rent payment
                            </p>
                            <h1
                                class="mt-2 text-2xl font-semibold tracking-[-0.045em] sm:text-3xl"
                            >
                                Hello, {{ invoice.tenant_first_name }}.
                            </h1>
                            <p
                                class="mt-2 text-xs leading-6 text-black/50 dark:text-white/50"
                            >
                                Review the protected invoice details before
                                choosing a payment method.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-sky-500/10 px-2.5 py-1.5 text-[9px] font-bold text-sky-600 dark:text-sky-400"
                            >{{ invoice.invoice_number }}</span
                        >
                    </div>

                    <div
                        class="mt-7 rounded-3xl bg-[#171715] p-6 text-white dark:bg-[#efefe9] dark:text-[#171715]"
                    >
                        <p
                            class="text-[9px] font-bold tracking-[0.12em] text-white/45 uppercase dark:text-black/45"
                        >
                            Amount to pay
                        </p>
                        <p
                            class="mt-2 text-3xl font-semibold tracking-[-0.05em] text-[#ff8060]"
                        >
                            {{ formatMoney(paymentLink.amount) }}
                        </p>
                        <div
                            class="mt-5 grid grid-cols-2 gap-4 border-t border-white/10 pt-5 dark:border-black/10"
                        >
                            <div>
                                <p
                                    class="text-[8px] font-bold tracking-wide text-white/40 uppercase dark:text-black/40"
                                >
                                    Due date
                                </p>
                                <p class="mt-1 text-xs font-semibold">
                                    {{ invoice.due_date }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[8px] font-bold tracking-wide text-white/40 uppercase dark:text-black/40"
                                >
                                    Apartment
                                </p>
                                <p class="mt-1 text-xs font-semibold">
                                    {{ invoice.unit.estate }} ·
                                    {{ invoice.unit.code }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <button
                            v-for="method in [
                                {
                                    title: 'Mobile money',
                                    note: 'MTN or Airtel',
                                    icon: Smartphone,
                                },
                                {
                                    title: 'Bank payment',
                                    note: 'Centenary',
                                    icon: Landmark,
                                },
                                {
                                    title: 'Visa / Mastercard',
                                    note: 'Secure card',
                                    icon: CreditCard,
                                },
                            ]"
                            :key="method.title"
                            type="button"
                            :disabled="!gateway.ready"
                            class="rounded-2xl border border-black/5 bg-[#f8f7f3] p-4 text-left transition-all hover:-translate-y-0.5 hover:border-[#f26745]/30 disabled:cursor-not-allowed disabled:opacity-55 dark:border-white/10 dark:bg-white/[0.035]"
                        >
                            <component
                                :is="method.icon"
                                class="size-4 text-[#f26745]"
                            />
                            <span
                                class="mt-3 block text-[11px] font-semibold"
                                >{{ method.title }}</span
                            >
                            <span
                                class="mt-1 block text-[9px] text-black/45 dark:text-white/45"
                                >{{ method.note }}</span
                            >
                        </button>
                    </div>

                    <div
                        v-if="!gateway.ready"
                        class="mt-5 flex gap-3 rounded-2xl border border-amber-500/15 bg-amber-500/[0.07] p-4"
                    >
                        <AlertTriangle
                            class="mt-0.5 size-4 shrink-0 text-amber-600"
                        />
                        <div>
                            <p class="text-[11px] font-semibold">
                                Online checkout is awaiting gateway activation
                            </p>
                            <p
                                class="mt-1 text-[10px] leading-5 text-black/50 dark:text-white/50"
                            >
                                The invoice and payment link are valid.
                                Management can connect the chosen Ugandan
                                payment provider without changing this link.
                            </p>
                        </div>
                    </div>
                </article>

                <aside class="space-y-5">
                    <article
                        class="rounded-[2rem] border border-black/5 bg-white p-6 shadow-[0_18px_60px_rgba(38,33,25,0.07)] dark:border-white/10 dark:bg-[#1b1b18]"
                    >
                        <div class="flex items-center gap-2">
                            <FileText class="size-4 text-[#f26745]" />
                            <h2 class="text-xs font-semibold">
                                Invoice summary
                            </h2>
                        </div>
                        <div class="mt-5 space-y-4">
                            <div class="flex justify-between gap-3 text-[10px]">
                                <span class="text-black/45 dark:text-white/45"
                                    >Billing month</span
                                ><strong>{{ invoice.billing_month }}</strong>
                            </div>
                            <div class="flex justify-between gap-3 text-[10px]">
                                <span class="text-black/45 dark:text-white/45"
                                    >Invoice total</span
                                ><strong>{{
                                    formatMoney(invoice.total_amount)
                                }}</strong>
                            </div>
                            <div class="flex justify-between gap-3 text-[10px]">
                                <span class="text-black/45 dark:text-white/45"
                                    >Already received</span
                                ><strong>{{
                                    formatMoney(invoice.amount_paid)
                                }}</strong>
                            </div>
                            <div
                                class="border-t border-black/5 pt-4 dark:border-white/10"
                            >
                                <div
                                    class="flex justify-between gap-3 text-[10px]"
                                >
                                    <span
                                        class="text-black/45 dark:text-white/45"
                                        >Destination</span
                                    ><strong class="text-right">{{
                                        invoice.settlement_account.owner_name
                                    }}</strong>
                                </div>
                                <div
                                    class="mt-2 flex justify-between gap-3 text-[10px]"
                                >
                                    <span
                                        class="text-black/45 dark:text-white/45"
                                        >Bank account</span
                                    ><strong
                                        >{{
                                            invoice.settlement_account.bank_name
                                        }}
                                        · ••{{
                                            invoice.settlement_account
                                                .account_last_four
                                        }}</strong
                                    >
                                </div>
                            </div>
                        </div>
                    </article>

                    <article
                        class="rounded-[2rem] bg-[#f26745] p-6 text-white shadow-[0_18px_50px_rgba(242,103,69,0.22)]"
                    >
                        <ShieldCheck class="size-5" />
                        <h2 class="mt-4 text-sm font-semibold">
                            Safe by design
                        </h2>
                        <p class="mt-2 text-[10px] leading-5 text-white/75">
                            This link fixes the invoice, amount, and destination
                            account. K&amp;R Estates never asks you to change
                            these details during checkout.
                        </p>
                        <p
                            v-if="paymentLink.expires_at"
                            class="mt-4 flex items-center gap-1.5 text-[9px] font-semibold text-white/70"
                        >
                            <CalendarDays class="size-3" /> Link valid until
                            {{ paymentLink.expires_at }}
                        </p>
                    </article>
                </aside>
            </section>

            <footer
                class="mt-7 flex flex-col justify-between gap-2 text-center text-[9px] text-black/40 sm:flex-row sm:text-left dark:text-white/35"
            >
                <span>© K&amp;R Estates · Secure resident collections</span>
                <span>Need help? {{ support.email }}</span>
            </footer>
        </div>
    </main>
</template>
