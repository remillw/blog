<template>
    <Head title="Edit Article" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl leading-tight font-semibold text-gray-800">Edit Article</h2>
            </div>

            <!-- Debug Information -->
            <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">🔍 Debug Information</h3>
                <div class="text-sm space-y-1">
                    <p><strong>Article ID:</strong> {{ article?.id || 'Not found' }}</p>
                    <p><strong>Article Title:</strong> {{ article?.title || 'Not found' }}</p>
                    <p><strong>Content Type:</strong> {{ article?.content_type || 'Not specified' }}</p>
                    <p><strong>Has EditorJS Content:</strong> {{ article?.editorjs_content ? 'Yes' : 'No' }}</p>
                    <p><strong>Has HTML Content:</strong> {{ article?.content_html ? 'Yes' : 'No' }}</p>
                    <p><strong>Categories Count:</strong> {{ categories?.length || 0 }}</p>
                    <p><strong>Sites Count:</strong> {{ sites?.length || 0 }}</p>
                    <p><strong>Current URL:</strong> {{ $page.url }}</p>
                </div>
            </div>

            <ArticleForm :show="true" :article="article" :categories="categories" :tags="tags" :sites="sites" @close="goBack" />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import ArticleForm from '@/components/articles/ArticleForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useRoutes } from '@/composables/useRoutes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';

// Types locaux pour corriger le linter
type Category = { id: number; name: string };
type Tag = { id: number; name: string };
type Article = any;

const { appRoutes, articleRoutes } = useRoutes();

const page = usePage();
const article = page.props.article as Article;
const categories = page.props.categories as Category[];
const tags = page.props.tags as Tag[];
const sites = page.props.sites as any[]; // Assuming the type for sites

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: appRoutes.dashboard() },
    { title: 'Articles', href: articleRoutes.index() },
    { title: 'Edit', href: page.url },
];

function goBack() {
    router.visit(articleRoutes.index());
}
</script>
