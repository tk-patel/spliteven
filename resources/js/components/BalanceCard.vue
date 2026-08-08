<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import AmountDisplay from '@/components/AmountDisplay.vue';
import { Card, CardContent } from '@/components/ui/card';
import UserAvatar from '@/components/UserAvatar.vue';

type User = {
    id: number;
    name: string;
    username: string;
};

defineProps<{
    user: User | null;
    amount: number;
}>();
</script>

<template>
    <Card>
        <Link
            :href="`/settlements/create?payee_id=${user?.id}&amount=${Math.abs(amount)}`"
            class="block"
        >
            <CardContent class="flex items-center gap-3 p-4">
                <UserAvatar :name="user?.name ?? '?'" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ user?.name }}</p>
                    <p class="text-xs text-muted-foreground">@{{ user?.username }}</p>
                </div>
                <div class="flex items-center gap-1">
                    <AmountDisplay :amount="amount" show-sign />
                    <ChevronRight class="h-4 w-4 text-muted-foreground" />
                </div>
            </CardContent>
        </Link>
    </Card>
</template>
