<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, Plus, Scale, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import BalanceCard from '@/components/BalanceCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create } from '@/routes/expenses';

type BalanceUser = {
    id: number;
    name: string;
    username: string;
};

type Balance = {
    user: BalanceUser | null;
    amount: number;
};

const props = defineProps<{
    balances: Balance[];
    totalOwed: number;
    totalOwing: number;
    netBalance: number;
}>();

const formatMoney = (n: number) =>
    n.toLocaleString(undefined, { style: 'currency', currency: 'CAD' });

const netBalanceLabel = computed(() => {
    if (props.netBalance > 0) {
        return 'You are owed overall';
    }

    if (props.netBalance < 0) {
        return 'You owe overall';
    }

    return 'All settled up';
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Dashboard</h1>
                <p class="text-sm text-muted-foreground">Your balances</p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="mr-1 h-4 w-4" />
                    Add expense
                </Link>
            </Button>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card class="border-red-200/70 bg-red-50/40 dark:border-red-900/40 dark:bg-red-950/20">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="text-base font-medium text-red-700 dark:text-red-400">
                                You owe
                            </CardTitle>
                            <CardDescription>Total you need to pay</CardDescription>
                        </div>
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-400"
                        >
                            <ArrowDownLeft class="size-4" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold tracking-tight text-red-600 dark:text-red-400">
                        {{ formatMoney(props.totalOwed) }}
                    </p>
                </CardContent>
            </Card>

            <Card class="border-green-200/70 bg-green-50/40 dark:border-green-900/40 dark:bg-green-950/20">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="text-base font-medium text-green-700 dark:text-green-400">
                                You are owed
                            </CardTitle>
                            <CardDescription>Total others owe you</CardDescription>
                        </div>
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-950 dark:text-green-400"
                        >
                            <ArrowUpRight class="size-4" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold tracking-tight text-green-600 dark:text-green-400">
                        {{ formatMoney(props.totalOwing) }}
                    </p>
                </CardContent>
            </Card>

            <Card
                class="sm:col-span-2 lg:col-span-1"
                :class="
                    props.netBalance > 0
                        ? 'border-green-200/70 bg-green-50/30 dark:border-green-900/40 dark:bg-green-950/15'
                        : props.netBalance < 0
                          ? 'border-red-200/70 bg-red-50/30 dark:border-red-900/40 dark:bg-red-950/15'
                          : 'border-border bg-muted/30'
                "
            >
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="text-base font-medium">Net balance</CardTitle>
                            <CardDescription>{{ netBalanceLabel }}</CardDescription>
                        </div>
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
                            :class="
                                props.netBalance > 0
                                    ? 'bg-green-100 text-green-600 dark:bg-green-950 dark:text-green-400'
                                    : props.netBalance < 0
                                      ? 'bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-400'
                                      : ''
                            "
                        >
                            <Wallet class="size-4" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-2xl font-bold tracking-tight"
                        :class="
                            props.netBalance > 0
                                ? 'text-green-600 dark:text-green-400'
                                : props.netBalance < 0
                                  ? 'text-red-600 dark:text-red-400'
                                  : 'text-muted-foreground'
                        "
                    >
                        {{ formatMoney(Math.abs(props.netBalance)) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Balances list -->
        <Card>
            <CardHeader class="border-b">
                <CardTitle>Balances with friends</CardTitle>
                <CardDescription>
                    Tap a balance to record a settlement
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3 pt-6">
                <EmptyState
                    v-if="balances.length === 0"
                    :icon="Scale"
                    title="All settled up"
                    description="Add an expense to start tracking who owes whom"
                />

                <BalanceCard
                    v-for="balance in balances"
                    :key="balance.user?.id"
                    :user="balance.user"
                    :amount="balance.amount"
                />
            </CardContent>
        </Card>
    </div>
</template>
