<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock, UserCheck, UserMinus, UserPlus, Users, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import SearchSheet from '@/components/SearchSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

const props = defineProps<{
    friends: Friend[];
    pendingReceived: PendingRequest[];
    pendingSent: PendingSent[];
}>();

const searchOpen = ref(false);

const defaultTab = computed(() =>
    props.friends.length === 0 && props.pendingSent.length > 0 ? 'sent' : 'circle',
);

const respond = (type: 'accept' | 'reject', friendshipId: number) => {
    router.post(`/circle/${type}/${friendshipId}`, {}, { preserveScroll: true });
};

const cancel = (friendshipId: number) => {
    if (confirm('Cancel this friend request?')) {
        router.delete(`/circle/cancel/${friendshipId}`, { preserveScroll: true });
    }
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
        <section v-if="pendingReceived.length > 0" class="space-y-2">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">
                Pending requests
            </h2>
            <Card v-for="request in pendingReceived" :key="request.id">
                <CardContent class="flex items-center justify-between px-3 py-2 md:px-4 md:py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <UserAvatar :name="request.requester.name" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ request.requester.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                @{{ request.requester.username }} · {{ formattedDate(request.created_at) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
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

        <Tabs :default-value="defaultTab" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
                <TabsTrigger value="circle" class="gap-1.5">
                    Your Circle
                    <Badge v-if="friends.length > 0" variant="secondary" class="h-5 min-w-5 px-1">
                        {{ friends.length }}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="sent" class="gap-1.5">
                    Sent requests
                    <Badge v-if="pendingSent.length > 0" variant="secondary" class="h-5 min-w-5 px-1">
                        {{ pendingSent.length }}
                    </Badge>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="circle" class="mt-4 space-y-2">
                <EmptyState
                    v-if="friends.length === 0"
                    :icon="Users"
                    title="Your Circle is empty"
                    description="Search by username to add friends"
                />

                <Card v-for="friend in friends" :key="friend.id">
                    <CardContent class="flex items-center justify-between px-3 py-2 md:px-4 md:py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <UserAvatar :name="friend.name" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ friend.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">@{{ friend.username }}</p>
                            </div>
                        </div>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="shrink-0 text-muted-foreground hover:text-destructive"
                            @click="remove(friend.friendship_id)"
                        >
                            <UserMinus class="mr-1 h-3 w-3" />
                            Remove
                        </Button>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="sent" class="mt-4 space-y-2">
                <EmptyState
                    v-if="pendingSent.length === 0"
                    :icon="Clock"
                    title="No sent requests"
                    description="Friend requests you send will appear here until accepted"
                />

                <Card v-for="request in pendingSent" :key="request.id">
                    <CardContent class="flex items-center justify-between gap-2 px-3 py-2 md:px-4 md:py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <UserAvatar :name="request.addressee.name" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ request.addressee.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    @{{ request.addressee.username }} · {{ formattedDate(request.created_at) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Badge variant="outline" class="hidden sm:inline-flex">Pending</Badge>
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 px-2 text-muted-foreground hover:text-destructive"
                                @click="cancel(request.id)"
                            >
                                <X class="h-3.5 w-3.5 sm:mr-1" />
                                <span class="hidden sm:inline">Cancel</span>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>

        <SearchSheet v-model:open="searchOpen" />
    </div>
</template>
