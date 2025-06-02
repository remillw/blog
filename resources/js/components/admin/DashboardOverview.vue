<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { FolderTree } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'

const categories = ref([])
const loading = ref(true)

onMounted(async () => {
    try {
        const response = await fetch('/api/admin/categories')
        const data = await response.json()
        categories.value = data.categories || []
    } catch (error) {
        console.error('Erreur lors du chargement des catégories:', error)
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <FolderTree class="w-5 h-5" />
                Catégories Globales
            </CardTitle>
            <CardDescription>Gérez les catégories utilisées sur tous les sites</CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="text-center py-8">
                Chargement des catégories...
            </div>
            
            <Table v-else-if="categories.length > 0">
                <TableHeader>
                    <TableRow>
                        <TableHead>Nom</TableHead>
                        <TableHead>Utilisation</TableHead>
                        <TableHead>Langue</TableHead>
                        <TableHead>Profondeur</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="category in categories.slice(0, 10)" :key="category.id">
                        <TableCell class="font-medium">{{ category.name }}</TableCell>
                        <TableCell>{{ category.usage_count }}</TableCell>
                        <TableCell>{{ category.locale }}</TableCell>
                        <TableCell>{{ category.depth }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            
            <div v-else class="text-center py-8 text-gray-500">
                <FolderTree class="w-8 h-8 mx-auto mb-2 text-gray-400" />
                <p>Aucune catégorie globale trouvée</p>
            </div>
        </CardContent>
    </Card>
</template> 