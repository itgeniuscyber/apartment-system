<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Sparkles } from '@lucide/vue';

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
                    ><Sparkles class="size-6"
                /></span>
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
                    Your tenant identity and access permissions are already
                    separated from the management workspace. This feature will
                    connect to that secure foundation.
                </p>
                <Link
                    href="/dashboard"
                    class="mt-8 inline-flex h-11 items-center gap-2 rounded-full bg-foreground px-5 text-xs font-semibold text-background transition-all hover:-translate-y-0.5 hover:shadow-lg"
                >
                    <ArrowLeft class="size-4" /> Return to my home
                </Link>
            </div>
        </section>
    </div>
</template>
