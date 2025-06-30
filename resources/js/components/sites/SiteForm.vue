<template>
    <Dialog :open="show" @update:open="emit('close')">
        <DialogContent class="custom-dialog-content">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit Site' : 'Add New Site' }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Update your site information.' : 'Create a new site to manage.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">Site Name</Label>
                    <Input id="name" v-model="form.name" type="text" required :disabled="form.processing" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="url">Site URL</Label>
                    <Input id="url" v-model="form.url" type="url" required :disabled="form.processing" />
                    <InputError :message="form.errors.url" />
                </div>

                <div class="space-y-2">
                    <Label for="platform_type">Plateforme</Label>
                    <select
                        id="platform_type"
                        v-model="form.platform_type"
                        :disabled="form.processing"
                        class="w-full rounded border px-3 py-2"
                        required
                    >
                        <option value="" disabled>Choisir une plateforme</option>
                        <option value="laravel">Laravel</option>
                        <option value="wordpress">WordPress</option>
                        <option value="prestashop">PrestaShop</option>
                    </select>
                    <InputError :message="form.errors.platform_type" />
                </div>

                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        :disabled="form.processing"
                        placeholder="Describe the purpose of this site..."
                        class="min-h-[100px]"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="space-y-2">
                    <Label for="primary_color">Primary Color</Label>
                    <div class="flex items-center gap-2">
                        <Input id="primary_color" v-model="form.primary_color" type="color" :disabled="form.processing" class="h-12 w-12 p-1" />
                        <Input v-model="form.primary_color" type="text" :disabled="form.processing" class="flex-1" placeholder="#4E8D44" />
                    </div>
                    <InputError :message="form.errors.primary_color" />
                </div>

                <div class="space-y-2">
                    <Label for="secondary_color">Secondary Color</Label>
                    <div class="flex items-center gap-2">
                        <Input id="secondary_color" v-model="form.secondary_color" type="color" :disabled="form.processing" class="h-12 w-12 p-1" />
                        <Input v-model="form.secondary_color" type="text" :disabled="form.processing" class="flex-1" placeholder="#6b7280" />
                    </div>
                    <InputError :message="form.errors.secondary_color" />
                </div>

                <div class="space-y-2">
                    <Label for="accent_color">Accent Color</Label>
                    <div class="flex items-center gap-2">
                        <Input id="accent_color" v-model="form.accent_color" type="color" :disabled="form.processing" class="h-12 w-12 p-1" />
                        <Input v-model="form.accent_color" type="text" :disabled="form.processing" class="flex-1" placeholder="#10b981" />
                    </div>
                    <InputError :message="form.errors.accent_color" />
                </div>

                <div class="space-y-2">
                    <Label for="status">Status</Label>
                    <Select v-model="form.status" :disabled="form.processing">
                        <SelectTrigger>
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>

                <div class="space-y-2">
                    <Label>Languages</Label>
                    <MultiSelect
                        v-model="selectedLanguageValues"
                        :options="languageOptions"
                        placeholder="Sélectionnez les langues..."
                        :disabled="form.processing"
                        class="w-full"
                    />
                    <InputError :message="form.errors.languages" />
                </div>

                <!-- Nouvelles options d'automatisation -->
                <div class="space-y-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <h3 class="text-lg font-semibold text-blue-900">🤖 Options d'automatisation</h3>
                    
                    <!-- Suppression après synchronisation -->
                    <div class="flex items-center space-x-2">
                        <input
                            id="auto_delete_after_sync"
                            v-model="form.auto_delete_after_sync"
                            type="checkbox"
                            :disabled="form.processing"
                            class="rounded border-gray-300 text-blue-600 focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                        />
                        <Label for="auto_delete_after_sync" class="text-sm">
                            Supprimer automatiquement les articles après synchronisation
                        </Label>
                    </div>
                    <p class="text-xs text-blue-600 ml-6">
                        Les articles seront supprimés de la base de données locale après avoir été synchronisés avec le site distant
                    </p>

                    <!-- Génération automatique d'articles -->
                    <div class="space-y-3">
                        <div class="flex items-center space-x-2">
                            <input
                                id="auto_article_generation"
                                v-model="form.auto_article_generation"
                                type="checkbox"
                                :disabled="form.processing"
                                class="rounded border-gray-300 text-blue-600 focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                            />
                            <Label for="auto_article_generation" class="text-sm">
                                Activer la génération automatique d'articles
                            </Label>
                        </div>

                        <!-- Configuration du planning (affiché seulement si la génération auto est activée) -->
                        <div v-if="form.auto_article_generation" class="ml-6 space-y-3 rounded border border-emerald-200 bg-emerald-50 p-3">
                            <h4 class="font-medium text-emerald-800">Configuration du planning</h4>
                            
                            <!-- Jours de la semaine -->
                            <div class="space-y-2">
                                <Label class="text-sm text-emerald-700">Jours de génération :</Label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label v-for="day in weekDays" :key="day.value" class="flex items-center space-x-2">
                                        <input
                                            v-model="form.auto_schedule_days"
                                            :value="day.value"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-emerald-600"
                                        />
                                        <span class="text-sm">{{ day.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Heure -->
                            <div class="space-y-2">
                                <Label for="auto_schedule_time" class="text-sm text-emerald-700">Heure de génération :</Label>
                                <Input
                                    id="auto_schedule_time"
                                    v-model="form.auto_schedule_time"
                                    type="time"
                                    :disabled="form.processing"
                                    class="w-32"
                                />
                            </div>

                            <!-- Langue par défaut -->
                            <div class="space-y-2">
                                <Label for="auto_content_language" class="text-sm text-emerald-700">Langue par défaut :</Label>
                                <Select v-model="form.auto_content_language" :disabled="form.processing">
                                    <SelectTrigger class="w-full">
                                        <SelectValue placeholder="Choisir une langue" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="lang in availableLanguages" :key="lang.id" :value="lang.code || lang.id.toString()">
                                            {{ lang.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Nombre de mots -->
                            <div class="space-y-2">
                                <Label for="auto_word_count" class="text-sm text-emerald-700">Nombre de mots par article :</Label>
                                <Input
                                    id="auto_word_count"
                                    v-model.number="form.auto_word_count"
                                    type="number"
                                    min="100"
                                    max="5000"
                                    :disabled="form.processing"
                                    class="w-32"
                                    placeholder="800"
                                />
                            </div>

                            <!-- Directives de contenu -->
                            <div class="space-y-2">
                                <Label for="auto_content_guidelines" class="text-sm text-emerald-700">Directives de contenu :</Label>
                                <Textarea
                                    id="auto_content_guidelines"
                                    v-model="form.auto_content_guidelines"
                                    :disabled="form.processing"
                                    placeholder="Décrivez le type de contenu que vous souhaitez générer, le ton, les sujets à aborder, etc."
                                    class="min-h-[80px]"
                                />
                                <p class="text-xs text-emerald-600">
                                    Ces directives aideront l'IA à générer du contenu en accord avec votre site
                                </p>
                            </div>

                            <!-- Gestion des sujets pour la génération automatique -->
                            <div v-if="form.auto_article_generation" class="space-y-4 border-t border-emerald-200 pt-4">
                                <h4 class="font-medium text-emerald-800">📝 Gestion des sujets d'articles</h4>
                                <p class="text-xs text-emerald-600">
                                    Définissez les sujets et mots-clés que l'IA utilisera pour générer automatiquement du contenu
                                </p>

                                <!-- Sélecteur de langue pour les sujets -->
                                <div class="flex items-center gap-3">
                                    <Label class="text-sm text-emerald-700">Langue des sujets :</Label>
                                    <Select v-model="topicsLanguage" @update:model-value="loadSiteTopics">
                                        <SelectTrigger class="w-48">
                                            <SelectValue placeholder="Choisir une langue" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="lang in availableLanguages" :key="lang.id" :value="lang.code || lang.id.toString()">
                                                {{ lang.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="loadSiteTopics"
                                        :disabled="!topicsLanguage || loadingTopics"
                                    >
                                        {{ loadingTopics ? '🔄' : '🔍' }} Charger
                                    </Button>
                                </div>

                                <!-- Configuration du nombre de topics à générer -->
                                <div class="flex items-center gap-3">
                                    <Label class="text-sm text-emerald-700">Nombre de sujets à générer :</Label>
                                    <Input
                                        v-model.number="topicsToGenerate"
                                        type="number"
                                        min="1"
                                        max="50"
                                        :disabled="generatingTopics"
                                        class="w-20"
                                        placeholder="20"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="generateTopicsWithAI"
                                        :disabled="!topicsLanguage || generatingTopics || !topicsToGenerate"
                                    >
                                        {{ generatingTopics ? '🔄 Génération...' : `🤖 Générer ${topicsToGenerate || 20} sujets avec IA` }}
                                    </Button>
                                </div>

                                <!-- Actions rapides -->
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="showTopicModal = true"
                                    >
                                        ➕ Ajouter manuellement
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="showImportModal = true"
                                    >
                                        📥 Importer CSV/JSON
                                    </Button>
                                </div>

                                <!-- Liste des sujets -->
                                <div v-if="siteTopics.length > 0" class="space-y-2 max-h-48 overflow-y-auto">
                                    <div 
                                        v-for="topic in siteTopics" 
                                        :key="topic.id"
                                        class="flex items-center justify-between p-2 bg-white rounded border text-sm"
                                    >
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium truncate">{{ topic.title }}</span>
                                                <span class="text-xs px-1 py-0.5 rounded bg-emerald-100 text-emerald-700">
                                                    P{{ topic.priority }}
                                                </span>
                                                <span v-if="!topic.is_active" class="text-xs px-1 py-0.5 rounded bg-gray-100 text-gray-600">
                                                    Inactif
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-600 mt-1">
                                                {{ topic.keywords.join(', ') }}
                                            </div>
                                            <div v-if="topic.usage_count > 0" class="text-xs text-blue-600 mt-1">
                                                Utilisé {{ topic.usage_count }} fois
                                            </div>
                                        </div>
                                        <div class="flex gap-1 ml-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="editTopic(topic)"
                                            >
                                                ✏️
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="deleteTopic(topic.id)"
                                                class="text-red-600 hover:text-red-700"
                                            >
                                                🗑️
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="topicsLanguage && !loadingTopics" class="text-center p-4 text-gray-600">
                                    <p class="text-sm">Aucun sujet défini pour cette langue</p>
                                    <p class="text-xs">Utilisez l'IA ou ajoutez manuellement des sujets</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="apiKey || webhookUrl" class="mb-4 space-y-2">
                    <div v-if="apiKey">
                        <Label>API Key</Label>
                        <div class="flex items-center gap-2">
                            <Input :value="apiKey" readonly class="font-mono select-all" />
                            <Button type="button" @click="copyToClipboard(apiKey)" size="sm" variant="outline">Copier</Button>
                        </div>
                    </div>
                    <div v-if="webhookUrl">
                        <Label>Webhook URL</Label>
                        <div class="flex items-center gap-2">
                            <Input :value="webhookUrl" readonly class="font-mono select-all" />
                            <Button type="button" @click="copyToClipboard(webhookUrl)" size="sm" variant="outline">Copier</Button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="emit('close')"> Cancel </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Update' : 'Create' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import MultiSelect from '@/components/ui/MultiSelect.vue';
import { useRoutes } from '@/composables/useRoutes';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch, defineComponent, h } from 'vue';

interface Language {
    id: number;
    name: string;
    flag_url: string;
}

interface LanguageOption {
    value: string;
    label: string;
    icon?: any;
}

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    site: {
        type: Object,
        default: () => ({}),
    },
    availableLanguages: {
        type: Array as () => Language[],
        default: () => [],
    },
    apiKey: {
        type: String,
        default: '',
    },
    webhookUrl: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const { siteRoutes } = useRoutes();

// Créer un composant pour afficher le drapeau
const FlagIcon = defineComponent({
    props: {
        flagUrl: String,
        alt: String,
    },
    setup(props) {
        return () => h('img', {
            src: props.flagUrl,
            alt: props.alt,
            class: 'h-4 w-4 rounded-full border object-cover',
        });
    },
});

const languageOptions = computed<LanguageOption[]>(() => {
    return props.availableLanguages.map((lang) => ({
        value: lang.id.toString(),
        label: lang.name,
        icon: defineComponent({
            setup() {
                return () => h(FlagIcon, { flagUrl: lang.flag_url, alt: lang.name });
            },
        }),
    }));
});

const selectedLanguageValues = ref<string[]>([]);

// Jours de la semaine pour le sélecteur
const weekDays = [
    { value: 'monday', label: 'Lundi' },
    { value: 'tuesday', label: 'Mardi' },
    { value: 'wednesday', label: 'Mercredi' },
    { value: 'thursday', label: 'Jeudi' },
    { value: 'friday', label: 'Vendredi' },
    { value: 'saturday', label: 'Samedi' },
    { value: 'sunday', label: 'Dimanche' },
];

// Variables pour la gestion des sujets
const siteTopics = ref([]);
const topicsLanguage = ref('');
const topicsToGenerate = ref(20);
const loadingTopics = ref(false);
const generatingTopics = ref(false);
const showTopicModal = ref(false);
const showImportModal = ref(false);
const editingTopic = ref(null);

const form = useForm({
    name: props.site?.name || '',
    url: props.site?.domain || '',
    platform_type: props.site?.platform_type || '',
    status: props.site?.is_active ? 'active' : 'inactive',
    description: props.site?.description || '',
    primary_color: props.site?.primary_color || '#4E8D44',
    secondary_color: props.site?.secondary_color || '#6b7280',
    accent_color: props.site?.accent_color || '#10b981',
    languages: [] as number[],
    // Nouveaux champs d'automatisation
    auto_delete_after_sync: props.site?.auto_delete_after_sync || false,
    auto_article_generation: props.site?.auto_article_generation || false,
    auto_schedule_days: props.site?.auto_schedule?.days || [] as string[],
    auto_schedule_time: props.site?.auto_schedule?.time || '09:00',
    auto_content_guidelines: props.site?.auto_content_guidelines || '',
    auto_content_language: props.site?.auto_content_language || '',
    auto_word_count: props.site?.auto_word_count || 800,
});

// Watch pour synchroniser les changements du multiselect avec le form
watch(
    selectedLanguageValues,
    (newValues) => {
        form.languages = newValues.map((value) => parseInt(value));
    },
    { deep: true },
);

watch(
    () => props.site,
    (newSite) => {
        if (newSite) {
            form.name = newSite.name;
            form.url = newSite.domain;
            form.platform_type = newSite.platform_type || '';
            form.status = newSite.is_active ? 'active' : 'inactive';
            form.description = newSite.description || '';
            form.primary_color = newSite.primary_color || '#4E8D44';
            form.secondary_color = newSite.secondary_color || '#6b7280';
            form.accent_color = newSite.accent_color || '#10b981';
            
            // Nouveaux champs d'automatisation
            form.auto_delete_after_sync = newSite.auto_delete_after_sync || false;
            form.auto_article_generation = newSite.auto_article_generation || false;
            form.auto_schedule_days = newSite.auto_schedule?.days || [];
            form.auto_schedule_time = newSite.auto_schedule?.time || '09:00';
            form.auto_content_guidelines = newSite.auto_content_guidelines || '';
            form.auto_content_language = newSite.auto_content_language || '';
            form.auto_word_count = newSite.auto_word_count || 800;
            
            // Gérer les langues sélectionnées
            if (newSite.languages) {
                selectedLanguageValues.value = newSite.languages.map((l: any) => (l.id || l.value || l).toString());
                form.languages = newSite.languages.map((l: any) => l.id || l.value || l);
            } else {
                selectedLanguageValues.value = [];
                form.languages = [];
            }
        } else {
            form.name = '';
            form.url = '';
            form.platform_type = '';
            form.status = 'active';
            form.description = '';
            form.primary_color = '#4E8D44';
            form.secondary_color = '#6b7280';
            form.accent_color = '#10b981';
            // Réinitialiser les nouveaux champs
            form.auto_delete_after_sync = false;
            form.auto_article_generation = false;
            form.auto_schedule_days = [];
            form.auto_schedule_time = '09:00';
            form.auto_content_guidelines = '';
            form.auto_content_language = '';
            form.auto_word_count = 800;
            selectedLanguageValues.value = [];
            form.languages = [];
        }
    },
    { immediate: true, deep: true },
);

watch(
    () => props.show,
    (newValue) => {
        if (!newValue) {
            form.reset();
            selectedLanguageValues.value = [];
        }
    },
);

const isEditing = computed(() => !!props.site?.id);

const submit = () => {
    // Préparer les données d'automatisation avant l'envoi
    const formData = {
        ...form.data(),
        auto_schedule: form.auto_article_generation ? {
            days: form.auto_schedule_days,
            time: form.auto_schedule_time,
        } : null,
    };
    
    // Supprimer les champs temporaires utilisés pour l'interface
    delete formData.auto_schedule_days;
    delete formData.auto_schedule_time;
    
    if (isEditing.value) {
        form.transform(() => formData).put(siteRoutes.update(props.site.id));
    } else {
        form.transform(() => formData).post(siteRoutes.store());
    }
};

watch(
    () => form.wasSuccessful,
    (success) => {
        if (success) {
            emit('close');
            form.reset();
            selectedLanguageValues.value = [];
        }
    },
);

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
}

// Méthodes pour la gestion des sujets
async function loadSiteTopics() {
    if (!props.site?.id || !topicsLanguage.value) return;
    
    loadingTopics.value = true;
    try {
        const response = await fetch(`/sites/${props.site.id}/topics?language=${topicsLanguage.value}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            siteTopics.value = data.topics || [];
        }
    } catch (error) {
        console.error('Erreur lors du chargement des sujets:', error);
    } finally {
        loadingTopics.value = false;
    }
}

function generateTopicsWithAI() {
    if (!props.site?.id || !topicsLanguage.value || !topicsToGenerate.value) return;
    
    generatingTopics.value = true;
    
    // Utiliser Inertia pour faire la requête
    useForm({
        language_code: topicsLanguage.value,
        count: topicsToGenerate.value,
        focus_area: form.auto_content_guidelines || '',
    }).post(`/sites/${props.site.id}/topics/generate-ai`, {
        onSuccess: (page) => {
            // Recharger les sujets après la génération
            loadSiteTopics();
            generatingTopics.value = false;
        },
        onError: (errors) => {
            console.error('Erreur lors de la génération des sujets:', errors);
            generatingTopics.value = false;
        },
        onFinish: () => {
            generatingTopics.value = false;
        }
    });
}

function deleteTopic(topicId: number) {
    if (!props.site?.id || !confirm('Êtes-vous sûr de vouloir supprimer ce sujet ?')) return;
    
    useForm({}).delete(`/sites/${props.site.id}/topics/${topicId}`, {
        onSuccess: () => {
            // Supprimer le sujet de la liste locale
            siteTopics.value = siteTopics.value.filter(topic => topic.id !== topicId);
        },
        onError: (errors) => {
            console.error('Erreur lors de la suppression du sujet:', errors);
        }
    });
}

function editTopic(topic: any) {
    editingTopic.value = { ...topic };
    showTopicModal.value = true;
}

// Watcher pour charger les sujets quand on change de langue
watch(topicsLanguage, () => {
    if (topicsLanguage.value && props.site?.id) {
        loadSiteTopics();
    }
});

// Charger les sujets quand le site change
watch(() => props.site?.id, () => {
    if (props.site?.id && topicsLanguage.value) {
        loadSiteTopics();
    }
});
</script>

<style>
.custom-dialog-content {
    max-width: 700px;
    width: 95vw;
    max-height: 80vh;
    min-height: unset;
    overflow-y: auto;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
    padding: 2.5rem 2rem;
    background: #fff;
}

@media (max-width: 800px) {
    .custom-dialog-content {
        max-width: 98vw;
        padding: 1.2rem 0.5rem;
    }
}
</style>
