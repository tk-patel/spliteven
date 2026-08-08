<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Activity, HandCoins, Layers, LayoutGrid, PlusCircle, Users } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as activityIndex } from '@/routes/activity';
import { index as circleIndex } from '@/routes/circle';
import { create as createExpense } from '@/routes/expenses';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Circle',
        href: circleIndex(),
        icon: Users,
    },
    {
        title: 'Add Expense',
        href: createExpense(),
        icon: PlusCircle,
    },
    {
        title: 'Groups',
        href: '/groups',
        icon: Layers,
    },
    {
        title: 'Settlements',
        href: '/settlements',
        icon: HandCoins,
    },
    {
        title: 'Activity',
        href: activityIndex(),
        icon: Activity,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
