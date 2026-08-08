<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        size: 'md',
    },
);

const colors = [
    'bg-red-100 text-red-700',
    'bg-blue-100 text-blue-700',
    'bg-green-100 text-green-700',
    'bg-yellow-100 text-yellow-700',
    'bg-purple-100 text-purple-700',
    'bg-pink-100 text-pink-700',
    'bg-indigo-100 text-indigo-700',
    'bg-orange-100 text-orange-700',
];

const sizeClasses = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-10 w-10 text-sm',
    lg: 'h-12 w-12 text-base',
};

const initials = computed(() => {
    const parts = props.name.trim().split(/\s+/);

    if (parts.length === 1) {
        return parts[0].charAt(0).toUpperCase();
    }

    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});

const colorClass = computed(() => {
    const code = props.name.charCodeAt(0) || 0;

    return colors[code % colors.length];
});
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center rounded-full font-semibold"
        :class="[sizeClasses[size], colorClass]"
    >
        {{ initials }}
    </div>
</template>
