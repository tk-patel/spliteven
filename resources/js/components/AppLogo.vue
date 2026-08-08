<script setup lang="ts">
import { branding } from '@/lib/branding';
</script>

<template>
    <img
        :src="branding.logo.md"
        :alt="`${branding.name} logo`"
        class="size-8 shrink-0 object-contain"
    />
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            branding.name
        }}</span>
    </div>
</template>
