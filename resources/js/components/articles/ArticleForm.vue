<template>
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold">
                {{ isEditing ? 'Edit Article' : 'Create Article' }}
            </h2>
            <p class="text-muted-foreground">
                {{ isEditing ? 'Update your article content and settings.' : 'Create a new article with rich content.' }}
            </p>
        </div>
        <form @submit.prevent="submit" class="space-y-6">
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
                        <div>
                            <Label for="categories">Categories</Label>
                            <MultiSelect v-model="form.categories" :options="categoryOptions" placeholder="Select categories..." />
                            <InputError :message="form.errors.categories" class="mt-2" />
                        </div>

                        <div>
                            <Label for="tags">Tags</Label>
                            <MultiSelect v-model="form.tags" :options="tagOptions" placeholder="Select tags..." />
                            <InputError :message="form.errors.tags" class="mt-2" />
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
                    <EditorJS v-model="form.content" :disabled="form.processing" class="min-h-[400px]" />
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface Tag {
    id: number;
    name: string;
}

interface Article {
    id?: number;
    title: string;
    excerpt: string;
    content: string;
    featured_image_url: string;
    status: string;
    scheduled_at?: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    canonical_url: string;
    author_name: string;
    author_bio: string;
    categories?: Category[];
    tags?: Tag[];
}

const props = defineProps<{
    article?: Article;
    categories: Category[];
    tags: Tag[];
}>();

const emit = defineEmits(['close']);

const form = useForm({
    title: '',
    excerpt: '',
    content: '',
    featured_image_url: '',
    status: 'draft',
    scheduled_at: undefined as string | undefined,
    categories: [] as number[],
    tags: [] as number[],
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    canonical_url: '',
    author_name: '',
    author_bio: '',
});

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
    (newArticle) => {
        if (newArticle && 'id' in newArticle) {
            form.title = newArticle.title;
            form.excerpt = newArticle.excerpt;
            form.content = newArticle.content;
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
            form.tags = newArticle.tags?.map((t) => t.id) || [];
        } else {
            form.reset();
        }
    },
    { immediate: true },
);

const submit = () => {
    if (isEditing.value) {
        form.put(route('articles.update', props.article.id), {
            onSuccess: () => emit('close'),
        });
    } else {
        form.post(route('articles.store'), {
            onSuccess: () => emit('close'),
        });
    }
};

const categoryOptions = computed(() => {
    return props.categories.map((c) => ({
        value: c.id,
        label: c.name,
    }));
});

const tagOptions = computed(() => {
    return props.tags.map((t) => ({
        value: t.id,
        label: t.name,
    }));
});
</script>
