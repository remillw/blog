<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Separator } from '@/components/ui/separator'
import { Head, useForm } from '@inertiajs/vue3'
import { Settings, Save, Trash2, Server, Zap } from 'lucide-vue-next'

interface Settings {
    app: {
        name: string
        url: string
        timezone: string
        locale: string
    }
    categories: {
        max_depth: number
        auto_suggest: boolean
        similarity_threshold: number
    }
    ai: {
        openai_api_key: string
        max_tokens: number
        temperature: number
    }
    stats: {
        total_users: number
        total_sites: number
        total_articles: number
        total_categories: number
        cache_size: string
        disk_usage: string
    }
}

interface Props {
    settings: Settings
}

const props = defineProps<Props>()

const form = useForm({
    app_name: props.settings.app.name,
    max_depth: props.settings.categories.max_depth,
    similarity_threshold: props.settings.categories.similarity_threshold,
    auto_suggest: props.settings.categories.auto_suggest,
    max_tokens: props.settings.ai.max_tokens,
    temperature: props.settings.ai.temperature,
})

const submit = () => {
    form.put('/admin/settings', {
        preserveScroll: true,
    })
}

const clearCache = () => {
    if (confirm('Êtes-vous sûr de vouloir vider le cache ?')) {
        // Implementation pour vider le cache
        console.log('Cache cleared')
    }
}
</script>

<template>
    <Head title="Administration - Configuration" />

    <AppLayout :breadcrumbs="[{ label: 'Admin', href: '/admin/categories' }, { label: 'Configuration' }]">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                ⚙️ Configuration Système
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Statistiques système -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Server class="w-5 h-5" />
                        Statistiques Système
                    </CardTitle>
                    <CardDescription>Vue d'ensemble du système</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ settings.stats.total_users }}</div>
                            <div class="text-sm text-gray-600">Utilisateurs</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ settings.stats.total_sites }}</div>
                            <div class="text-sm text-gray-600">Sites</div>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ settings.stats.total_articles }}</div>
                            <div class="text-sm text-gray-600">Articles</div>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">{{ settings.stats.total_categories }}</div>
                            <div class="text-sm text-gray-600">Catégories</div>
                        </div>
                        <div class="text-center p-4 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-600">{{ settings.stats.cache_size }}</div>
                            <div class="text-sm text-gray-600">Cache</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-gray-600">{{ settings.stats.disk_usage }}</div>
                            <div class="text-sm text-gray-600">Disque libre</div>
                        </div>
                    </div>
                    
                    <!-- Actions système -->
                    <Separator class="my-6" />
                    <div class="flex gap-4">
                        <Button @click="clearCache" variant="outline" class="text-red-600 border-red-200">
                            <Trash2 class="w-4 h-4 mr-2" />
                            Vider le cache
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Formulaire de configuration -->
            <form @submit.prevent="submit">
                <div class="space-y-6">
                    <!-- Paramètres de l'application -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Application</CardTitle>
                            <CardDescription>Paramètres généraux de l'application</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="app_name">Nom de l'application</Label>
                                    <Input
                                        id="app_name"
                                        v-model="form.app_name"
                                        type="text"
                                        :class="{ 'border-red-500': form.errors.app_name }"
                                    />
                                    <p v-if="form.errors.app_name" class="text-sm text-red-600">
                                        {{ form.errors.app_name }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label>URL de base</Label>
                                    <Input
                                        :value="settings.app.url"
                                        type="text"
                                        disabled
                                        class="bg-gray-50"
                                    />
                                    <p class="text-xs text-gray-500">Configuré dans .env</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Paramètres des catégories -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Catégories</CardTitle>
                            <CardDescription>Configuration du système de catégories globales</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="space-y-2">
                                    <Label for="max_depth">Profondeur maximale</Label>
                                    <Input
                                        id="max_depth"
                                        v-model.number="form.max_depth"
                                        type="number"
                                        min="1"
                                        max="10"
                                        :class="{ 'border-red-500': form.errors.max_depth }"
                                    />
                                    <p v-if="form.errors.max_depth" class="text-sm text-red-600">
                                        {{ form.errors.max_depth }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="similarity_threshold">Seuil de similarité</Label>
                                    <Input
                                        id="similarity_threshold"
                                        v-model.number="form.similarity_threshold"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="1"
                                        :class="{ 'border-red-500': form.errors.similarity_threshold }"
                                    />
                                    <p v-if="form.errors.similarity_threshold" class="text-sm text-red-600">
                                        {{ form.errors.similarity_threshold }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="auto_suggest">Suggestions automatiques</Label>
                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="auto_suggest"
                                            v-model:checked="form.auto_suggest"
                                        />
                                        <Label for="auto_suggest" class="text-sm text-gray-600">
                                            {{ form.auto_suggest ? 'Activé' : 'Désactivé' }}
                                        </Label>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Paramètres IA -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Zap class="w-5 h-5" />
                                Intelligence Artificielle
                            </CardTitle>
                            <CardDescription>Configuration des paramètres OpenAI</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="space-y-2">
                                    <Label>Clé API OpenAI</Label>
                                    <Input
                                        :value="settings.ai.openai_api_key"
                                        type="password"
                                        disabled
                                        class="bg-gray-50"
                                    />
                                    <p class="text-xs text-gray-500">Configuré dans .env</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="max_tokens">Tokens maximum</Label>
                                    <Input
                                        id="max_tokens"
                                        v-model.number="form.max_tokens"
                                        type="number"
                                        min="100"
                                        max="8000"
                                        :class="{ 'border-red-500': form.errors.max_tokens }"
                                    />
                                    <p v-if="form.errors.max_tokens" class="text-sm text-red-600">
                                        {{ form.errors.max_tokens }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="temperature">Température</Label>
                                    <Input
                                        id="temperature"
                                        v-model.number="form.temperature"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="2"
                                        :class="{ 'border-red-500': form.errors.temperature }"
                                    />
                                    <p v-if="form.errors.temperature" class="text-sm text-red-600">
                                        {{ form.errors.temperature }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Bouton de sauvegarde -->
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                            <Save class="w-4 h-4 mr-2" />
                            {{ form.processing ? 'Sauvegarde...' : 'Sauvegarder les paramètres' }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template> 