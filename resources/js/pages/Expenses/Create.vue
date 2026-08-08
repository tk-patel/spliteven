<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { Minus, Plus, ReceiptText } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import UserAvatar from '@/components/UserAvatar.vue';
import { store } from '@/routes/expenses';

type Friend = {
    id: number;
    name: string;
    username: string;
};

type Group = {
    id: number;
    name: string;
    members: Friend[];
};

type Participant = {
    user_id: number;
    name: string;
    username: string;
    share_value: string;
    amount: string;
};

type SplitType = 'equal' | 'shares' | 'percentage' | 'exact';

const props = defineProps<{
    friends: Friend[];
    groups: Group[];
    preselectedGroupId?: number | string | null;
    preselectedFriendId?: number | string | null;
}>();

const page = usePage();
const currentUserId = page.props.auth.user.id;
const currentUserName = page.props.auth.user.name;

const num = (v: string | null | undefined): number => {
    const parsed = parseFloat(v ?? '');

    return Number.isFinite(parsed) ? parsed : 0;
};

const context = ref<'friend' | 'group'>(props.preselectedGroupId ? 'group' : 'friend');
const selectedFriendId = ref<string | null>(props.preselectedFriendId ? String(props.preselectedFriendId) : null);
const selectedGroupId = ref<string | null>(props.preselectedGroupId ? String(props.preselectedGroupId) : null);

const form = useForm({
    description: '',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    group_id: props.preselectedGroupId ? Number(props.preselectedGroupId) : null,
    paid_by: currentUserId,
    split_type: 'equal' as SplitType,
    participants: [] as Participant[],
});

const splitTypes: { value: SplitType; label: string }[] = [
    { value: 'equal', label: 'Equal' },
    { value: 'shares', label: 'Shares' },
    { value: 'percentage', label: '%' },
    { value: 'exact', label: 'Exact' },
];

// Context selection
const selectedGroup = computed(() => props.groups.find((g) => g.id === Number(selectedGroupId.value)));

const buildParticipants = (ids: number[]) => {
    const allUsers = new Map<number, Friend>();
    allUsers.set(currentUserId, { id: currentUserId, name: currentUserName, username: '' });

    props.friends.forEach((f) => allUsers.set(f.id, f));
    props.groups.forEach((g) => g.members.forEach((m) => allUsers.set(m.id, m)));

    const participants = ids
        .filter((id) => allUsers.has(id))
        .map((id) => {
            const user = allUsers.get(id)!;

            return {
                user_id: user.id,
                name: user.name,
                username: user.username,
                share_value: '1',
                amount: '',
            };
        });

    form.participants = participants;

    // Ensure paid_by is still a participant
    if (!participants.some((p) => p.user_id === form.paid_by)) {
        form.paid_by = currentUserId;
    }
};

const onFriendSelect = () => {
    if (selectedFriendId.value) {
        form.group_id = null;
        buildParticipants([currentUserId, Number(selectedFriendId.value)]);
    }
};

const onGroupSelect = () => {
    if (selectedGroupId.value) {
        form.group_id = Number(selectedGroupId.value);
        buildParticipants(selectedGroup.value?.members.map((m) => m.id) ?? []);
    }
};

// Initialize participants from preselection
if (form.group_id) {
    onGroupSelect();
} else if (selectedFriendId.value) {
    onFriendSelect();
}

