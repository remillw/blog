<template>
    <div class="mx-auto max-w-4xl">
        <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-2">
                <Label for="site_id">Site</Label>
                <MultiSelect
                    v-model="selectedSiteValues"
                    :options="siteOptions"
                    placeholder="Sélectionner un site..."
                    :disabled="form.processing"
                    :max-selections="1"
                    class="w-full"
                />
                <InputError :message="form.errors.site_id" />
            </div>
            <div v-if="siteColors.primary_color" class="space-y-2">
                <Label class="text-sm font-medium">Couleurs du site</Label>
                <div class="bg-muted/30 flex items-center gap-6 rounded-lg border p-4">
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-lg border shadow-sm" :style="{ backgroundColor: siteColors.primary_color }"></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium">Primary</p>
                            <p class="font-mono text-xs">{{ siteColors.primary_color }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-lg border shadow-sm" :style="{ backgroundColor: siteColors.secondary_color }"></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium">Secondary</p>
                            <p class="font-mono text-xs">{{ siteColors.secondary_color }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-lg border shadow-sm" :style="{ backgroundColor: siteColors.accent_color }"></div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium">Accent</p>
                            <p class="font-mono text-xs">{{ siteColors.accent_color }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sélecteur de langue pour navigation multi-langues -->
            <div
                v-if="articleVersions.size > 1 || (selectedSiteValues.length > 0 && siteLanguages.length > 0)"
                class="rounded-lg border border-blue-200 bg-blue-50 p-4"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <Label class="text-sm font-medium text-blue-800">Langue de l'article</Label>
                        <p class="text-xs text-blue-600">Sélectionnez la langue pour voir/éditer l'article</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Select v-model="currentLanguage" @update:model-value="switchLanguage">
                            <SelectTrigger class="w-48">
                                <SelectValue placeholder="Choisir la langue" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="lang in availableLanguagesForSelection" :key="lang.code" :value="lang.code">
                                    {{ lang.flag }} {{ lang.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div class="text-xs text-blue-600">{{ articleVersions.size }} version(s) disponible(s)</div>
                    </div>
                </div>
            </div>

            <!-- Section Génération IA Nouvelle Version -->
            <div class="space-y-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <h3 class="text-lg font-semibold text-emerald-900">🤖 Génération d'article par IA</h3>

                <div class="space-y-3">
                    <div v-if="selectedSiteValues.length === 0" class="rounded-md border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800">
                        ⚠️ Sélectionnez d'abord un site pour utiliser l'IA (contexte nécessaire)
                    </div>

                    <div v-else class="space-y-3">
                        <!-- Sélection des langues de génération -->
                        <div>
                            <Label class="mb-2 block text-sm font-medium text-emerald-800">Langues de génération</Label>
                            <MultiSelect
                                v-model="selectedGenerationLanguages"
                                :options="siteLanguageOptions"
                                placeholder="Choisir les langues pour la génération..."
                                :disabled="generatingWithAI || siteLanguages.length === 0"
                                class="w-full"
                            />
                            <div v-if="siteLanguages.length === 0" class="mt-1 text-xs text-emerald-600">Aucune langue configurée pour ce site</div>
                        </div>

                        <!-- Prompt de génération -->
                        <div>
                            <Label class="mb-2 block text-sm font-medium text-emerald-800">Sujet de l'article</Label>
                            <Input
                                v-model="aiPrompt"
                                placeholder="Ex: Guide complet du jardinage urbain pour débutants..."
                                :disabled="generatingWithAI"
                            />
                        </div>

                        <!-- Bouton de génération -->
                        <div class="flex items-center gap-3">
                            <Button
                                type="button"
                                @click="generateMultiLanguageArticle"
                                :disabled="!aiPrompt.trim() || selectedGenerationLanguages.length === 0 || generatingWithAI"
                                class="flex-1"
                            >
                                {{ generatingWithAI ? '🔄 Génération en cours...' : `🪄 Générer en ${selectedGenerationLanguages.length} langue(s)` }}
                            </Button>
                        </div>

                        <div v-if="selectedGenerationLanguages.length > 0" class="text-xs text-emerald-600">
                            Génération dans : {{ selectedGenerationLanguages.map((lang) => getLanguageName(lang)).join(', ') }}
                        </div>
                    </div>

                    <p class="text-xs text-emerald-600">
                        L'IA créera des articles complets avec titre, contenu structuré, méta-données SEO et suggestions de catégories.
                    </p>
                </div>
            </div>

            <!-- Section Traduction Simplifiée -->
            <div v-if="hasContent" class="space-y-4 rounded-lg border border-purple-200 bg-purple-50 p-4">
                <h3 class="text-lg font-semibold text-purple-900">🌍 Traduction automatique</h3>

                <div class="space-y-3">
                    <div v-if="selectedSiteValues.length === 0" class="text-sm text-purple-600 italic">
                        Sélectionnez d'abord un site pour voir les langues disponibles
                    </div>

                    <div v-else class="space-y-3">
                        <!-- Sélection des langues de traduction -->
                        <div>
                            <Label class="mb-2 block text-sm font-medium text-purple-800">Langues de traduction</Label>
                            <MultiSelect
                                v-model="selectedTranslationLanguages"
                                :options="siteLanguageOptions"
                                placeholder="Choisir les langues de traduction..."
                                :disabled="translating || siteLanguages.length === 0"
                                class="w-full"
                            />
                            <div v-if="siteLanguages.length === 0" class="mt-1 text-xs text-purple-600">Aucune langue configurée pour ce site</div>
                        </div>

                        <!-- Bouton de traduction -->
                        <div class="flex items-center gap-3">
                            <Button
                                type="button"
                                @click="translateToMultipleLanguages"
                                :disabled="translating || selectedTranslationLanguages.length === 0"
                                class="flex-1"
                            >
                                {{ translating ? '🔄 Traduction...' : `🌍 Traduire vers ${selectedTranslationLanguages.length} langue(s)` }}
                            </Button>
                        </div>

                        <div v-if="selectedTranslationLanguages.length > 0" class="text-xs text-purple-600">
                            Traduction vers : {{ selectedTranslationLanguages.map((lang) => getLanguageName(lang)).join(', ') }}
                        </div>
                    </div>

                    <!-- Résultats de traduction -->
                    <div v-if="translationResults.length > 0" class="mt-3">
                        <Label class="mb-2 block text-sm font-medium text-purple-800">Traductions créées :</Label>
                        <div class="space-y-1">
                            <div v-for="result in translationResults" :key="result.language" class="flex items-center gap-2 text-xs">
                                <span class="text-green-600">✓</span>
                                <span>{{ getLanguageName(result.language) }}</span>
                                <Button size="sm" variant="ghost" @click="loadTranslation(result)">📝 Charger</Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="form.title" type="text" required :disabled="form.processing" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="space-y-2">
                        <Label for="excerpt">Excerpt</Label>
                        <Textarea id="excerpt" v-model="form.excerpt" :disabled="form.processing" rows="3" />
                        <InputError :message="form.errors.excerpt" />
                    </div>

                    <div class="space-y-2">
                        <Label for="cover_image">Cover Image</Label>
                        <div class="space-y-3">
                            <!-- Prévisualisation de l'image actuelle -->
                            <div v-if="currentCoverImageUrl" class="relative">
                                <img :src="currentCoverImageUrl" alt="Cover image preview" class="h-32 w-full rounded-lg border object-cover" />
                                <Button type="button" variant="destructive" size="sm" class="absolute top-2 right-2" @click="removeCoverImage">
                                    ✕
                                </Button>
                            </div>

                            <!-- Upload d'image -->
                            <div class="flex items-center gap-3">
                                <input ref="coverImageInput" type="file" accept="image/*" class="hidden" @change="handleCoverImageUpload" />
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="coverImageInput?.click()"
                                    :disabled="form.processing || uploadingCoverImage"
                                >
                                    {{ uploadingCoverImage ? 'Uploading...' : 'Choose Image' }}
                                </Button>
                                <span class="text-sm text-gray-500">Max 2MB, JPG/PNG</span>
                            </div>
                        </div>
                        <InputError :message="form.errors.cover_image" />
                    </div>

                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="form.status" :disabled="form.processing">
                            <SelectTrigger>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="published">Published</SelectItem>
                                <SelectItem value="scheduled">Scheduled</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div v-if="form.status === 'scheduled'" class="space-y-2">
                        <Label for="scheduled_at">Schedule Date</Label>
                        <Input id="scheduled_at" v-model="form.scheduled_at" type="datetime-local" :disabled="form.processing" />
                        <InputError :message="form.errors.scheduled_at" />
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label for="categories">Categories</Label>
                            <div
                                v-if="selectedSiteValues.length === 0"
                                class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600"
                            >
                                Sélectionnez d'abord un site pour voir les catégories disponibles
                            </div>
                            <MultiSelect
                                v-else
                                v-model="selectedCategoryValues"
                                :options="categoryOptions"
                                placeholder="Sélectionnez les catégories..."
                                :disabled="form.processing || availableCategories.length === 0"
                                class="w-full"
                            />
                            <div v-if="selectedSiteValues.length > 0 && availableCategories.length === 0" class="mt-1 text-sm text-gray-500">
                                Aucune catégorie disponible pour ce site
                            </div>
                            <InputError :message="form.errors.categories" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="meta_title">Meta Title</Label>
                        <Input id="meta_title" v-model="form.meta_title" type="text" :disabled="form.processing" />
                        <InputError :message="form.errors.meta_title" />
                    </div>

                    <div class="space-y-2">
                        <Label for="meta_description">Meta Description</Label>
                        <Textarea id="meta_description" v-model="form.meta_description" :disabled="form.processing" rows="3" />
                        <InputError :message="form.errors.meta_description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="meta_keywords">Meta Keywords</Label>
                        <Input
                            id="meta_keywords"
                            v-model="form.meta_keywords"
                            type="text"
                            :disabled="form.processing"
                            placeholder="Separate keywords with commas"
                        />
                        <InputError :message="form.errors.meta_keywords" />
                    </div>

                    <div class="space-y-2">
                        <Label for="canonical_url">Canonical URL</Label>
                        <Input id="canonical_url" v-model="form.canonical_url" type="url" :disabled="form.processing" />
                        <InputError :message="form.errors.canonical_url" />
                    </div>

                    <div class="space-y-2">
                        <Label for="author_name">Author Name</Label>
                        <Input id="author_name" v-model="form.author_name" type="text" :disabled="form.processing" />
                        <InputError :message="form.errors.author_name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="author_bio">Author Bio</Label>
                        <Textarea id="author_bio" v-model="form.author_bio" :disabled="form.processing" rows="3" />
                        <InputError :message="form.errors.author_bio" />
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <Label>Content</Label>
                <div class="rounded-lg border">
                    <EditorJS
                        :initial-content="form.content"
                        :site-colors="siteColors"
                        @update:content="handleContentUpdate"
                        :disabled="form.processing"
                        class="min-h-[400px]"
                    />
                </div>
                <InputError :message="form.errors.content" />
            </div>

            <div class="flex justify-end space-x-2">
                <Button type="submit" :disabled="form.processing">
                    {{ isEditing ? 'Update' : 'Create' }}
                </Button>
            </div>
        </form>

        <!-- Toast notification system -->
        <div
            v-if="notification.show"
            class="animate-in fade-in slide-in-from-bottom-5 fixed right-4 bottom-4 z-[9999] flex items-center gap-2 rounded-lg border bg-white p-4 shadow-lg transition-opacity"
            :class="notification.type === 'success' ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'"
        >
            <div
                class="flex h-8 w-8 items-center justify-center rounded-full"
                :class="notification.type === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
            >
                <CheckIcon v-if="notification.type === 'success'" class="h-5 w-5" />
                <XIcon v-else class="h-5 w-5" />
            </div>
            <div>
                <p class="font-medium" :class="notification.type === 'success' ? 'text-green-800' : 'text-red-800'">
                    {{ notification.title }}
                </p>
                <p class="text-sm" :class="notification.type === 'success' ? 'text-green-700' : 'text-red-700'">
                    {{ notification.message }}
                </p>
            </div>
            <Button variant="ghost" size="icon" class="ml-auto h-6 w-6 p-0" @click="notification.show = false">
                <XIcon class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>

<script setup lang="ts">
import EditorJS from '@/components/Editor/EditorJS.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MultiSelect from '@/components/ui/MultiSelect.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useEditorJSConverter } from '@/composables/useEditorJSConverter';
import { useRoutes } from '@/composables/useRoutes';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckIcon, XIcon } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface Article {
    id?: number;
    title: string;
    excerpt: string;
    content: string;
    content_html: string;
    cover_image?: string;
    status: string;
    scheduled_at?: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    canonical_url: string;
    author_name: string;
    author_bio: string;
    site_id?: number;
    categories?: Category[];
}

const props = defineProps<{
    article?: Article;
    categories: Category[];
    sites: { id: number; name: string }[];
}>();

const emit = defineEmits(['close']);

const { articleRoutes, siteRoutes } = useRoutes();
const { convertForWebhook, convertHTMLToEditorJS } = useEditorJSConverter();

// Déclarations des refs AVANT les watchers
const siteColors = ref({
    primary_color: '',
    secondary_color: '',
    accent_color: '',
});

const selectedSiteValues = ref<string[]>([]);
const selectedCategoryValues = ref<string[]>([]);
const availableCategories = ref<Category[]>([]);
const uploadingCoverImage = ref(false);
const currentCoverImageUrl = ref<string>('');
const coverImageInput = ref<HTMLInputElement>();

// Variables pour l'IA et multi-langues
const aiPrompt = ref<string>('');
const generatingWithAI = ref<boolean>(false);
const translating = ref<boolean>(false);

// Nouvelles variables pour la séparation des fonctionnalités
const selectedTranslationLanguages = ref<string[]>([]);
const siteLanguages = ref<any[]>([]);
const translationResults = ref<any[]>([]);

// Nouvelles variables pour la génération multi-langues
const selectedGenerationLanguages = ref<string[]>([]);
const generationResults = ref<any[]>([]);

// Variables pour la gestion multi-langues en temps réel
const currentLanguage = ref<string>('fr');
const articleVersions = ref<Map<string, any>>(new Map());

// Notification system (comme dans SiteList.vue)
const notification = reactive({
    show: false,
    type: 'success' as 'success' | 'error',
    title: '',
    message: '',
    timeout: null as number | null,
});

const form = useForm({
    title: '',
    excerpt: '',
    content: '',
    content_html: '',
    cover_image: null as File | null,
    status: 'draft',
    scheduled_at: undefined as string | undefined,
    categories: [] as number[],
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    canonical_url: '',
    author_name: '',
    author_bio: '',
    site_id: '' as string,
});

// Computed properties
const categoryOptions = computed(() => {
    return availableCategories.value.map((c) => ({
        value: c.id.toString(),
        label: c.name,
    }));
});

const siteOptions = computed(() => {
    if (!Array.isArray(props.sites)) {
        return [];
    }
    const options = props.sites
        .filter((s) => s.id !== undefined && s.id !== null)
        .map((s) => ({
            value: String(s.id),
            label: s.name,
        }));
    return options;
});

const hasContent = computed(() => {
    return !!(form.title || form.excerpt || form.content);
});

// Computed properties pour les nouvelles fonctionnalités
const siteLanguageOptions = computed(() => {
    return siteLanguages.value.map((lang: any) => ({
        value: lang.code,
        label: `${lang.flag} ${lang.name}`,
    }));
});

// Langues disponibles pour la sélection dans le header
const availableLanguagesForSelection = computed(() => {
    const siteLangs = siteLanguages.value.map((lang: any) => ({
        code: lang.code,
        name: lang.name,
        flag: lang.flag,
    }));

    // Si on a des versions d'articles, inclure toutes les langues qui ont du contenu
    const versionsLangs = Array.from(articleVersions.value.keys()).map((code) => ({
        code,
        name: getLanguageName(code),
        flag: getLanguageFlag(code),
    }));

    // Combiner et dédupliquer
    const combined = [...siteLangs, ...versionsLangs];
    const unique = combined.filter((lang, index, self) => index === self.findIndex((l) => l.code === lang.code));

    return unique;
});

// Fonction pour obtenir le drapeau d'une langue
const getLanguageFlag = (code: string): string => {
    const flags: Record<string, string> = {
        fr: '🇫🇷',
        en: '🇬🇧',
        es: '🇪🇸',
        de: '🇩🇪',
        it: '🇮🇹',
        pt: '🇵🇹',
        nl: '🇳🇱',
        ru: '🇷🇺',
        ja: '🇯🇵',
        zh: '🇨🇳',
    };
    return flags[code] || '🌐';
};

// Function pour sauvegarder la version actuelle avant de changer
const saveCurrentVersion = () => {
    if (currentLanguage.value && (form.title || form.excerpt || form.content)) {
        articleVersions.value.set(currentLanguage.value, {
            title: form.title,
            excerpt: form.excerpt,
            content: form.content,
            content_html: form.content_html,
            meta_title: form.meta_title,
            meta_description: form.meta_description,
            meta_keywords: form.meta_keywords,
            canonical_url: form.canonical_url,
            author_name: form.author_name,
            author_bio: form.author_bio,
            categories: [...selectedCategoryValues.value],
        });

        console.log('💾 Saved version for language:', currentLanguage.value);
    }
};

// Fonction pour charger une version linguistique
const loadLanguageVersion = (languageCode: string) => {
    const version = articleVersions.value.get(languageCode);

    if (version) {
        // Charger les données de cette version
        form.title = version.title || '';
        form.excerpt = version.excerpt || '';
        form.content = version.content || '';
        form.content_html = version.content_html || '';
        form.meta_title = version.meta_title || '';
        form.meta_description = version.meta_description || '';
        form.meta_keywords = version.meta_keywords || '';
        form.canonical_url = version.canonical_url || '';
        form.author_name = version.author_name || '';
        form.author_bio = version.author_bio || '';
        selectedCategoryValues.value = version.categories || [];

        console.log('📄 Loaded version for language:', languageCode);
    } else {
        // Nouvelle langue, vider les champs
        form.title = '';
        form.excerpt = '';
        form.content = '';
        form.content_html = '';
        form.meta_title = '';
        form.meta_description = '';
        form.meta_keywords = '';
        form.canonical_url = '';
        form.author_name = '';
        form.author_bio = '';
        selectedCategoryValues.value = [];

        console.log('🆕 New language version:', languageCode);
    }
};

// Fonction pour changer de langue
const switchLanguage = (newLanguage: string) => {
    if (newLanguage === currentLanguage.value) return;

    // Sauvegarder la version actuelle avant de changer
    saveCurrentVersion();

    // Changer la langue actuelle
    currentLanguage.value = newLanguage;

    // Charger la version de la nouvelle langue
    loadLanguageVersion(newLanguage);

    showNotification('success', 'Langue changée', `Basculé vers ${getLanguageName(newLanguage)}`);
};

// Functions
const fetchSiteColors = async (value: any) => {
    const siteId = value ? String(value) : '';
    if (!siteId) {
        siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
        return;
    }
    try {
        const response = await axios.get(siteRoutes.show(siteId) + '/colors');
        siteColors.value = response.data;
    } catch (error) {
        console.error('Error fetching site colors:', error);
        siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
    }
};

const fetchSiteCategories = async (siteId: any) => {
    console.log('Fetching categories for site:', siteId);

    if (!siteId) {
        console.log('No siteId provided, clearing categories');
        availableCategories.value = [];
        return;
    }

    try {
        const url = siteRoutes.show(siteId) + '/categories';
        console.log('Making request to:', url);
        const response = await axios.get(url);
        console.log('Categories response:', response.data);
        availableCategories.value = response.data;
    } catch (error: any) {
        console.error('Error fetching site categories:', error);
        if (error.response) {
            console.error('Error response:', error.response.data);
            console.error('Error status:', error.response.status);
        }
        availableCategories.value = [];
    }
};

const handleContentUpdate = (content: string) => {
    console.log('🔥 handleContentUpdate called with:', content ? content.substring(0, 200) + '...' : 'empty content');

    form.content = content;

    // Convertir immédiatement en HTML
    if (content) {
        try {
            console.log('🔄 Attempting to parse content...');
            const editorJSData = typeof content === 'string' ? JSON.parse(content) : content;
            console.log('✅ Parsed EditorJS data:', JSON.stringify(editorJSData, null, 2));

            // Vérifier la structure des blocs
            if (editorJSData.blocks) {
                console.log('📦 Blocks found:', editorJSData.blocks.length);
                editorJSData.blocks.forEach((block: any, index: number) => {
                    console.log(`Block ${index}:`, {
                        type: block.type,
                        data: block.data,
                    });
                });
            }

            console.log('🔄 Converting to HTML...');
            const htmlResult = convertForWebhook(editorJSData);
            console.log('✅ HTML conversion result:', htmlResult);

            form.content_html = htmlResult;
            console.log('✅ form.content_html updated:', form.content_html.substring(0, 100) + '...');
        } catch (error) {
            console.error('❌ Erreur lors de la conversion du contenu:', error);
            form.content_html = '';
        }
    } else {
        console.log('⚠️ Content is empty, clearing content_html');
        form.content_html = '';
    }
};

const handleCoverImageUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    // Vérifier la taille du fichier (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        alert('File size must be less than 2MB');
        return;
    }

    uploadingCoverImage.value = true;

    try {
        // Créer une prévisualisation immédiate
        const reader = new FileReader();
        reader.onload = (e) => {
            currentCoverImageUrl.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);

        // Stocker le fichier dans le form
        form.cover_image = file;
    } catch (error) {
        console.error('Error uploading cover image:', error);
        alert('Error uploading image');
    } finally {
        uploadingCoverImage.value = false;
    }
};

const removeCoverImage = () => {
    currentCoverImageUrl.value = '';
    form.cover_image = null;
    if (coverImageInput.value) {
        coverImageInput.value.value = '';
    }
};

// Nouvelles fonctions pour la traduction multi-langues
const fetchSiteLanguages = async (siteId: any) => {
    if (!siteId) {
        siteLanguages.value = [];
        return;
    }

    try {
        console.log('🌍 Fetching languages for site:', siteId);
        const response = await axios.get(siteRoutes.show(siteId) + '/languages');
        siteLanguages.value = response.data;
        console.log('✅ Site languages fetched:', siteLanguages.value);
    } catch (error: any) {
        console.error('❌ Error fetching site languages:', error);
        siteLanguages.value = [];
    }
};

const translateToMultipleLanguages = async () => {
    if (!hasContent.value || selectedTranslationLanguages.value.length === 0) return;

    translating.value = true;
    translationResults.value = [];

    try {
        for (const targetLanguage of selectedTranslationLanguages.value) {
            console.log('🌍 Translating to:', targetLanguage);

            const response = await axios.post(
                '/articles/translate',
                {
                    title: form.title,
                    excerpt: form.excerpt,
                    content: form.content,
                    meta_title: form.meta_title,
                    meta_description: form.meta_description,
                    meta_keywords: form.meta_keywords,
                    author_bio: form.author_bio,
                    target_language: targetLanguage,
                    source_language: 'fr',
                },
                {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                },
            );

            const translatedData = response.data;
            translationResults.value.push({
                language: targetLanguage,
                data: translatedData,
            });
        }

        showNotification('success', 'Traduction réussie', `Articles traduits en ${selectedTranslationLanguages.value.length} langue(s)`);

        console.log('✅ All translations completed:', translationResults.value);
    } catch (error: any) {
        console.error('❌ Erreur lors de la traduction multiple:', error);

        let errorMessage = 'Erreur lors de la traduction';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.message) {
            errorMessage = error.message;
        }

        showNotification('error', 'Erreur de traduction', errorMessage);
    } finally {
        translating.value = false;
    }
};

