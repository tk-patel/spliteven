<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        icon: Component;
        title: string;
        description?: string;
    }>(),
    {
        description: '',
    },
);
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 py-12 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
            <component :is="icon" class="h-7 w-7 text-muted-foreground" />
        </div>
        <div>
            <p class="text-sm font-medium text-foreground">{{ title }}</p>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
    </div>
</template>
