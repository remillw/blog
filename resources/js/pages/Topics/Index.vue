<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Topics</h2>
                    <p class="text-gray-600">Gérez vos sujets d'articles</p>
                </div>
                <div class="flex gap-2">
                    <Button @click="router.get('/topics')" class="bg-emerald-600 hover:bg-emerald-700">
                        📅 Calendrier Éditorial
                    </Button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <Card>
                <CardContent class="p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-semibold mb-2">Calendrier Éditorial</h3>
                        <p class="text-gray-600 mb-4">
                            Planifiez vos topics par date et organisez votre contenu éditorial.
                        </p>
                        <Button @click="router.get('/topics')" class="bg-emerald-600 hover:bg-emerald-700">
                            Ouvrir le Calendrier
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
</script>