const loadTranslation = (result: any) => {
    const translatedData = result.data;

    // Remplacer le contenu par la traduction
    form.title = translatedData.title || form.title;
    form.excerpt = translatedData.excerpt || form.excerpt;
    form.content = translatedData.content || form.content;
    form.meta_title = translatedData.meta_title || form.meta_title;
    form.meta_description = translatedData.meta_description || form.meta_description;
    form.meta_keywords = translatedData.meta_keywords || form.meta_keywords;
    form.author_bio = translatedData.author_bio || form.author_bio;

    // Convertir le contenu traduit en HTML
    if (form.content) {
        try {
            const editorJSData = typeof form.content === 'string' ? JSON.parse(form.content) : form.content;
            form.content_html = convertForWebhook(editorJSData);
        } catch (error) {
            console.error('Erreur lors de la conversion du contenu traduit:', error);
        }
    }

    console.log('✅ Translation loaded for:', getLanguageName(result.language));

    // Log pour débugger quels champs ont été remplis
    console.log('📋 Form fields after AI generation:', {
        title: form.title,
        excerpt: form.excerpt,
        meta_title: form.meta_title,
        meta_description: form.meta_description,
        meta_keywords: form.meta_keywords,
        author_name: form.author_name,
        author_bio: form.author_bio,
        categories: selectedCategoryValues.value,
    });
};

