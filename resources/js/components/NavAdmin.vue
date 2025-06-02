<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar'
import { type NavItem, type SharedData } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'
import { ShieldCheck, Settings, BarChart3, FolderTree } from 'lucide-vue-next'
import { computed } from 'vue'

const page = usePage<SharedData>()

// Vérifier si l'utilisateur est admin
const isAdmin = computed(() => {
    const user = page.props.auth?.user
    return user?.permissions?.includes('administrator') || false
})

// Vérifier les permissions spécifiques
const hasPermission = (permission: string) => {
    const user = page.props.auth?.user
    return user?.permissions?.includes(permission) || user?.permissions?.includes('administrator') || false
}

// Liens d'administration
const adminNavItems = computed(() => {
    const items: NavItem[] = []
    
    if (hasPermission('manage categories') || hasPermission('review suggestions') || hasPermission('view analytics')) {
        items.push({
            title: 'Catégories',
            href: '/admin/categories',
            icon: FolderTree,
        })
    }
    
    if (hasPermission('administrator')) {
        items.push({
            title: 'Utilisateurs',
            href: '/admin/users',
            icon: ShieldCheck,
        })
        
        items.push({
            title: 'Configuration',
            href: '/admin/settings',
            icon: Settings,
        })
        
        items.push({
            title: 'Analytics',
            href: '/admin/analytics',
            icon: BarChart3,
        })
    }
    
    return items
})
</script>

<template>
    <SidebarGroup v-if="isAdmin && adminNavItems.length > 0" class="px-2 py-0">
        <SidebarGroupLabel>Administration</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in adminNavItems" :key="item.title">
                <SidebarMenuButton 
                    as-child 
                    :is-active="item.href === page.url"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template> 