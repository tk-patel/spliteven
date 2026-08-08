<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Activity, Layers, Plus, Users } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { index as activityIndex } from '@/routes/activity';
import { index as circleIndex } from '@/routes/circle';
import { create as createExpense } from '@/routes/expenses';

const { isCurrentUrl } = useCurrentUrl();

const items = [
    { title: 'Home', href: dashboard(), icon: Activity, name: 'Home' },
    { title: 'Circle', href: circleIndex(), icon: Users, name: 'Circle' },
    { title: 'Groups', href: '/groups', icon: Layers, name: 'Groups' },
    { title: 'Activity', href: activityIndex(), icon: Activity, name: 'Activity' },
];
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-50 border-t bg-background pb-[env(safe-area-inset-bottom)] md:hidden">
        <div class="mx-auto flex h-16 max-w-lg items-center justify-around px-2">
            <Link
                v-for="item in items"
                :key="item.title"
                :href="item.href"
                class="flex min-h-11 min-w-11 flex-col items-center justify-center gap-0.5 rounded-md px-3 text-[11px] font-medium"
                :class="isCurrentUrl(item.href) ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
            >
                <component :is="item.icon" class="h-5 w-5" />
                {{ item.name }}
            </Link>

            <!-- Prominent Add button -->
            <Link
                :href="createExpense()"
                class="-mt-6 flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg"
                aria-label="Add expense"
            >
                <Plus class="h-7 w-7" />
                <span class="text-[9px] font-semibold">Add</span>
            </Link>
        </div>
    </nav>
</template>
