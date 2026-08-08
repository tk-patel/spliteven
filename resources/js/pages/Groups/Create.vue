<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import UserAvatar from '@/components/UserAvatar.vue';
import { store } from '@/routes/groups';

type Friend = {
    id: number;
    name: string;
    username: string;
};

defineProps<{
    friends: Friend[];
}>();

const selectedIds = ref<number[]>([]);

const toggle = (id: number) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((fid) => fid !== id);
    } else {
        selectedIds.value.push(id);
    }
};
</script>

<template>
    <Head title="Create Group" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Create group</h1>
            <p class="text-sm text-muted-foreground">Pick a name and add friends from your Circle</p>
        </div>

        <Form
            v-bind="store.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <div class="grid gap-2">
                <Label for="name">Group name</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    placeholder="e.g. Weekend Trip"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label>Select friends</Label>
                <InputError :message="errors.member_ids" />

                <div v-if="friends.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                    <Users class="mx-auto mb-2 h-8 w-8" />
                    <p>No friends in your Circle yet.</p>
                    <p>Add friends before creating a group.</p>
                </div>

                <div v-else class="grid gap-2">
                    <Card
                        v-for="friend in friends"
                        :key="friend.id"
                        :class="selectedIds.includes(friend.id) ? 'border-primary' : ''"
                    >
                        <CardContent class="flex items-center gap-3 p-3">
                            <input
                                type="checkbox"
                                :name="`member_ids[]`"
                                :value="friend.id"
                                :checked="selectedIds.includes(friend.id)"
                                class="h-4 w-4 accent-primary"
                                @change="toggle(friend.id)"
                            />
                            <UserAvatar :name="friend.name" size="sm" />
                            <div>
                                <p class="text-sm font-medium">{{ friend.name }}</p>
                                <p class="text-xs text-muted-foreground">@{{ friend.username }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Button type="submit" class="w-full" :disabled="processing || friends.length === 0">
                <Spinner v-if="processing" />
                Create group
            </Button>
        </Form>
    </div>
</template>