// Watchers APRÈS les déclarations
watch(
    () => form.processing,
    (newValue) => {
        console.log('Form processing state:', newValue);
    },
    { immediate: true },
);

const isEditing = computed(() => !!props.article?.id);

watch(
    () => props.article,
    async (newArticle) => {
        if (newArticle && 'id' in newArticle) {
            form.title = newArticle.title;
            form.excerpt = newArticle.excerpt;

            // Gestion du contenu : priorité à EditorJS, sinon conversion depuis HTML
            if (newArticle.content) {
                // Si on a du contenu EditorJS, l'utiliser directement
                form.content = newArticle.content;
            } else if (newArticle.content_html) {
                // Si on a seulement du HTML (article reçu via webhook), le convertir
                const editorJSData = convertHTMLToEditorJS(newArticle.content_html);
                form.content = JSON.stringify(editorJSData);
            } else {
                form.content = '';
            }

            form.content_html = newArticle.content_html || '';

            // Afficher l'image de couverture existante
            if (newArticle.cover_image) {
                currentCoverImageUrl.value = newArticle.cover_image;
            }
            form.meta_title = newArticle.meta_title;
            form.meta_description = newArticle.meta_description;
            form.meta_keywords = newArticle.meta_keywords;
            form.canonical_url = newArticle.canonical_url;
            form.status = newArticle.status;
            form.scheduled_at = newArticle.scheduled_at || undefined;
            form.author_name = newArticle.author_name;
            form.author_bio = newArticle.author_bio;
            form.categories = newArticle.categories?.map((c) => c.id) || [];

            // Si l'article a un site_id, le précharger
            if (newArticle.site_id) {
                const siteOption = siteOptions.value.find((s) => s.value === String(newArticle.site_id));
                if (siteOption) {
                    selectedSiteValues.value = [String(newArticle.site_id)];
                    form.site_id = String(newArticle.site_id);
                    await Promise.all([fetchSiteColors(newArticle.site_id), fetchSiteCategories(newArticle.site_id)]);

                    // Précharger la catégorie sélectionnée si il y en a une
                    if (newArticle.categories && newArticle.categories.length > 0) {
                        selectedCategoryValues.value = newArticle.categories.map((cat) => cat.id.toString());
                    }
                }
            }
        } else {
            form.reset();
            selectedSiteValues.value = [];
            selectedCategoryValues.value = [];
            availableCategories.value = [];
            siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
        }
    },
    { immediate: true },
);

