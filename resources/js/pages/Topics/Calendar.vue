<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Calendrier Éditorial</h2>
                    <p class="text-gray-600">Planifiez et organisez vos sujets d'articles</p>
                </div>
                <div class="flex gap-2">
                    <Button @click="showGenerateModal = true" variant="outline" class="border-purple-600 text-purple-600 hover:bg-purple-50">
                        🤖 Générer avec IA
                    </Button>
                    <Button @click="showAddModal = true" class="bg-emerald-600 hover:bg-emerald-700">
                        ➕ Ajouter manuellement
                    </Button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                📊
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Topics</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.total_topics }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="p-2 bg-green-100 rounded-lg">
                                ⏰
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Programmés</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.scheduled }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="p-2 bg-purple-100 rounded-lg">
                                ✅
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Publiés</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.published }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="p-2 bg-gray-100 rounded-lg">
                                📝
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Brouillons</p>
                                <p class="text-2xl font-bold text-gray-900">{{ stats.draft }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filtres -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center gap-2">
                            <Label>Site :</Label>
                            <Select v-model="filters.site_id" @update:model-value="applyFilters">
                                <SelectTrigger class="w-48">
                                    <SelectValue placeholder="Tous les sites" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Tous les sites</SelectItem>
                                    <SelectItem v-for="site in sites" :key="site.id" :value="site.id.toString()">
                                        {{ site.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-center gap-2">
                            <Label>Statut :</Label>
                            <Select v-model="filters.status" @update:model-value="applyFilters">
                                <SelectTrigger class="w-40">
                                    <SelectValue placeholder="Tous" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Tous</SelectItem>
                                    <SelectItem value="draft">Brouillon</SelectItem>
                                    <SelectItem value="scheduled">Programmé</SelectItem>
                                    <SelectItem value="published">Publié</SelectItem>
                                    <SelectItem value="cancelled">Annulé</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-center gap-2">
                            <Label>Mois :</Label>
                            <Input
                                v-model="selectedMonth"
                                type="month"
                                @change="changeMonth"
                                class="w-40"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Tableau des Topics -->
            <Card>
                <CardHeader>
                    <CardTitle>Topics - {{ formatMonthYear(selectedMonth) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Titre</TableHead>
                                <TableHead>Site</TableHead>
                                <TableHead>Langue</TableHead>
                                <TableHead>Date prévue</TableHead>
                                <TableHead>Heure</TableHead>
                                <TableHead>Statut</TableHead>
                                <TableHead>Priorité</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="topics.length === 0">
                                <TableCell :colspan="8" class="text-center py-8">
                                    <div class="text-gray-500">
                                        <div class="text-lg mb-2">Aucun topic trouvé</div>
                                        <Button @click="showAddModal = true" variant="outline" size="sm">
                                            Créer votre premier topic
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="topic in topics" :key="topic.id" class="hover:bg-gray-50">
                                <TableCell class="font-medium">
                                    <div class="max-w-xs">
                                        <div class="flex items-center gap-2">
                                            <div class="font-semibold text-gray-900">{{ topic.title }}</div>
                                            <span v-if="topic.article_id" class="inline-flex items-center px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
                                                ✓ Article généré
                                            </span>
                                        </div>
                                        <div v-if="topic.description" class="text-sm text-gray-500 truncate">
                                            {{ topic.description }}
                                        </div>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span v-for="keyword in topic.keywords.slice(0, 3)" :key="keyword" 
                                                  class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                                {{ keyword }}
                                            </span>
                                            <span v-if="topic.keywords.length > 3" 
                                                  class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">
                                                +{{ topic.keywords.length - 3 }}
                                            </span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="font-medium text-gray-900">{{ topic.site.name }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{ topic.language_code.toUpperCase() }}</Badge>
                                </TableCell>
                                <TableCell>
                                    <div v-if="topic.scheduled_date" class="text-sm">
                                        {{ formatDate(topic.scheduled_date) }}
                                    </div>
                                    <div v-else class="text-gray-400 text-sm">Non planifié</div>
                                </TableCell>
                                <TableCell>
                                    <div v-if="topic.scheduled_time" class="text-sm">
                                        {{ formatTime(topic.scheduled_time) }}
                                    </div>
                                    <div v-else class="text-gray-400 text-sm">-</div>
                                </TableCell>
                                <TableCell>
                                    <Badge :class="getStatusBadgeClass(topic.status)">
                                        {{ getStatusLabel(topic.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center">
                                        <span class="text-sm">{{ topic.priority }}</span>
                                        <div class="ml-2 flex">
                                            <div v-for="i in 5" :key="i" 
                                                 :class="i <= topic.priority ? 'text-yellow-400' : 'text-gray-300'"
                                                 class="text-xs">★</div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button 
                                            @click="generateArticleFromTopic(topic)" 
                                            variant="outline" 
                                            size="sm" 
                                            :class="topic.article_id ? 'bg-green-50 text-green-700 hover:bg-green-100 border-green-300' : 'bg-purple-50 text-purple-700 hover:bg-purple-100 border-purple-300'" 
                                            :disabled="generatingArticles[topic.id]"
                                        >
                                            {{ generatingArticles[topic.id] ? '🔄' : (topic.article_id ? '🔄' : '🤖') }} 
                                            {{ generatingArticles[topic.id] ? 'Génération...' : (topic.article_id ? 'Regénérer l\'article' : 'Générer l\'article') }}
                                        </Button>
                                        <Button @click="editTopic(topic)" variant="outline" size="sm">
                                            ✏️ Éditer
                                        </Button>
                                        <Button @click="scheduleTopic(topic)" variant="outline" size="sm" v-if="!topic.scheduled_date">
                                            📅 Planifier
                                        </Button>
                                        <Button @click="deleteTopic(topic)" variant="destructive" size="sm">
                                            🗑️
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Modal de génération IA -->
        <Dialog v-model:open="showGenerateModal">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>🤖 Générer des topics avec l'IA</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="generateTopics" class="space-y-4">
                    <div>
                        <Label for="generate_site_id">Site *</Label>
                        <Select v-model="generateForm.site_id" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionnez un site" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="site in sites" :key="site.id" :value="site.id.toString()">
                                    {{ site.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Label for="generate_language">Langue *</Label>
                        <Select v-model="generateForm.language_code" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionnez une langue" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="fr">Français</SelectItem>
                                <SelectItem value="en">Anglais</SelectItem>
                                <SelectItem value="es">Espagnol</SelectItem>
                                <SelectItem value="de">Allemand</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Label for="generate_count">Nombre de topics à générer</Label>
                        <Input 
                            v-model.number="generateForm.count" 
                            type="number" 
                            min="1" 
                            max="20" 
                            placeholder="10"
                        />
                    </div>

                    <div>
                        <Label for="generate_focus">Thématique spécifique (optionnel)</Label>
                        <Input 
                            v-model="generateForm.focus_area" 
                            placeholder="ex: cuisine végétarienne, voyage en Europe..."
                        />
                    </div>

                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-blue-800">
                            🔥 L'IA va générer {{ generateForm.count || 10 }} sujets uniques basés sur les informations de votre site et la thématique choisie.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="closeGenerateModal">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="generatingTopics">
                            {{ generatingTopics ? '🔄 Génération...' : '🤖 Générer les topics' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal d'ajout de topic -->
        <Dialog v-model:open="showAddModal">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ editingTopic ? 'Modifier le topic' : 'Ajouter un topic' }}</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="saveTopic" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="site_id">Site *</Label>
                            <Select v-model="topicForm.site_id" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionnez un site" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="site in sites" :key="site.id" :value="site.id.toString()">
                                        {{ site.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="language_code">Langue *</Label>
                            <Select v-model="topicForm.language_code" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionnez une langue" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fr">Français</SelectItem>
                                    <SelectItem value="en">Anglais</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div>
                        <Label for="title">Titre *</Label>
                        <Input v-model="topicForm.title" placeholder="Titre du topic" required />
                    </div>

                    <div>
                        <Label for="description">Description</Label>
                        <Textarea v-model="topicForm.description" placeholder="Description du topic" rows="3" />
                    </div>

                    <div>
                        <Label for="keywords">Mots-clés *</Label>
                        <Input v-model="keywordsString" placeholder="mot1, mot2, mot3" required />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="scheduled_date">Date de publication</Label>
                            <Input v-model="topicForm.scheduled_date" type="date" />
                        </div>
                        
                        <div>
                            <Label for="scheduled_time">Heure</Label>
                            <Input v-model="topicForm.scheduled_time" type="time" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="priority">Priorité</Label>
                            <Select v-model="topicForm.priority">
                                <SelectTrigger>
                                    <SelectValue placeholder="Priorité" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">1 - Très faible</SelectItem>
                                    <SelectItem value="2">2 - Faible</SelectItem>
                                    <SelectItem value="3">3 - Normale</SelectItem>
                                    <SelectItem value="4">4 - Élevée</SelectItem>
                                    <SelectItem value="5">5 - Très élevée</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label for="status">Statut</Label>
                            <Select v-model="topicForm.status">
                                <SelectTrigger>
                                    <SelectValue placeholder="Statut" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft">Brouillon</SelectItem>
                                    <SelectItem value="scheduled">Programmé</SelectItem>
                                    <SelectItem value="published">Publié</SelectItem>
                                    <SelectItem value="cancelled">Annulé</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div v-if="editingTopic">
                        <Label for="editorial_notes">Notes éditoriales</Label>
                        <Textarea v-model="topicForm.editorial_notes" placeholder="Notes pour l'équipe éditoriale" rows="3" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="closeModal">
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="saving">
                            {{ saving ? 'Enregistrement...' : (editingTopic ? 'Mettre à jour' : 'Enregistrer') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    sites: any[];
    topics: any[];
    currentDate: string;
    stats: {
        total_topics: number;
        scheduled: number;
        published: number;
        draft: number;
    };
    filters: any;
}>();

// État réactif
const showAddModal = ref(false);
const showGenerateModal = ref(false);
const editingTopic = ref(null);
const saving = ref(false);
const generatingTopics = ref(false);
const generatingArticles = ref({});
const selectedMonth = ref(props.currentDate);

// Filtres
const filters = reactive({
    site_id: props.filters.site_id || '',
    status: props.filters.status || '',
    language: props.filters.language || '',
});

// Formulaire de topic
const topicForm = reactive({
    site_id: '',
    title: '',
    description: '',
    keywords: [] as string[],
    categories: [] as string[],
    language_code: '',
    priority: '3',
    scheduled_date: '',
    scheduled_time: '09:00',
    status: 'draft',
    editorial_notes: '',
});

const keywordsString = ref('');

// Formulaire de génération IA
const generateForm = reactive({
    site_id: '',
    language_code: '',
    count: 10,
    focus_area: '',
});

// Watchers
watch(keywordsString, (value) => {
    topicForm.keywords = value ? value.split(',').map(k => k.trim()).filter(k => k) : [];
});

// Méthodes
function formatMonthYear(monthStr: string): string {
    const [year, month] = monthStr.split('-');
    const date = new Date(parseInt(year), parseInt(month) - 1);
    return date.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long' });
}

function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { 
        weekday: 'short', 
        day: 'numeric', 
        month: 'short' 
    });
}

function formatTime(timeStr: string): string {
    return timeStr ? timeStr.substring(0, 5) : '';
}

function getStatusLabel(status: string): string {
    const labels = {
        draft: 'Brouillon',
        scheduled: 'Programmé',
        published: 'Publié',
        cancelled: 'Annulé',
    };
    return labels[status] || status;
}

function getStatusBadgeClass(status: string): string {
    const classes = {
        draft: 'bg-gray-100 text-gray-800',
        scheduled: 'bg-blue-100 text-blue-800',
        published: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return classes[status] || classes.draft;
}

function changeMonth() {
    router.get('/topics', {
        date: selectedMonth.value,
        ...filters,
    });
}

function applyFilters() {
    router.get('/topics', {
        date: selectedMonth.value,
        ...filters,
    });
}

function editTopic(topic: any) {
    editingTopic.value = topic;
    
    // Pré-remplir le formulaire
    topicForm.site_id = topic.site.id.toString();
    topicForm.title = topic.title;
    topicForm.description = topic.description || '';
    topicForm.language_code = topic.language_code;
    topicForm.priority = topic.priority.toString();
    topicForm.scheduled_date = topic.scheduled_date || '';
    topicForm.scheduled_time = topic.scheduled_time || '09:00';
    topicForm.status = topic.status;
    topicForm.editorial_notes = topic.editorial_notes || '';

    keywordsString.value = topic.keywords.join(', ');

    showAddModal.value = true;
}

function scheduleTopic(topic: any) {
    const today = new Date().toISOString().split('T')[0];
    topicForm.scheduled_date = today;
    topicForm.scheduled_time = '09:00';
    editTopic(topic);
}

function deleteTopic(topic: any) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce topic ?')) return;
    
    useForm({}).delete(`/topics/${topic.id}`, {
        onSuccess: () => {
            router.reload();
        }
    });
}

function saveTopic() {
    saving.value = true;
    const form = useForm(topicForm);
    
    if (editingTopic.value) {
        form.put(`/topics/${editingTopic.value.id}`, {
            onSuccess: () => {
                closeModal();
            },
            onFinish: () => {
                saving.value = false;
            }
        });
    } else {
        form.post('/topics', {
            onSuccess: () => {
                closeModal();
            },
            onFinish: () => {
                saving.value = false;
            }
        });
    }
}

function generateTopics() {
    if (!generateForm.site_id || !generateForm.language_code) return;
    
    generatingTopics.value = true;
    
    useForm({
        site_id: generateForm.site_id,
        language_code: generateForm.language_code,
        count: generateForm.count || 10,
        focus_area: generateForm.focus_area || '',
    }).post('/topics/generate-ai', {
        onSuccess: () => {
            closeGenerateModal();
            window.location.reload(); // Recharger complètement la page
        },
        onError: (errors) => {
            console.error('Erreur lors de la génération des topics:', errors);
        },
        onFinish: () => {
            generatingTopics.value = false;
        }
    });
}

function closeGenerateModal() {
    showGenerateModal.value = false;
    Object.assign(generateForm, {
        site_id: '',
        language_code: '',
        count: 10,
        focus_area: '',
    });
}

function closeModal() {
    showAddModal.value = false;
    editingTopic.value = null;
    
    Object.assign(topicForm, {
        site_id: '',
        title: '',
        description: '',
        keywords: [],
        categories: [],
        language_code: '',
        priority: '3',
        scheduled_date: '',
        scheduled_time: '09:00',
        status: 'draft',
        editorial_notes: '',
    });
    keywordsString.value = '';
}

/**
 * Génère un article directement depuis un topic
 */
function generateArticleFromTopic(topic: any) {
    const action = topic.article_id ? 'regénérer' : 'générer';
    const confirmMessage = topic.article_id 
        ? `Voulez-vous regénérer un nouvel article avec l'IA pour le topic "${topic.title}" ? Cela créera un nouvel article.`
        : `Voulez-vous générer un article avec l'IA pour le topic "${topic.title}" ?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Marquer ce topic comme en cours de génération
    generatingArticles.value[topic.id] = true;
    
    useForm({}).post(`/topics/${topic.id}/generate-article`, {
        onSuccess: (response) => {
            // Extraire les données de l'article depuis la session flash
            const articleData = response.props.flash?.article_data;
            
            let successMessage = `✅ Article ${action === 'regénérer' ? 'regénéré' : 'généré'} avec succès !`;
            
            if (articleData) {
                successMessage += `\n📝 Titre: ${articleData.title}\n📊 ${articleData.word_count} mots (≈${articleData.reading_time} min de lecture)`;
                
                // Proposer d'ouvrir l'article pour édition
                if (confirm(successMessage + '\n\nVoulez-vous ouvrir l\'article pour l\'éditer ?')) {
                    window.open(articleData.edit_url, '_blank');
                }
            } else {
                alert(successMessage);
            }
            
            // Recharger la page pour voir les changements
            router.reload();
        },
        onError: (errors) => {
            console.error('❌ Erreur lors de la génération de l\'article:', errors);
            
            // Afficher le message d'erreur
            let errorMessage = `Erreur lors de la ${action}ation de l'article`;
            if (errors.message) {
                errorMessage = errors.message;
            } else if (typeof errors === 'string') {
                errorMessage = errors;
            } else if (errors.error) {
                errorMessage = errors.error;
            }
            
            alert('❌ ' + errorMessage);
        },
        onFinish: () => {
            // Enlever l'état de génération
            generatingArticles.value[topic.id] = false;
        }
    });
}
</script>
