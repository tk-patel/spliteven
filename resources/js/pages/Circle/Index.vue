<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { UserCheck, UserMinus, UserPlus, Users } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import SearchSheet from '@/components/SearchSheet.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import UserAvatar from '@/components/UserAvatar.vue';

type Friend = {
    friendship_id: number;
    id: number;
    name: string;
    username: string;
};

type PendingRequest = {
    id: number;
    requester: Friend;
    created_at: string;
};

type PendingSent = {
    id: number;
    addressee: Friend;
    created_at: string;
};

defineProps<{
    friends: Friend[];
    pendingReceived: PendingRequest[];
    pendingSent: PendingSent[];
}>();

const searchOpen = ref(false);

const respond = (type: 'accept' | 'reject', friendshipId: number) => {
    router.post(`/circle/${type}/${friendshipId}`, {}, { preserveScroll: true });
};

const remove = (friendshipId: number) => {
    if (confirm('Remove this friend from your Circle?')) {
        router.delete(`/circle/${friendshipId}`, { preserveScroll: true });
    }
};

const formattedDate = (date: string) =>
    new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Circle</h1>
                <p class="text-sm text-muted-foreground">Your friends</p>
            </div>
            <Button @click="searchOpen = true">
                <UserPlus class="mr-1 h-4 w-4" />
                Add friend
            </Button>
        </div>

        <!-- Pending received -->
        <section v-if="pendingReceived.length > 0" class="space-y-3">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">
                Pending requests
            </h2>
            <Card v-for="request in pendingReceived" :key="request.id">
                <CardContent class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="request.requester.name" />
                        <div>
                            <p class="text-sm font-medium">{{ request.requester.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                @{{ request.requester.username }} · {{ formattedDate(request.created_at) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" @click="respond('accept', request.id)">
                            <UserCheck class="mr-1 h-3 w-3" />
                            Accept
                        </Button>
                        <Button size="sm" variant="outline" @click="respond('reject', request.id)">
                            Reject
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- Pending sent -->
        <section v-if="pendingSent.length > 0" class="space-y-3">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">
                Sent requests
            </h2>
            <Card v-for="request in pendingSent" :key="request.id">
                <CardContent class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="request.addressee.name" />
                        <div>
                            <p class="text-sm font-medium">{{ request.addressee.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                @{{ request.addressee.username }} · {{ formattedDate(request.created_at) }}
                            </p>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-muted-foreground">Pending</span>
                </CardContent>
            </Card>
        </section>

        <!-- Friends list -->
        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">
                Your Circle ({{ friends.length }})
            </h2>

            <EmptyState
                v-if="friends.length === 0 && pendingReceived.length === 0 && pendingSent.length === 0"
                :icon="Users"
                title="Your Circle is empty"
                description="Search by username to add friends"
            />

            <Card v-for="friend in friends" :key="friend.id">
                <CardContent class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="friend.name" />
                        <div>
                            <p class="text-sm font-medium">{{ friend.name }}</p>
                            <p class="text-xs text-muted-foreground">@{{ friend.username }}</p>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="text-muted-foreground hover:text-destructive"
                        @click="remove(friend.friendship_id)"
                    >
                        <UserMinus class="mr-1 h-3 w-3" />
                        Remove
                    </Button>
                </CardContent>
            </Card>
        </section>

        <SearchSheet v-model:open="searchOpen" />
    </div>
</template>
