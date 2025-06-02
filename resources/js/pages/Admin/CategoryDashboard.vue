<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import DashboardOverview from '@/components/admin/DashboardOverview.vue'
import SuggestionsManager from '@/components/admin/SuggestionsManager.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Head } from '@inertiajs/vue3'
import { FolderTree, Lightbulb } from 'lucide-vue-next'
</script>

<template>
    <Head title="Administration - Catégories Globales" />

    <AppLayout :breadcrumbs="[{ label: 'Administration', href: '/admin/categories' }, { label: 'Catégories' }]">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🗂️ Gestion des Catégories Globales
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Vue d'ensemble rapide -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FolderTree class="w-5 h-5" />
                            Catégories Globales
                        </CardTitle>
                        <CardDescription>Système unifié de catégorisation</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-center p-4">
                            <div class="text-3xl font-bold text-blue-600" id="categories-count">-</div>
                            <div class="text-sm text-gray-600">Catégories disponibles</div>
                            <div class="text-xs text-gray-500 mt-2">Utilisées sur tous les sites</div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Lightbulb class="w-5 h-5" />
                            Suggestions IA & Utilisateurs
                        </CardTitle>
                        <CardDescription>Suggestions automatiques et manuelles</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm">En attente</span>
                                <span class="font-bold text-yellow-600" id="pending-count">-</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm">Approuvées</span>
                                <span class="font-bold text-green-600" id="approved-count">-</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm">Rejetées</span>
                                <span class="font-bold text-red-600" id="rejected-count">-</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Onglets de gestion -->
            <Tabs default-value="suggestions" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="suggestions">
                        <Lightbulb class="w-4 h-4 mr-2" />
                        Suggestions
                    </TabsTrigger>
                    <TabsTrigger value="categories">
                        <FolderTree class="w-4 h-4 mr-2" />
                        Catégories
                    </TabsTrigger>
                </TabsList>
                
                <TabsContent value="suggestions" class="space-y-4">
                    <SuggestionsManager />
                </TabsContent>
                
                <TabsContent value="categories" class="space-y-4">
                    <DashboardOverview />
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template> 