// Split calculations
const calculatedSplits = computed(() => {
    const amount = parseFloat(form.amount) || 0;
    const participants = form.participants;

    if (!amount || participants.length < 2) {
return [];
}

    switch (form.split_type) {
        case 'equal': {
            const perPerson = amount / participants.length;

            return participants.map((p, i) => ({
                ...p,
                calculatedAmount:
                    i === participants.length - 1
                        ? +(amount - perPerson * (participants.length - 1)).toFixed(2)
                        : +perPerson.toFixed(2),
            }));
        }
        case 'shares': {
            const totalShares = participants.reduce((sum, p) => sum + (num(p.share_value) || 1), 0);
            let allocated = 0;

            return participants.map((p, i) => {
                const share = num(p.share_value) || 1;

                if (i === participants.length - 1) {
                    return { ...p, calculatedAmount: +(amount - allocated).toFixed(2) };
                }

                const calc = +((share / totalShares) * amount).toFixed(2);
                allocated += calc;

                return { ...p, calculatedAmount: calc };
            });
        }
        case 'percentage': {
            let allocated = 0;

            return participants.map((p, i) => {
                const pct = num(p.share_value) || 0;

                if (i === participants.length - 1) {
                    return { ...p, calculatedAmount: +(amount - allocated).toFixed(2) };
                }

                const calc = +((pct / 100) * amount).toFixed(2);
                allocated += calc;

                return { ...p, calculatedAmount: calc };
            });
        }
        case 'exact': {
            return participants.map((p) => ({
                ...p,
                calculatedAmount: +(num(p.amount) || 0).toFixed(2),
            }));
        }
        default:
            return [];
    }
});

const totalPercentage = computed(() =>
    form.participants.reduce((sum, p) => sum + (num(p.share_value) || 0), 0),
);

const totalExact = computed(() =>
    form.participants.reduce((sum, p) => sum + (num(p.amount) || 0), 0),
);

const remainingExact = computed(() => +((parseFloat(form.amount) || 0) - totalExact.value).toFixed(2));

const equalPerPerson = computed(() => {
    const amount = parseFloat(form.amount) || 0;

    if (!amount || form.participants.length === 0) {
return 0;
}

    return amount / form.participants.length;
});

const canSubmit = computed(() => {
    if (form.participants.length < 2) {
return false;
}

    if (!form.description.trim()) {
return false;
}

    if (!(parseFloat(form.amount) > 0)) {
return false;
}

    if (form.split_type === 'percentage' && Math.abs(totalPercentage.value - 100) > 0.01) {
return false;
}

    if (form.split_type === 'exact' && Math.abs(remainingExact.value) > 0.01) {
return false;
}

    return true;
});

function setPaidBy(userId: number) {
    form.paid_by = userId;
}

function submit() {
    if (!canSubmit.value) {
return;
}

    const data = {
        ...form.data(),
        participants: form.participants.map((p) => ({
            user_id: p.user_id,
            share_value:
                form.split_type === 'shares' || form.split_type === 'percentage'
                    ? num(p.share_value) || 0
                    : null,
            amount: form.split_type === 'exact' ? num(p.amount) || 0 : null,
        })),
    };

    form
        .transform(() => data)
        .post(store.url());
}

const formatMoney = (n: number) =>
    n.toLocaleString(undefined, { style: 'currency', currency: 'CAD' });
</script>

