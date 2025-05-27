<template>
    <Head title="Edit Article" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl leading-tight font-semibold text-gray-800">Edit Article</h2>
            </div>
            <ArticleForm :show="true" :article="article" :categories="categories" :tags="tags" @close="goBack" />
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: appRoutes.dashboard() },
    { title: 'Articles', href: articleRoutes.index() },
    { title: 'Edit', href: page.url },
];

function goBack() {
    router.visit(articleRoutes.index());
}
</script>
