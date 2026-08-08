<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Layers, Plus, Users } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import UserAvatar from '@/components/UserAvatar.vue';
import { create } from '@/routes/groups';

type GroupMember = {
    id: number;
    name: string;
    username: string;
};

type Group = {
    id: number;
    name: string;
    created_by: number;
    members_count: number;
    members: GroupMember[];
};

defineProps<{
    groups: Group[];
}>();
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Groups</h1>
                <p class="text-sm text-muted-foreground">Shared expense groups</p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="mr-1 h-4 w-4" />
                    Create group
                </Link>
            </Button>
        </div>

        <EmptyState
            v-if="groups.length === 0"
            :icon="Layers"
            title="No groups yet"
            description="Create a group to split expenses with friends"
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <Card v-for="group in groups" :key="group.id">
                <Link :href="`/groups/${group.id}`" class="block">
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-semibold">{{ group.name }}</h3>
                                <p class="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                                    <Users class="h-3.5 w-3.5" />
                                    {{ group.members_count }} members
                                </p>
                            </div>
                            <div class="flex -space-x-2">
                                <UserAvatar
                                    v-for="member in group.members.slice(0, 4)"
                                    :key="member.id"
                                    :name="member.name"
                                    size="sm"
                                    class="ring-2 ring-background"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Link>
            </Card>
        </div>
    </div>
</template>
