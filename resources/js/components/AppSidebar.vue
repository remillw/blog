<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useRoutes } from '@/composables/useRoutes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, FileText, Folder, Globe, LayoutGrid, Tags } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const { appRoutes, articleRoutes, categoryRoutes, siteRoutes } = useRoutes();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: appRoutes.dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Sites',
        href: siteRoutes.index(),
        icon: Globe,
    },
    {
        title: 'Articles',
        href: articleRoutes.index(),
        icon: FileText,
    },
    {
        title: 'Categories',
        href: categoryRoutes.index(),
        icon: Tags,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="appRoutes.dashboard()">
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
