<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Construction, Sparkles } from '@lucide/vue';
import { dashboard } from '@/routes';

defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <Head :title="title" />
    <div
        class="mx-auto flex min-h-[calc(100svh-76px)] w-full max-w-5xl items-center px-5 py-10 sm:px-8"
    >
        <section
            class="surface-card soft-grid dashboard-enter relative w-full overflow-hidden p-8 sm:p-12"
        >
            <div
                class="absolute -top-16 -right-10 size-56 rounded-full bg-accent/10 blur-3xl"
            />
            <div class="relative max-w-2xl">
                <span
                    class="mb-6 flex size-12 items-center justify-center rounded-2xl bg-accent/10 text-accent"
                    ><Construction class="size-6"
                /></span>
                <div
                    class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1.5 text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                >
                    <Sparkles class="size-3" /> Module foundation ready
                </div>
                <h1
                    class="text-3xl font-semibold tracking-[-0.05em] sm:text-4xl"
                >
                    {{ title }}
                </h1>
                <p
                    class="mt-4 max-w-xl text-sm leading-7 text-muted-foreground"
                >
                    {{ description }}
                </p>
                <p class="mt-4 text-sm leading-7 text-muted-foreground">
                    The shared navigation, theming, security, and responsive
                    page structure are already active. We will develop this
                    module against the confirmed apartment workflows in the next
                    implementation stage.
                </p>
                <Link
                    :href="dashboard()"
                    class="mt-8 inline-flex h-11 items-center gap-2 rounded-full bg-foreground px-5 text-xs font-semibold text-background transition-all hover:-translate-y-0.5 hover:shadow-lg"
                    ><ArrowLeft class="size-4" /> Return to overview</Link
                >
            </div>
        </section>
    </div>
</template>