const submit = () => {
    console.log('🚀 Submit called');
    console.log('📝 form.content:', form.content ? form.content.substring(0, 200) + '...' : 'empty');
    console.log('🌐 form.content_html BEFORE conversion:', form.content_html ? form.content_html.substring(0, 200) + '...' : 'empty');

    // Convertir le contenu EditorJS en HTML avant l'envoi
    if (form.content) {
        try {
            const editorJSData = typeof form.content === 'string' ? JSON.parse(form.content) : form.content;
            form.content_html = convertForWebhook(editorJSData);
            console.log('🌐 form.content_html AFTER conversion:', form.content_html ? form.content_html.substring(0, 200) + '...' : 'empty');
        } catch (error) {
            console.error('❌ Erreur lors de la conversion du contenu dans submit:', error);
            form.content_html = '';
        }
    }

    console.log('📤 Final form data being sent:', {
        title: form.title,
        content: form.content ? 'has content' : 'empty',
        content_html: form.content_html ? 'has html' : 'empty',
        site_id: form.site_id,
        cover_image: form.cover_image ? 'has file' : 'no file',
    });

    if (isEditing.value && props.article && props.article.id) {
        form.put(articleRoutes.update(props.article.id), {
            onSuccess: () => emit('close'),
        });
    } else {
        form.post(articleRoutes.store(), {
            onSuccess: () => emit('close'),
        });
    }
};

