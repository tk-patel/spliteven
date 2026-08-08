<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Plus, ReceiptText, UserMinus } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import UserAvatar from '@/components/UserAvatar.vue';
import { create as createExpense } from '@/routes/expenses';

type User = {
    id: number;
    name: string;
    username: string;
};

type ExpenseParticipant = {
    user: User;
    owed_amount: number;
};

type Expense = {
    id: number;
    description: string;
    amount: number;
    paid_by: number;
    payer: User;
    expense_date: string;
    participants: ExpenseParticipant[];
};

type Balance = {
    user: User | null;
    amount: number;
};

type Debt = {
    from: User | null;
    to: User | null;
    amount: number;
};

type Group = {
    id: number;
    name: string;
    created_by: number;
    members: User[];
};

const props = defineProps<{
    group: Group;
    expenses: { data: Expense[] };
    balances: Balance[];
    simplifiedDebts: Debt[];
    addableFriends: User[];
}>();

const addMemberOpen = ref(false);
const newMemberId = ref<number | null>(null);

const addMember = () => {
    if (!newMemberId.value) {
        return;
    }

    router.post(
        `/groups/${props.group.id}/members`,
        { user_id: newMemberId.value },
        {
            preserveScroll: true,
            onFinish: () => {
                addMemberOpen.value = false;
                newMemberId.value = null;
            },
        },
    );
};

const removeMember = (userId: number) => {
    if (confirm('Remove this member from the group?')) {
        router.delete(`/groups/${props.group.id}/members/${userId}`, { preserveScroll: true });
    }
};

const formatMoney = (amount: number) =>
    amount.toLocaleString(undefined, { style: 'currency', currency: 'CAD' });

const formatDate = (date: string) => date.slice(0, 10);

const isCreator = (userId: number) => userId === props.group.created_by;
</script>

<template>
    <div class="space-y-6">
        <div>
            <Link href="/groups" class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="h-4 w-4" />
                Groups
            </Link>
            <h1 class="text-2xl font-bold">{{ group.name }}</h1>
            <p class="text-sm text-muted-foreground">{{ group.members.length }} members</p>
        </div>

        <!-- Action buttons -->
        <div class="flex gap-2">
            <Button as-child class="flex-1">
                <Link :href="createExpense({ query: { group_id: String(group.id) } })">
                    <Plus class="mr-1 h-4 w-4" />
                    Add expense
                </Link>
            </Button>
        </div>

        <!-- Balances -->
        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">Balances</h2>
            <Card v-for="balance in balances" :key="balance.user?.id">
                <CardContent class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="balance.user?.name ?? '?'" size="sm" />
                        <div>
                            <p class="text-sm font-medium">{{ balance.user?.name }}</p>
                            <p class="text-xs text-muted-foreground">@{{ balance.user?.username }}</p>
                        </div>
                    </div>
                    <p
                        class="text-sm font-semibold"
                        :class="balance.amount > 0 ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ balance.amount > 0 ? 'is owed' : 'owes' }}
                        {{ formatMoney(Math.abs(balance.amount)) }}
                    </p>
                </CardContent>
            </Card>
            <p v-if="balances.length === 0" class="text-sm text-muted-foreground">
                No balances yet.
            </p>
        </section>

        <!-- Simplified debts -->
        <section v-if="simplifiedDebts.length > 0" class="space-y-3">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">Suggested payments</h2>
            <Card v-for="(debt, index) in simplifiedDebts" :key="index">
                <CardContent class="flex items-center gap-3 p-4">
                    <UserAvatar :name="debt.from?.name ?? '?'" size="sm" />
                    <ArrowRight class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <UserAvatar :name="debt.to?.name ?? '?'" size="sm" />
                    <p class="ml-auto text-sm font-semibold">{{ formatMoney(debt.amount) }}</p>
                </CardContent>
            </Card>
        </section>

        <!-- Members -->
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-muted-foreground uppercase">Members</h2>
                <Button size="sm" variant="outline" @click="addMemberOpen = true">Add member</Button>
            </div>
            <Card v-for="member in group.members" :key="member.id">
                <CardContent class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="member.name" size="sm" />
                        <div>
                            <p class="text-sm font-medium">
                                {{ member.name }}
                                <span v-if="isCreator(member.id)" class="ml-1 text-xs text-muted-foreground">(creator)</span>
                            </p>
                            <p class="text-xs text-muted-foreground">@{{ member.username }}</p>
                        </div>
                    </div>
                    <Button
                        v-if="isCreator(group.created_by) && !isCreator(member.id)"
                        size="sm"
                        variant="ghost"
                        class="text-muted-foreground hover:text-destructive"
                        @click="removeMember(member.id)"
                    >
                        <UserMinus class="h-3 w-3" />
                    </Button>
                </CardContent>
            </Card>
        </section>

        <!-- Expenses -->
        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">Expenses</h2>
            <EmptyState
                v-if="expenses.data.length === 0"
                :icon="ReceiptText"
                title="No expenses yet"
                description="Add your first expense to get started"
            />
            <Link
                v-for="expense in expenses.data"
                :key="expense.id"
                :href="`/expenses/${expense.id}`"
                class="block"
            >
                <Card>
                    <CardContent class="flex items-center justify-between p-4">
                        <div>
                            <p class="text-sm font-medium">{{ expense.description }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ expense.payer?.name }} paid · {{ formatDate(expense.expense_date) }}
                            </p>
                        </div>
                        <p class="text-sm font-semibold">{{ formatMoney(Number(expense.amount)) }}</p>
                    </CardContent>
                </Card>
            </Link>
        </section>

        <!-- Add member dialog -->
        <Dialog :open="addMemberOpen" @update:open="addMemberOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add member</DialogTitle>
                    <DialogDescription>
                        Select a friend from your Circle to add to this group.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="member">Friend</Label>
                    <select
                        id="member"
                        v-model="newMemberId"
                        class="border-input bg-background h-9 w-full rounded-md border px-3 py-1 text-sm"
                    >
                        <option :value="null" disabled>Select a friend…</option>
                        <option v-for="friend in addableFriends" :key="friend.id" :value="friend.id">
                            {{ friend.name }} (@{{ friend.username }})
                        </option>
                    </select>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="addMemberOpen = false">Cancel</Button>
                    <Button :disabled="!newMemberId" @click="addMember">Add</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
