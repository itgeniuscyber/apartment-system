<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Building2,
    CheckCircle2,
    ShieldCheck,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
    wide?: boolean;
}>();
</script>

<template>
    <main
        class="min-h-svh bg-background lg:grid lg:grid-cols-[minmax(420px,0.94fr)_minmax(520px,1.06fr)]"
    >
        <section
            class="relative hidden min-h-svh overflow-hidden bg-[#171715] p-10 text-white lg:flex lg:flex-col xl:p-14"
        >
            <div class="soft-grid absolute inset-0 opacity-[0.14]" />
            <div
                class="absolute -top-32 -right-28 size-[30rem] rounded-full bg-accent/20 blur-[100px]"
            />
            <div
                class="absolute -bottom-40 -left-36 size-[28rem] rounded-full bg-[#7f503e]/30 blur-[110px]"
            />

            <Link
                :href="home()"
                class="relative z-10 flex w-fit items-center gap-3"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-2xl bg-accent text-white shadow-[0_12px_30px_rgba(242,103,69,0.28)]"
                >
                    <AppLogoIcon class="size-7" />
                </span>
                <span>
                    <span class="block text-base font-bold tracking-[-0.02em]"
                        >K&amp;R Estates</span
                    >
                    <span
                        class="block text-[10px] font-semibold tracking-[0.18em] text-white/45 uppercase"
                        >Property suite</span
                    >
                </span>
            </Link>

            <div class="relative z-10 my-auto max-w-xl py-14">
                <div
                    class="mb-7 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.06] px-3.5 py-2 text-xs font-medium text-white/70 backdrop-blur"
                >
                    <span class="pulse-soft size-1.5 rounded-full bg-accent" />
                    One view across all 66 apartments
                </div>
                <h2
                    class="max-w-lg text-[clamp(2.6rem,4vw,4.5rem)] leading-[0.98] font-semibold tracking-[-0.065em]"
                >
                    Property management,
                    <span class="text-accent">beautifully clear.</span>
                </h2>
                <p class="mt-7 max-w-lg text-base leading-7 text-white/55">
                    Track rent, residents, maintenance, and every estate from
                    one secure workspace built for K&amp;R Estates.
                </p>

                <div class="mt-10 grid max-w-lg grid-cols-2 gap-3">
                    <div
                        class="rounded-3xl border border-white/10 bg-white/[0.055] p-5 backdrop-blur-sm"
                    >
                        <Building2 class="mb-5 size-5 text-accent" />
                        <p
                            class="money text-3xl font-semibold tracking-[-0.05em]"
                        >
                            66
                        </p>
                        <p class="mt-1 text-xs text-white/45">
                            Units across four estates
                        </p>
                    </div>
                    <div
                        class="rounded-3xl border border-white/10 bg-white/[0.055] p-5 backdrop-blur-sm"
                    >
                        <ShieldCheck class="mb-5 size-5 text-accent" />
                        <p class="text-lg font-semibold tracking-[-0.03em]">
                            Secure by design
                        </p>
                        <p class="mt-2 text-xs leading-5 text-white/45">
                            Protected access and complete audit history
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="relative z-10 flex items-center justify-between border-t border-white/10 pt-6 text-xs text-white/35"
            >
                <span>© 2026 K&amp;R Estates</span>
                <span class="flex items-center gap-1.5"
                    >Kampala, Uganda <ArrowUpRight class="size-3.5"
                /></span>
            </div>
        </section>

        <section
            class="relative flex min-h-svh items-center justify-center px-5 py-8 sm:px-8 lg:px-12 xl:px-20"
        >
            <div class="absolute top-5 right-5 sm:top-8 sm:right-8">
                <ThemeToggle />
            </div>

            <div
                class="dashboard-enter w-full"
                :class="wide ? 'max-w-[720px]' : 'max-w-[460px]'"
            >
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <span
                        class="flex size-11 items-center justify-center rounded-2xl bg-accent text-white shadow-[0_10px_24px_rgba(242,103,69,0.22)]"
                    >
                        <AppLogoIcon class="size-7" />
                    </span>
                    <div>
                        <p class="font-bold tracking-[-0.02em]">
                            K&amp;R Estates
                        </p>
                        <p
                            class="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            Property suite
                        </p>
                    </div>
                </div>

                <div class="mb-8">
                    <div
                        class="mb-4 flex size-11 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    >
                        <CheckCircle2 class="size-5" />
                    </div>
                    <h1
                        v-if="title"
                        class="text-3xl font-semibold tracking-[-0.045em] sm:text-[2.15rem]"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-3 max-w-md text-sm leading-6 text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>

                <slot />

                <p
                    class="mt-8 text-center text-xs leading-5 text-muted-foreground/70"
                >
                    By continuing, you agree to K&amp;R Estates’ access and
                    privacy policies.
                </p>
            </div>
        </section>
    </main>
</template>
