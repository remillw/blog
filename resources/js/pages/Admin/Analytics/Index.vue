<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Head, router } from '@inertiajs/vue3'
import { BarChart3, TrendingUp, Users, Globe, FileText, FolderTree, Clock, Zap } from 'lucide-vue-next'
import { ref } from 'vue'

interface Analytics {
    overview: {
        total_users: number
        total_sites: number
        total_articles: number
        total_categories: number
        pending_suggestions: number
        verified_users: number
        active_sites: number
        published_articles: number
    }
    growth: {
        users: Array<{ date: string; count: number }>
        sites: Array<{ date: string; count: number }>
        articles: Array<{ date: string; count: number }>
        categories: Array<{ date: string; count: number }>
    }
    categories: {
        top_categories: Array<{ name: string; usage_count: number; sites_count: number }>
        language_distribution: Array<{ locale: string; count: number }>
        depth_distribution: Array<{ depth: number; count: number }>
    }
    suggestions: {
        status_distribution: Array<{ status: string; count: number }>
        similarity_stats: { high: number; medium: number; low: number }
        recent_suggestions: any[]
    }
    users: {
        active_users: number
        users_by_role: Array<{ role: string; count: number }>
        registration_trend: Array<{ date: string; count: number }>
    }
    performance: {
        avg_suggestions_per_day: number
        approval_rate: number
        avg_response_time: string
        popular_features: Array<{ name: string; usage: number }>
    }
}

interface Props {
    analytics: Analytics
    period: string
}

const props = defineProps<Props>()
const selectedPeriod = ref(props.period)

const changePeriod = (period: string) => {
    router.get('/admin/analytics', { period }, {
        preserveState: true,
        replace: true,
    })
}

const formatNumber = (num: number) => {
    return new Intl.NumberFormat('fr-FR').format(num)
}

const getStatusColor = (status: string) => {
    const colors = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'approved': 'bg-green-100 text-green-800',
        'rejected': 'bg-red-100 text-red-800',
        'merged': 'bg-blue-100 text-blue-800',
    }
    return colors[status] || 'bg-gray-100 text-gray-800'
}

const getStatusLabel = (status: string) => {
    const labels = {
        'pending': 'En attente',
        'approved': 'Approuvées',
        'rejected': 'Rejetées',
        'merged': 'Fusionnées',
    }
    return labels[status] || status
}
</script>

