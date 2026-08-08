<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { branding } from '@/lib/branding';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        :src="branding.logo.md"
        :alt="`${branding.name} logo`"
        :class="cn('object-contain', className)"
        v-bind="$attrs"
    />
</template>
