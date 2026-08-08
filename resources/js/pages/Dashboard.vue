<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, Scale } from '@lucide/vue';
import BalanceCard from '@/components/BalanceCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

        <!-- Summary card -->
        <Card>
            <CardContent class="p-6">
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase">You owe</p>
                        <p class="mt-1 text-lg font-bold text-red-600">
                            {{ formatMoney(props.totalOwed) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground uppercase">You are owed</p>
                        <p class="mt-1 text-lg font-bold text-green-600">
                            {{ formatMoney(props.totalOwing) }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 border-t pt-4 text-center">
                    <p class="text-xs font-medium text-muted-foreground uppercase">Net balance</p>
                    <p
                        class="mt-1 text-xl font-bold"
                        :class="
                            props.netBalance > 0
                                ? 'text-green-600'
                                : props.netBalance < 0
                                  ? 'text-red-600'
                                  : 'text-muted-foreground'
                        "
                    >
                        {{ formatMoney(Math.abs(props.netBalance)) }}
                    </p>
                    <p
                        v-if="props.netBalance !== 0"
                        class="text-xs text-muted-foreground"
                    >
                        {{ props.netBalance > 0 ? 'you are owed overall' : 'you owe overall' }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Balances list -->
        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">
                Balances with friends
            </h2>

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
        </section>
    </div>
</template>
