<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { HandCoins, Plus } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import UserAvatar from '@/components/UserAvatar.vue';
import { create } from '@/routes/settlements';

type User = {
    id: number;
    name: string;
    username: string;
};

type Settlement = {
    id: number;
    payer: User;
    payee: User;
    group: { id: number; name: string } | null;
    amount: number;
    note: string | null;
    settled_at: string;
};

defineProps<{
    settlements: { data: Settlement[] };
}>();

const formatMoney = (n: number | string) =>
    Number(n).toLocaleString(undefined, { style: 'currency', currency: 'CAD' });
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Settlements</h1>
                <p class="text-sm text-muted-foreground">Payment history</p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="mr-1 h-4 w-4" />
                    Settle up
                </Link>
            </Button>
        </div>

        <EmptyState
            v-if="settlements.data.length === 0"
            :icon="HandCoins"
            title="No settlements yet"
            description="Record payments when you pay someone back"
        />

        <div class="grid gap-3">
            <Card v-for="settlement in settlements.data" :key="settlement.id">
                <CardContent class="flex items-center gap-3 p-4">
                    <UserAvatar :name="settlement.payer?.name ?? '?'" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">
                            {{ settlement.payer?.name }}
                            <span class="text-muted-foreground">paid</span>
                            {{ settlement.payee?.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ settlement.settled_at }}
                            <span v-if="settlement.group" class="ml-1 rounded bg-muted px-1.5 py-0.5">
                                {{ settlement.group.name }}
                            </span>
                        </p>
                        <p v-if="settlement.note" class="mt-0.5 text-xs text-muted-foreground">
                            {{ settlement.note }}
                        </p>
                    </div>
                    <p class="shrink-0 text-sm font-semibold text-green-600">
                        {{ formatMoney(settlement.amount) }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
