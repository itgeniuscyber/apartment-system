<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        fill="none"
    >
        <path
            d="M7 39V18.5L24 7l17 11.5V39"
            stroke="currentColor"
            stroke-width="4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <path
            d="M17 39V25h14v14M13 39h22"
            stroke="currentColor"
            stroke-width="4"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