// Watch pour convertir automatiquement le contenu EditorJS en HTML
watch(
    () => form.content,
    (newContent) => {
        if (newContent) {
            try {
                const editorJSData = typeof newContent === 'string' ? JSON.parse(newContent) : newContent;
                form.content_html = convertForWebhook(editorJSData);
            } catch (error) {
                console.error('Erreur lors de la conversion automatique du contenu:', error);
                form.content_html = '';
            }
        } else {
            form.content_html = '';
        }
    },
    { deep: true },
);

// Watch pour gérer les changements de site
watch(
    selectedSiteValues,
    async (newSiteValues) => {
        if (newSiteValues.length > 0) {
            const siteId = newSiteValues[0];
            form.site_id = siteId;

            // Reset categories when changing site
            form.categories = [];
            availableCategories.value = [];
            selectedCategoryValues.value = [];

            await Promise.all([fetchSiteColors(siteId), fetchSiteCategories(siteId), fetchSiteLanguages(siteId)]);
        } else {
            form.site_id = '';
            siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
            availableCategories.value = [];
            selectedCategoryValues.value = [];
            siteLanguages.value = [];
        }
    },
    { deep: true },
);

// Watch pour synchroniser les changements du multiselect avec le form
watch(
    selectedCategoryValues,
    (newCategories) => {
        form.categories = newCategories.map((value) => Number(value));
    },
    { deep: true },
);