<template>
    <Head title="Add Expense" />

    <div class="space-y-6">
        <div class="flex items-center gap-2">
            <ReceiptText class="h-6 w-6" />
            <h1 class="text-2xl font-bold">Add Expense</h1>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Input
                    id="description"
                    v-model="form.description"
                    type="text"
                    placeholder="e.g. Dinner at Italian place"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="amount">Amount</Label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">$</span>
                        <Input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="pl-7"
                        />
                    </div>
                    <InputError :message="form.errors.amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="expense_date">Date</Label>
                    <Input id="expense_date" v-model="form.expense_date" type="date" />
                    <InputError :message="form.errors.expense_date" />
                </div>
            </div>

            <!-- Context selector -->
            <div class="grid gap-2">
                <Label>Split with</Label>
                <div class="grid grid-cols-2 gap-2 rounded-lg border p-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :class="context === 'friend' ? 'bg-muted' : ''"
                        @click="context = 'friend'"
                    >
                        Friend
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :class="context === 'group' ? 'bg-muted' : ''"
                        @click="context = 'group'"
                    >
                        Group
                    </Button>
                </div>

                <div v-if="context === 'friend'">
                    <Select
                        v-model="selectedFriendId"
                        @update:model-value="onFriendSelect"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select a friend" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="friend in friends" :key="friend.id" :value="String(friend.id)">
                                {{ friend.name }} (@{{ friend.username }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div v-else>
                    <Select
                        v-model="selectedGroupId"
                        @update:model-value="onGroupSelect"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select a group" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="group in groups" :key="group.id" :value="String(group.id)">
                                {{ group.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <InputError :message="form.errors.group_id" />
            </div>

            <!-- Paid by -->
            <div class="grid gap-2">
                <Label>Paid by</Label>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="p in form.participants"
                        :key="p.user_id"
                        type="button"
                        variant="outline"
                        size="sm"
                        :class="form.paid_by === p.user_id ? 'border-primary text-primary' : ''"
                        @click="setPaidBy(p.user_id)"
                    >
                        {{ p.name }}
                    </Button>
                </div>
                <InputError :message="form.errors.paid_by" />
            </div>

            <!-- Split type -->
            <div class="grid gap-2">
                <Label>Split type</Label>
                <div class="grid grid-cols-4 gap-2 rounded-lg border p-1">
                    <Button
                        v-for="type in splitTypes"
                        :key="type.value"
                        type="button"
                        variant="ghost"
                        size="sm"
                        :class="form.split_type === type.value ? 'bg-muted' : ''"
                        @click="form.split_type = type.value"
                    >
                        {{ type.label }}
                    </Button>
                </div>
                <InputError :message="(form.errors as Record<string, string>)['split']" />
            </div>

            <!-- Participants -->
            <div v-if="form.participants.length >= 2" class="grid gap-3">
                <Label>Split details</Label>
                <p v-if="form.split_type === 'equal'" class="text-sm text-muted-foreground">
                    Split equally among {{ form.participants.length }} people — {{ formatMoney(equalPerPerson) }} each
                </p>
                <p v-if="form.split_type === 'percentage'" class="text-sm" :class="Math.abs(totalPercentage - 100) > 0.01 ? 'text-red-600' : 'text-muted-foreground'">
                    Total: {{ totalPercentage }}% (must be 100%)
                </p>
                <p v-if="form.split_type === 'exact'" class="text-sm" :class="Math.abs(remainingExact) > 0.01 ? 'text-red-600' : 'text-green-600'">
                    Remaining: {{ formatMoney(remainingExact) }}
                </p>

                <Card v-for="(p, index) in form.participants" :key="p.user_id">
                    <CardContent class="flex items-center gap-3 p-3">
                        <UserAvatar :name="p.name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ p.name }}
                                <span v-if="p.user_id === currentUserId" class="text-xs text-muted-foreground">(you)</span>
                            </p>
                        </div>

                        <!-- Shares -->
                        <div v-if="form.split_type === 'shares'" class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                class="h-7 w-7"
                                @click="p.share_value = String(Math.max(1, (num(p.share_value) || 1) - 1))"
                            >
                                <Minus class="h-3 w-3" />
                            </Button>
                            <span class="w-8 text-center text-sm font-medium">{{ p.share_value || 1 }}</span>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                class="h-7 w-7"
                                @click="p.share_value = String((num(p.share_value) || 1) + 1)"
                            >
                                <Plus class="h-3 w-3" />
                            </Button>
                        </div>

                        <!-- Percentage -->
                        <div v-else-if="form.split_type === 'percentage'" class="flex items-center gap-1">
                            <Input
                                v-model="p.share_value"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                class="h-8 w-20 text-right"
                            />
                            <span class="text-sm text-muted-foreground">%</span>
                        </div>

                        <!-- Exact -->
                        <div v-else-if="form.split_type === 'exact'" class="flex items-center gap-1">
                            <span class="text-sm text-muted-foreground">$</span>
                            <Input
                                v-model="p.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="h-8 w-24 text-right"
                            />
                        </div>

                        <!-- Calculated amount display -->
                        <span
                            v-if="calculatedSplits[index]"
                            class="w-24 shrink-0 text-right text-sm font-semibold"
                        >
                            {{ formatMoney(calculatedSplits[index].calculatedAmount) }}
                        </span>
                    </CardContent>
                </Card>
            </div>

            <Button type="submit" class="w-full" :disabled="!canSubmit || form.processing">
                <Spinner v-if="form.processing" />
                Save Expense
            </Button>
        </form>
    </div>
</template>
