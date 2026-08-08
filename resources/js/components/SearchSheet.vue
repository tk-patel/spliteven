<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Loader2, Search, UserPlus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import UserAvatar from '@/components/UserAvatar.vue';

type SearchResult = {
    id: number;
    name: string;
    username: string;
    friendship_status: 'pending' | 'accepted' | 'rejected' | null;
};

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const page = usePage();

const query = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
const searched = ref(false);
const invitingId = ref<number | null>(null);

const searchUsers = useDebounceFn(async () => {
    const q = query.value.trim();

    if (q.length < 2) {
        results.value = [];
        searched.value = false;

        return;
    }

    loading.value = true;

    try {
        const response = await fetch('/circle/search', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ query: q }),
        });

        const data = await response.json();

        results.value = data.results ?? [];
        searched.value = true;
    } catch {
        results.value = [];
        searched.value = true;
    } finally {
        loading.value = false;
    }
}, 300);

watch(query, () => {
    searched.value = false;
    searchUsers();
});

const invite = (userId: number) => {
    invitingId.value = userId;

    router.post(
        `/circle/invite/${userId}`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                invitingId.value = null;
                searchUsers();
            },
        },
    );
};

const isCurrentUser = (userId: number) => page.props.auth.user.id === userId;
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="bottom" class="max-h-[80vh] overflow-y-auto sm:max-w-lg sm:rounded-t-xl sm:mx-auto">
            <SheetHeader>
                <SheetTitle>Find friends</SheetTitle>
                <SheetDescription>Search by username to add friends to your Circle</SheetDescription>
            </SheetHeader>

            <div class="relative mt-4">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="query"
                    type="text"
                    placeholder="Search username…"
                    autofocus
                    class="pl-9"
                />
            </div>

            <div class="mt-4 space-y-2">
                <div v-if="loading" class="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
                    <Loader2 class="h-4 w-4 animate-spin" />
                    Searching…
                </div>

                <div
                    v-else-if="searched && results.length === 0"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    No users found
                </div>

                <div
                    v-for="result in results"
                    v-else
                    :key="result.id"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="result.name" size="sm" />
                        <div>
                            <p class="text-sm font-medium">{{ result.name }}</p>
                            <p class="text-xs text-muted-foreground">@{{ result.username }}</p>
                        </div>
                    </div>

                    <Button
                        v-if="isCurrentUser(result.id)"
                        size="sm"
                        variant="ghost"
                        disabled
                    >
                        You
                    </Button>
                    <Button
                        v-else-if="result.friendship_status === 'accepted'"
                        size="sm"
                        variant="ghost"
                        disabled
                    >
                        Friends
                    </Button>
                    <Button
                        v-else-if="result.friendship_status === 'pending'"
                        size="sm"
                        variant="ghost"
                        disabled
                    >
                        Pending
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        :disabled="invitingId === result.id"
                        @click="invite(result.id)"
                    >
                        <Loader2 v-if="invitingId === result.id" class="mr-1 h-3 w-3 animate-spin" />
                        <UserPlus v-else class="mr-1 h-3 w-3" />
                        Invite
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
