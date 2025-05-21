<template>
    <Head title="Create Article" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl leading-tight font-semibold text-gray-800">Create Article</h2>
            </div>
            <ArticleForm
                :show="true"
                :categories="categories"
                :tags="tags"
                :sites="sites"
                @close="goBack"
            />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import ArticleForm from '@/components/articles/ArticleForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';

// Types locaux pour corriger le linter
type Category = { id: number; name: string };
type Tag = { id: number; name: string };

const page = usePage();
const categories = page.props.categories as Category[];
const tags = page.props.tags as Tag[];
const sites = page.props.sites ?? [];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Articles', href: route('articles.index') },
    { title: 'Create', href: route('articles.create') },
];

function goBack() {
    router.visit(route('articles.index'));
}
</script>
