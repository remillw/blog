<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Input } from '@/components/ui/input'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { MoreHorizontal, UserPlus, Search, Plus, Minus } from 'lucide-vue-next'
import { ref, watch } from 'vue'

interface User {
    id: number
    name: string
    email: string
    email_verified_at: string | null
    created_at: string
    roles: Array<{ name: string }>
    permissions: Array<{ name: string }>
    articles_count: number
    points: number
}

interface Props {
    users: {
        data: User[]
        links: any[]
        meta: any
    }
    filters: {
        search?: string
    }
}

const props = defineProps<Props>()

const searchForm = useForm({
    search: props.filters.search || '',
})

const searchQuery = ref(props.filters.search || '')

// Recherche en temps réel
watch(searchQuery, (value) => {
    router.get('/admin/users', { search: value }, {
        preserveState: true,
        replace: true,
    })
}, { debounce: 300 })

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const getRolesList = (roles: Array<{ name: string }>) => {
    return roles.map(role => role.name).join(', ') || 'Aucun rôle'
}

const deleteUser = (user: User) => {
    if (confirm(`Êtes-vous sûr de vouloir supprimer ${user.name} ?`)) {
        router.delete(`/admin/users/${user.id}`)
    }
}

const addPoints = (user: User) => {
    const points = prompt(`Ajouter des points à ${user.name}:`, '10')
    if (points && !isNaN(Number(points))) {
        router.post(`/admin/users/${user.id}/add-points`, {
            points: Number(points)
        })
    }
}

const removePoints = (user: User) => {
    const points = prompt(`Retirer des points à ${user.name}:`, '5')
    if (points && !isNaN(Number(points))) {
        router.post(`/admin/users/${user.id}/remove-points`, {
            points: Number(points)
        })
    }
}
</script>

<template>
    <Head title="Administration - Utilisateurs" />

    <AppLayout :breadcrumbs="[{ label: 'Admin', href: '/admin/categories' }, { label: 'Utilisateurs' }]">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                👥 Gestion des Utilisateurs
            </h2>
        </template>

        <div class="space-y-4">
            <Card>
                <CardHeader>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <CardTitle>Utilisateurs</CardTitle>
                            <CardDescription>Gérez les utilisateurs et leurs permissions.</CardDescription>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <!-- Barre de recherche -->
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Rechercher un utilisateur..."
                                    class="pl-9 w-64"
                                />
                            </div>
                            <!-- Bouton Ajouter -->
                            <Button as-child>
                                <Link :href="'/admin/users/create'">
                                    <UserPlus class="w-4 h-4 mr-2" />
                                    Ajouter un utilisateur
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nom</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Statut</TableHead>
                                <TableHead>Rôles</TableHead>
                                <TableHead>Date création</TableHead>
                                <TableHead>Articles</TableHead>
                                <TableHead>Points</TableHead>
                                <TableHead class="w-[100px]">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="user in users.data" :key="user.id">
                                <TableCell class="font-medium">
                                    {{ user.name }}
                                </TableCell>
                                <TableCell>
                                    {{ user.email }}
                                </TableCell>
                                <TableCell>
                                    <span :class="{
                                        'text-green-600 bg-green-100': user.email_verified_at,
                                        'text-yellow-600 bg-yellow-100': !user.email_verified_at,
                                    }" class="px-2 py-1 rounded-full text-xs font-medium">
                                        {{ user.email_verified_at ? 'Vérifié' : 'Non vérifié' }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span class="text-sm text-gray-600">
                                        {{ getRolesList(user.roles) }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-sm text-gray-500">
                                    {{ formatDate(user.created_at) }}
                                </TableCell>
                                <TableCell>
                                    {{ user.articles_count }}
                                </TableCell>
                                <TableCell>
                                    {{ user.points }}
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" class="h-8 w-8 p-0">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem asChild>
                                                <Link :href="`/admin/users/${user.id}/edit`">
                                                    Modifier
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="addPoints(user)">
                                                <Plus class="w-4 h-4 mr-2" />
                                                Ajouter des points
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="removePoints(user)">
                                                <Minus class="w-4 h-4 mr-2" />
                                                Retirer des points
                                            </DropdownMenuItem>
                                            <DropdownMenuItem 
                                                @click="deleteUser(user)"
                                                class="text-red-600"
                                            >
                                                Supprimer
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                            
                            <!-- Message si aucun utilisateur -->
                            <TableRow v-if="users.data.length === 0">
                                <TableCell colspan="8" class="text-center py-8 text-gray-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <UserPlus class="w-8 h-8 text-gray-400" />
                                        <span>Aucun utilisateur trouvé</span>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    
                    <!-- Pagination -->
                    <div v-if="users.links && users.links.length > 3" class="mt-4 flex justify-center">
                        <nav class="flex space-x-1">
                            <Link
                                v-for="link in users.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                :class="{
                                    'bg-blue-600 text-white': link.active,
                                    'text-gray-700 hover:bg-gray-50': !link.active && link.url,
                                    'text-gray-400 cursor-not-allowed': !link.url,
                                }"
                                class="px-3 py-2 text-sm border rounded-md"
                                v-html="link.label"
                            />
                        </nav>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template> 