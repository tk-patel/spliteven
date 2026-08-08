<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { HandCoins } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { store } from '@/routes/settlements';

type Friend = {
    id: number;
    name: string;
    username: string;
};

type Group = {
    id: number;
    name: string;
};

const props = defineProps<{
    friends: Friend[];
    groups: Group[];
    preselectedPayeeId?: number | string | null;
    preselectedAmount?: number | string | null;
    preselectedGroupId?: number | string | null;
}>();

const payeeId = ref<string | null>(props.preselectedPayeeId ? String(props.preselectedPayeeId) : null);
const groupId = ref<string | null>(props.preselectedGroupId ? String(props.preselectedGroupId) : null);

const form = useForm({
    payee_id: props.preselectedPayeeId ? String(props.preselectedPayeeId) : '',
    amount: props.preselectedAmount ? String(props.preselectedAmount) : '',
    group_id: props.preselectedGroupId ? String(props.preselectedGroupId) : '',
    note: '',
    settled_at: new Date().toISOString().split('T')[0],
});

const selectedPayee = computed(() => props.friends.find((f) => f.id === Number(payeeId.value)));

const onPayeeSelect = () => {
    form.payee_id = payeeId.value ?? '';
};

const onGroupSelect = () => {
    form.group_id = groupId.value ?? '';
};

function submit() {
    form.post(store.url());
}
</script>

<template>
    <Head title="Settle Up" />

    <div class="space-y-6">
        <div class="flex items-center gap-2">
            <HandCoins class="h-6 w-6" />
            <h1 class="text-2xl font-bold">Settle Up</h1>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div v-if="selectedPayee" class="flex items-center gap-3 rounded-lg border bg-muted/50 p-4">
                <UserAvatar :name="selectedPayee.name" />
                <div>
                    <p class="text-sm text-muted-foreground">You paid</p>
                    <p class="text-sm font-medium text-foreground">{{ selectedPayee.name }}</p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label>Paid to</Label>
                <Select v-model="payeeId" @update:model-value="onPayeeSelect">
                    <SelectTrigger>
                        <SelectValue placeholder="Select a friend" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="friend in friends" :key="friend.id" :value="String(friend.id)">
                            {{ friend.name }} (@{{ friend.username }})
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.payee_id" />
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
                    <Label for="settled_at">Date</Label>
                    <Input id="settled_at" v-model="form.settled_at" type="date" />
                    <InputError :message="form.errors.settled_at" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label>Group (optional)</Label>
                <Select v-model="groupId" @update:model-value="onGroupSelect">
                    <SelectTrigger>
                        <SelectValue placeholder="No group (personal)" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="''">No group (personal)</SelectItem>
                        <SelectItem v-for="group in groups" :key="group.id" :value="String(group.id)">
                            {{ group.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.group_id" />
            </div>

            <div class="grid gap-2">
                <Label for="note">Note (optional)</Label>
                <Input
                    id="note"
                    v-model="form.note"
                    type="text"
                    placeholder="e.g. Paid you back for dinner"
                />
                <InputError :message="form.errors.note" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Record payment
            </Button>
        </form>
    </div>
</template>
