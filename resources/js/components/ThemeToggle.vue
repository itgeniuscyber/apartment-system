<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <button
        type="button"
        class="group inline-flex size-10 items-center justify-center rounded-full border border-border/70 bg-card text-muted-foreground shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:text-foreground hover:shadow-md focus-visible:ring-3 focus-visible:ring-ring/30"
        :aria-label="
            resolvedAppearance === 'dark' ? 'Use light theme' : 'Use dark theme'
        "
        @click="toggleTheme"
    >
        <Sun
            v-if="resolvedAppearance === 'dark'"
            class="size-4.5 transition-transform group-hover:rotate-12"
        />
        <Moon
            v-else
            class="size-4.5 transition-transform group-hover:-rotate-12"
        />
    </button>
</template>
