<script setup lang="ts">
import { router, useHttp, usePage } from '@inertiajs/vue3';
import { Loader2, Search, UserPlus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import UserAvatar from '@/components/UserAvatar.vue';
import { invite, search as circleSearch } from '@/routes/circle';

type SearchResult = {
    id: number;
    name: string;
    username: string;
    friendship_status: 'pending' | 'accepted' | 'rejected' | null;
};

type SearchResponse = {
    results: SearchResult[];
};

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const page = usePage();
const http = useHttp(circleSearch(), { query: '' });

const query = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
const searched = ref(false);
const invitingId = ref<number | null>(null);

const USERNAME_PATTERN = /^[a-z0-9_]{3,30}$/;

const searchUsers = useDebounceFn(async () => {
    const q = query.value.trim().toLowerCase();

    if (!USERNAME_PATTERN.test(q)) {
        results.value = [];
        searched.value = false;

        return;
    }

    loading.value = true;

    try {
        http.query = q;

        const data = (await http.submit()) as SearchResponse;

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

const inviteUser = (userId: number) => {
    invitingId.value = userId;

    router.post(
        invite(userId).url,
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
                <SheetDescription>Enter an exact username to add someone to your Circle</SheetDescription>
            </SheetHeader>

            <div class="relative mt-4">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="query"
                    type="text"
                    placeholder="Exact username (e.g. jane_doe)"
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
                    No user found with that username
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
                        @click="inviteUser(result.id)"
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
