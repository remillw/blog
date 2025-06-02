<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { UserPlus, ArrowLeft } from 'lucide-vue-next'

interface Role {
    id: number
    name: string
}

interface Permission {
    id: number
    name: string
}

interface Props {
    roles: Role[]
    permissions: Permission[]
}

const props = defineProps<Props>()

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    points: 0,
    roles: [] as string[],
    permissions: [] as string[],
})

const submit = () => {
    form.post('/admin/users')
}
</script>

<template>
    <Head title="Administration - Créer un utilisateur" />

    <AppLayout :breadcrumbs="[
        { label: 'Admin', href: '/admin/categories' }, 
        { label: 'Utilisateurs', href: '/admin/users' }, 
        { label: 'Créer' }
    ]">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="'/admin/users'" class="p-2 hover:bg-gray-100 rounded-lg">
                    <ArrowLeft class="w-5 h-5" />
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    👤 Créer un utilisateur
                </h2>
            </div>
        </template>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <UserPlus class="w-5 h-5" />
                    Nouvel utilisateur
                </CardTitle>
                <CardDescription>Créez un nouveau compte utilisateur avec permissions</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Informations de base -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="name">Nom complet *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                :class="{ 'border-red-500': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="text-sm text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email *</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                :class="{ 'border-red-500': form.errors.email }"
                            />
                            <p v-if="form.errors.email" class="text-sm text-red-600">
                                {{ form.errors.email }}
                            </p>
                        </div>
                    </div>

                    <!-- Mot de passe -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="password">Mot de passe *</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                :class="{ 'border-red-500': form.errors.password }"
                            />
                            <p v-if="form.errors.password" class="text-sm text-red-600">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">Confirmer le mot de passe *</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                required
                            />
                        </div>
                    </div>

                    <!-- Points -->
                    <div class="space-y-2">
                        <Label for="points">Points de départ</Label>
                        <Input
                            id="points"
                            v-model.number="form.points"
                            type="number"
                            min="0"
                            max="10000"
                        />
                        <p class="text-xs text-gray-500">Points attribués à la création du compte</p>
                    </div>

                    <!-- Rôles -->
                    <div class="space-y-2" v-if="roles.length > 0">
                        <Label>Rôles</Label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <div v-for="role in roles" :key="role.id" class="flex items-center space-x-2">
                                <Checkbox
                                    :id="`role-${role.id}`"
                                    :value="role.name"
                                    v-model:checked="form.roles"
                                />
                                <Label :for="`role-${role.id}`" class="text-sm">
                                    {{ role.name }}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div class="space-y-2" v-if="permissions.length > 0">
                        <Label>Permissions spécifiques</Label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <div v-for="permission in permissions" :key="permission.id" class="flex items-center space-x-2">
                                <Checkbox
                                    :id="`permission-${permission.id}`"
                                    :value="permission.name"
                                    v-model:checked="form.permissions"
                                />
                                <Label :for="`permission-${permission.id}`" class="text-sm">
                                    {{ permission.name }}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-4">
                        <Button type="button" variant="outline" as-child>
                            <Link :href="'/admin/users'">Annuler</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            <UserPlus class="w-4 h-4 mr-2" />
                            {{ form.processing ? 'Création...' : 'Créer l\'utilisateur' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </AppLayout>
</template> 