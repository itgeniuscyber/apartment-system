<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <div
        class="flex aspect-square size-10 items-center justify-center rounded-xl bg-accent text-white shadow-[0_8px_20px_rgba(242,103,69,0.25)]"
    >
        <AppLogoIcon class="size-6 text-white" />
    </div>
    <div class="ml-1.5 grid flex-1 text-left">
        <span
            class="truncate text-[15px] leading-tight font-bold tracking-[-0.02em]"
            >{{ name }}</span
        >
        <span
            class="truncate text-[10px] font-medium tracking-[0.14em] text-sidebar-foreground/55 uppercase"
            >Property suite</span
        >
    </div>
</template>
