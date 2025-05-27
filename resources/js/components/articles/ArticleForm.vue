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
                        <Label for="featured_image_url">Featured Image URL</Label>
                        <Input id="featured_image_url" v-model="form.featured_image_url" type="url" :disabled="form.processing" />
                        <InputError :message="form.errors.featured_image_url" />
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
                            <div v-if="selectedSiteValues.length === 0" class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
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
    </div>
</template>

<script setup lang="ts">
import EditorJS from '@/components/Editor/EditorJS.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import MultiSelect from '@/components/ui/MultiSelect.vue';
import { useRoutes } from '@/composables/useRoutes';
import { useEditorJSConverter } from '@/composables/useEditorJSConverter';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface CategoryOption {
    value: string;
    label: string;
}

interface Article {
    id?: number;
    title: string;
    excerpt: string;
    content: string;
    content_html: string;
    featured_image_url: string;
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
const { convertForWebhook, convertToHTML, convertHTMLToEditorJS } = useEditorJSConverter();

// Déclarations des refs AVANT les watchers
const siteColors = ref({
    primary_color: '',
    secondary_color: '',
    accent_color: '',
});

const selectedSiteValues = ref<string[]>([]);
const selectedCategoryValues = ref<string[]>([]);
const availableCategories = ref<Category[]>([]);

const form = useForm({
    title: '',
    excerpt: '',
    content: '',
    content_html: '',
    featured_image_url: '',
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
                        data: block.data
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
            form.featured_image_url = newArticle.featured_image_url;
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
        site_id: form.site_id
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

// Watcher pour convertir automatiquement le contenu EditorJS en HTML
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
    { deep: true }
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
            
            await Promise.all([fetchSiteColors(siteId), fetchSiteCategories(siteId)]);
        } else {
            form.site_id = '';
            siteColors.value = { primary_color: '', secondary_color: '', accent_color: '' };
            availableCategories.value = [];
            selectedCategoryValues.value = [];
        }
    },
    { deep: true }
);

// Watch pour synchroniser les changements du multiselect avec le form
watch(
    selectedCategoryValues,
    (newCategories) => {
        form.categories = newCategories.map((value) => Number(value));
    },
    { deep: true },
);
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