// Fonction de notification (comme dans SiteList.vue)
function showNotification(type: 'success' | 'error', title: string, message: string) {
    // Clear any existing timeout
    if (notification.timeout) {
        clearTimeout(notification.timeout);
    }

    // Set notification data
    notification.type = type;
    notification.title = title;
    notification.message = message;
    notification.show = true;

    // Auto-hide after 5 seconds
    notification.timeout = setTimeout(() => {
        notification.show = false;
    }, 5000) as unknown as number;
}

// Nouvelle fonction pour génération multi-langues
const generateMultiLanguageArticle = async () => {
    if (!aiPrompt.value.trim() || selectedGenerationLanguages.value.length === 0) {
        showNotification('error', 'Paramètres manquants', 'Veuillez saisir un prompt et sélectionner au moins une langue');
        return;
    }

    console.log('🚀 Starting multi-language generation:', {
        prompt: aiPrompt.value,
        languages: selectedGenerationLanguages.value,
        siteId: form.site_id,
    });

    generatingWithAI.value = true;
    generationResults.value = [];

    try {
        // Générer pour chaque langue sélectionnée
        for (const targetLanguage of selectedGenerationLanguages.value) {
            console.log('🤖 Generating article for language:', targetLanguage);

            const requestData = {
                prompt: aiPrompt.value,
                site_id: form.site_id,
                language: targetLanguage, // Envoie une langue à la fois
            };

            console.log('📤 Request data:', requestData);

            const response = await axios.post('/articles/generate-with-ai', requestData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const aiData = response.data;
            console.log('✅ AI generation response for', targetLanguage, ':', aiData);

            generationResults.value.push({
                language: targetLanguage,
                data: aiData,
            });
        }

        // Charger le premier résultat dans le formulaire
        if (generationResults.value.length > 0) {
            loadGeneratedArticle(generationResults.value[0]);
        }

        showNotification('success', 'Génération réussie', `Articles générés en ${selectedGenerationLanguages.value.length} langue(s)`);

        // Vider le prompt après génération réussie
        aiPrompt.value = '';
    } catch (error: any) {
        console.error('❌ Erreur lors de la génération multi-langues:', error);
        console.error('📋 Error details:', {
            status: error.response?.status,
            data: error.response?.data,
            message: error.message,
        });

        let errorMessage = 'Erreur lors de la génération des articles';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.error) {
            errorMessage = error.response.data.error;
        } else if (error.response?.data?.errors) {
            // Gestion des erreurs de validation Laravel
            const validationErrors = Object.values(error.response.data.errors).flat();
            errorMessage = validationErrors.join(', ');
        } else if (error.message) {
            errorMessage = error.message;
        }

        showNotification('error', 'Erreur de génération', errorMessage);
    } finally {
        generatingWithAI.value = false;
    }
};

