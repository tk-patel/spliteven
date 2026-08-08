<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Activity as ActivityIcon, HandCoins, ReceiptText } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Card, CardContent } from '@/components/ui/card';

type User = {
    id: number;
    name: string;
    username: string;
};

type ExpenseData = {
    id: number;
    description: string;
    amount: number;
    paid_by: number;
    payer: User | null;
    group: { id: number; name: string } | null;
    expense_date: string;
};

type SettlementData = {
    id: number;
    payer: User | null;
    payee: User | null;
    group: { id: number; name: string } | null;
    amount: number;
    settled_at: string;
};

type ActivityItem = {
    type: 'expense' | 'settlement';
    id: number;
    data: ExpenseData | SettlementData;
    date: string;
    created_at: string;
};

defineProps<{
    activities: ActivityItem[];
}>();

const formatMoney = (n: number | string) =>
    Number(n).toLocaleString(undefined, { style: 'currency', currency: 'CAD' });
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Activity</h1>
            <p class="text-sm text-muted-foreground">Recent expenses and payments</p>
        </div>

        <EmptyState
            v-if="activities.length === 0"
            :icon="ActivityIcon"
            title="No activity yet"
            description="Expenses and settlements will show up here"
        />

        <div class="grid gap-3">
            <template v-for="activity in activities" :key="`${activity.type}-${activity.id}`">
                <!-- Expense item -->
                <Link v-if="activity.type === 'expense'" :href="`/expenses/${activity.data.id}`" class="block">
                    <Card>
                        <CardContent class="flex items-center gap-3 p-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                <ReceiptText class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">
                                    {{ (activity.data as ExpenseData).description }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ (activity.data as ExpenseData).payer?.name }} paid · {{ activity.date }}
                                </p>
                                <p v-if="(activity.data as ExpenseData).group" class="mt-0.5">
                                    <span class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                        {{ (activity.data as ExpenseData).group?.name }}
                                    </span>
                                </p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold">
                                {{ formatMoney((activity.data as ExpenseData).amount) }}
                            </p>
                        </CardContent>
                    </Card>
                </Link>

                <!-- Settlement item -->
                <Card v-else>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                            <HandCoins class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">
                                {{ (activity.data as SettlementData).payer?.name }}
                                <span class="text-muted-foreground">paid</span>
                                {{ (activity.data as SettlementData).payee?.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">{{ activity.date }}</p>
                            <p v-if="(activity.data as SettlementData).group" class="mt-0.5">
                                <span class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                    {{ (activity.data as SettlementData).group?.name }}
                                </span>
                            </p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-green-600">
                            {{ formatMoney((activity.data as SettlementData).amount) }}
                        </p>
                    </CardContent>
                </Card>
            </template>
        </div>
    </div>
</template>
