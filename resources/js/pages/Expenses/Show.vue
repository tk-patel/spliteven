<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import UserAvatar from '@/components/UserAvatar.vue';

type User = {
    id: number;
    name: string;
    username: string;
};

type Participant = {
    user: User;
    owed_amount: number;
    share_value?: number | null;
};

type Expense = {
    id: number;
    description: string;
    amount: number;
    split_type: 'equal' | 'shares' | 'percentage' | 'exact';
    expense_date: string;
    paid_by: number;
    payer: User;
    created_by: number;
    group?: { id: number; name: string } | null;
    participants: Participant[];
};

const props = defineProps<{
    expense: Expense;
}>();

const page = usePage();
const currentUserId = page.props.auth.user.id;

const splitTypeLabels: Record<Expense['split_type'], string> = {
    equal: 'Equally',
    shares: 'By shares',
    percentage: 'By percentage',
    exact: 'Exact amounts',
};

const deleteExpense = () => {
    if (confirm('Delete this expense?')) {
        router.delete(`/expenses/${props.expense.id}`);
    }
};

const formatMoney = (amount: number | string) =>
    Number(amount).toLocaleString(undefined, { style: 'currency', currency: 'CAD' });

const formatDate = (date: string) => date.slice(0, 10);
</script>

<template>
    <div class="space-y-6">
        <div>
            <Link href="/dashboard" class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="h-4 w-4" />
                Dashboard
            </Link>
            <div class="flex items-start justify-between gap-4">
                <h1 class="text-2xl font-bold">{{ expense.description }}</h1>
                <Button
                    v-if="expense.created_by === currentUserId"
                    size="sm"
                    variant="ghost"
                    class="text-muted-foreground hover:text-destructive"
                    @click="deleteExpense"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <Card>
            <CardContent class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="expense.payer?.name ?? '?'" />
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Paid by <span class="font-medium text-foreground">{{ expense.payer?.name }}</span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDate(expense.expense_date) }} · {{ splitTypeLabels[expense.split_type] }}
                                <span v-if="expense.group" class="ml-1 rounded bg-muted px-1.5 py-0.5">
                                    {{ expense.group.name }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <p class="text-2xl font-bold">{{ formatMoney(expense.amount) }}</p>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-3">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">Split breakdown</h2>
            <Card v-for="p in expense.participants" :key="p.user.id">
                <CardContent class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="p.user.name" size="sm" />
                        <div>
                            <p class="text-sm font-medium">
                                {{ p.user.name }}
                                <span v-if="p.user.id === currentUserId" class="text-xs text-muted-foreground">(you)</span>
                            </p>
                            <p v-if="p.share_value" class="text-xs text-muted-foreground">
                                {{ expense.split_type === 'percentage' ? `${p.share_value}%` : `${p.share_value} shares` }}
                            </p>
                        </div>
                    </div>
                    <p class="text-sm font-semibold">{{ formatMoney(p.owed_amount) }}</p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