// Fonction pour charger un article généré et remplir TOUS les champs
const loadGeneratedArticle = (result: any) => {
    const aiData = result.data;
    const language = result.language;

    console.log('📝 Loading generated article data for', language, ':', aiData);

    // Créer la version pour cette langue
    const version = {
        title: aiData.title || '',
        excerpt: aiData.excerpt || '',
        content: aiData.content || '',
        content_html: '',
        meta_title: aiData.meta_title || '',
        meta_description: aiData.meta_description || '',
        meta_keywords: aiData.meta_keywords || '',
        canonical_url: aiData.canonical_url || '',
        author_name: aiData.author_name || '',
        author_bio: aiData.author_bio || '',
        categories: [] as string[],
    };

    // Convertir le contenu EditorJS en HTML AVANT de l'assigner
    if (version.content) {
        try {
            // S'assurer que le contenu est au bon format
            let editorJSData;
            if (typeof version.content === 'string') {
                editorJSData = JSON.parse(version.content);
            } else {
                editorJSData = version.content;
            }

            // Vérifier que c'est un objet EditorJS valide
            if (editorJSData && editorJSData.blocks) {
                version.content = JSON.stringify(editorJSData);
                version.content_html = convertForWebhook(editorJSData);
                console.log('✅ Content converted to HTML for', language);
            } else {
                console.warn('⚠️ Invalid EditorJS format, creating simple content');
                // Créer un contenu EditorJS simple si le format n'est pas valide
                const simpleContent = {
                    time: Date.now(),
                    blocks: [
                        {
                            type: 'paragraph',
                            data: {
                                text: version.content,
                            },
                        },
                    ],
                    version: '2.28.2',
                };
                version.content = JSON.stringify(simpleContent);
                version.content_html = convertForWebhook(simpleContent);
            }
        } catch (error) {
            console.error('❌ Error converting content for', language, ':', error);
            // En cas d'erreur, créer un contenu par défaut
            const defaultContent = {
                time: Date.now(),
                blocks: [
                    {
                        type: 'paragraph',
                        data: {
                            text: aiData.content || '',
                        },
                    },
                ],
                version: '2.28.2',
            };
            version.content = JSON.stringify(defaultContent);
            version.content_html = convertForWebhook(defaultContent);
        }
    }

    // Si des catégories sont suggérées et correspondent aux catégories disponibles
    if (aiData.suggested_categories && Array.isArray(aiData.suggested_categories)) {
        console.log('🏷️ Processing suggested categories:', aiData.suggested_categories);

        const matchingCategories = availableCategories.value
            .filter((cat) =>
                aiData.suggested_categories.some(
                    (suggested: string) =>
                        cat.name.toLowerCase().includes(suggested.toLowerCase()) || suggested.toLowerCase().includes(cat.name.toLowerCase()),
                ),
            )
            .map((cat) => cat.id.toString());

        console.log('✅ Matched categories:', matchingCategories);
        version.categories = matchingCategories;
    }

    // Sauvegarder cette version dans le système multi-langues
    articleVersions.value.set(language, version);

    // Si c'est la première génération ou si on génère pour la langue actuelle, charger dans le formulaire
    if (language === currentLanguage.value || articleVersions.value.size === 1) {
        currentLanguage.value = language;
        loadLanguageVersion(language);
    }

    console.log('✅ Article généré et sauvegardé pour:', getLanguageName(language));

    // Log pour débugger quels champs ont été remplis
    console.log('📋 Version saved for', language, ':', version);
};