<template>
    <Head title="Administration - Analytics" />

    <AppLayout :breadcrumbs="[{ label: 'Admin', href: '/admin/categories' }, { label: 'Analytics' }]">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📊 Analytics Administrateur
                </h2>
                <Select :model-value="selectedPeriod" @update:model-value="changePeriod">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Période" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="7">7 derniers jours</SelectItem>
                        <SelectItem value="30">30 derniers jours</SelectItem>
                        <SelectItem value="90">90 derniers jours</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Vue d'ensemble -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BarChart3 class="w-5 h-5" />
                        Vue d'ensemble
                    </CardTitle>
                    <CardDescription>Statistiques générales du système</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <Users class="w-8 h-8 text-blue-600 mx-auto mb-2" />
                            <div class="text-2xl font-bold text-blue-600">{{ formatNumber(analytics.overview.total_users) }}</div>
                            <div class="text-sm text-gray-600">Utilisateurs totaux</div>
                            <div class="text-xs text-blue-500 mt-1">{{ analytics.overview.verified_users }} vérifiés</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <Globe class="w-8 h-8 text-green-600 mx-auto mb-2" />
                            <div class="text-2xl font-bold text-green-600">{{ formatNumber(analytics.overview.total_sites) }}</div>
                            <div class="text-sm text-gray-600">Sites totaux</div>
                            <div class="text-xs text-green-500 mt-1">{{ analytics.overview.active_sites }} actifs</div>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 rounded-lg">
                            <FileText class="w-8 h-8 text-yellow-600 mx-auto mb-2" />
                            <div class="text-2xl font-bold text-yellow-600">{{ formatNumber(analytics.overview.total_articles) }}</div>
                            <div class="text-sm text-gray-600">Articles totaux</div>
                            <div class="text-xs text-yellow-500 mt-1">{{ analytics.overview.published_articles }} publiés</div>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-lg">
                            <FolderTree class="w-8 h-8 text-purple-600 mx-auto mb-2" />
                            <div class="text-2xl font-bold text-purple-600">{{ formatNumber(analytics.overview.total_categories) }}</div>
                            <div class="text-sm text-gray-600">Catégories globales</div>
                            <div class="text-xs text-purple-500 mt-1">{{ analytics.overview.pending_suggestions }} suggestions</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Top Catégories -->
                <Card>
                    <CardHeader>
                        <CardTitle>Top Catégories</CardTitle>
                        <CardDescription>Catégories les plus utilisées</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="category in analytics.categories.top_categories.slice(0, 5)" :key="category.name" 
                                 class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="font-medium">{{ category.name }}</div>
                                    <div class="text-sm text-gray-600">{{ category.sites_count }} sites liés</div>
                                </div>
                                <div class="text-2xl font-bold text-blue-600">{{ category.usage_count }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Distribution des suggestions -->
                <Card>
                    <CardHeader>
                        <CardTitle>Suggestions par statut</CardTitle>
                        <CardDescription>Répartition des suggestions de catégories</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="status in analytics.suggestions.status_distribution" :key="status.status"
                                 class="flex items-center justify-between p-3 rounded-lg"
                                 :class="getStatusColor(status.status)">
                                <div class="font-medium">{{ getStatusLabel(status.status) }}</div>
                                <div class="text-2xl font-bold">{{ status.count }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Métriques de performance -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Zap class="w-5 h-5" />
                            Performance
                        </CardTitle>
                        <CardDescription>Métriques clés du système</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                <div class="text-lg font-bold text-green-600">{{ analytics.performance.approval_rate }}%</div>
                                <div class="text-sm text-gray-600">Taux d'approbation</div>
                            </div>
                            <div class="text-center p-3 bg-blue-50 rounded-lg">
                                <Clock class="w-6 h-6 text-blue-600 mx-auto mb-1" />
                                <div class="text-lg font-bold text-blue-600">{{ analytics.performance.avg_response_time }}</div>
                                <div class="text-sm text-gray-600">Temps de réponse</div>
                            </div>
                            <div class="text-center p-3 bg-purple-50 rounded-lg">
                                <div class="text-lg font-bold text-purple-600">{{ analytics.performance.avg_suggestions_per_day }}</div>
                                <div class="text-sm text-gray-600">Suggestions/jour</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <div class="text-lg font-bold text-gray-600">{{ analytics.users.active_users }}</div>
                                <div class="text-sm text-gray-600">Utilisateurs actifs</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Fonctionnalités populaires -->
                <Card>
                    <CardHeader>
                        <CardTitle>Fonctionnalités populaires</CardTitle>
                        <CardDescription>Usage des différentes fonctionnalités</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="feature in analytics.performance.popular_features" :key="feature.name"
                                 class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="font-medium">{{ feature.name }}</div>
                                <div class="text-lg font-bold text-blue-600">{{ formatNumber(feature.usage) }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Distribution par langue -->
            <Card v-if="analytics.categories.language_distribution.length > 0">
                <CardHeader>
                    <CardTitle>Distribution par langue</CardTitle>
                    <CardDescription>Répartition des catégories par langue</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <div v-for="lang in analytics.categories.language_distribution" :key="lang.locale"
                             class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl mb-2">
                                {{ lang.locale === 'fr' ? '🇫🇷' : lang.locale === 'en' ? '🇬🇧' : 
                                   lang.locale === 'es' ? '🇪🇸' : lang.locale === 'de' ? '🇩🇪' : 
                                   lang.locale === 'it' ? '🇮🇹' : '🌐' }}
                            </div>
                            <div class="text-lg font-bold text-gray-800">{{ lang.count }}</div>
                            <div class="text-sm text-gray-600 uppercase">{{ lang.locale }}</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template> 