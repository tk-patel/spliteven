<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        amount: number;
        showSign?: boolean;
    }>(),
    {
        showSign: false,
    },
);

const formatted = computed(() =>
    Math.abs(props.amount).toLocaleString(undefined, {
        style: 'currency',
        currency: 'CAD',
    }),
);

const label = computed(() => {
    if (props.amount > 0) {
        return props.showSign ? `you owe ${formatted.value}` : formatted.value;
    }

    if (props.amount < 0) {
        return props.showSign ? `owes you ${formatted.value}` : formatted.value;
    }

    return 'settled up';
});

const colorClass = computed(() => {
    if (props.amount > 0) {
        return 'text-red-600';
    }

    if (props.amount < 0) {
        return 'text-green-600';
    }

    return 'text-muted-foreground';
});
</script>

<template>
    <span :class="colorClass" class="font-medium">
        {{ label }}
    </span>
</template>