// Watchers pour sauvegarder automatiquement les modifications
watch(
    [
        () => form.title,
        () => form.excerpt,
        () => form.content,
        () => form.meta_title,
        () => form.meta_description,
        () => form.meta_keywords,
        () => form.canonical_url,
        () => form.author_name,
        () => form.author_bio,
        () => selectedCategoryValues.value,
    ],
    () => {
        // Sauvegarder automatiquement la version actuelle quand l'utilisateur modifie quelque chose
        if (currentLanguage.value) {
            saveCurrentVersion();
        }
    },
    { deep: true, flush: 'post' },
);

// Fonctions pour l'IA et multi-langues
const languageNames: Record<string, string> = {
    fr: 'Français',
    en: 'English',
    es: 'Español',
    de: 'Deutsch',
    it: 'Italiano',
    pt: 'Português',
    nl: 'Nederlands',
    ru: 'Русский',
    ja: '日本語',
    zh: '中文',
};

const getLanguageName = (langCode: string): string => {
    return languageNames[langCode] || langCode;
};
</script>

<style>
.custom-multiselect .multiselect__tags {
    min-height: 48px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    padding: 8px 12px;
    font-size: 15px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.custom-multiselect .multiselect__tags:hover {
    border-color: #9ca3af;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.custom-multiselect .multiselect__tags:focus-within {
    border-color: #6b7280;
    box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.1);
    transform: translateY(-1px);
}

.custom-multiselect .multiselect__input {
    border: none;
    background: transparent;
    font-size: 15px;
    padding: 4px 0;
}

.custom-multiselect .multiselect__input:focus {
    outline: none;
}

.custom-multiselect .multiselect__placeholder {
    color: #9ca3af;
    padding-top: 4px;
    margin-bottom: 8px;
    font-size: 15px;
}

.custom-multiselect .multiselect__content-wrapper {
    border: none;
    border-radius: 8px;
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
    background: white;
    margin-top: 4px;
    overflow: hidden;
    animation: dropdownAppear 0.15s ease-out;
}

.custom-multiselect .multiselect__content {
    max-height: 240px;
}

.custom-multiselect .multiselect__option {
    padding: 0;
    border-bottom: 1px solid #f3f4f6;
    transition: all 0.15s ease;
}

.custom-multiselect .multiselect__option:last-child {
    border-bottom: none;
}

.custom-multiselect .multiselect__option--highlight {
    background: #f9fafb;
    color: #374151;
}

.custom-multiselect .multiselect__option--selected {
    background: #f3f4f6;
    color: #1f2937;
    font-weight: 500;
}

.custom-multiselect .multiselect__option--selected::after {
    content: '✓';
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #059669;
    font-weight: bold;
}

.custom-multiselect .multiselect__tag {
    background: none !important;
    border: none !important;
    border-radius: 0 !important;
    color: inherit !important;
    font-size: inherit !important;
    font-weight: inherit !important;
    padding: 0 !important;
    margin: 0 !important;
    animation: tagAppear 0.2s ease-out;
}

.custom-multiselect .multiselect__tag-icon {
    display: none !important;
}

.custom-multiselect .multiselect__spinner {
    background: #6b7280;
    border-radius: 50%;
    width: 3px;
    height: 3px;
}

.custom-multiselect .multiselect__loading {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(2px);
}

/* Animation pour les tags */
@keyframes tagAppear {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Transition douce pour le contenu */
@keyframes dropdownAppear {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
