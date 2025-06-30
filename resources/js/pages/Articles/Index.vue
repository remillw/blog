<template>
    <Head title="Articles" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl leading-tight font-semibold text-gray-800">Articdaddales</h2>
                <Button as-child variant="outline">
                    <Link :href="articleRoutes.create()">
                        <PlusIcon class="mr-2 h-4 w-4" />
                        New Article
                    </Link>
                </Button>
            </div>
            <div class="mb-6 flex items-center justify-between">
                <div class="flex space-x-4">
                    <Select v-model="filters.status" class="w-40">
                        <SelectTrigger>
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                            <SelectItem value="scheduled">Scheduled</SelectItem>
                        </SelectContent>
                    </Select>

                    <Input v-model="filters.search" type="text" placeholder="Search articles..." class="w-64" />
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Title</TableHead>
                        <TableHead>Author</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Categories</TableHead>
                        <TableHead>Published</TableHead>
                        <TableHead class="w-[100px]">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="article in articles.data" :key="article.id">
                        <TableCell>
                            <div class="flex items-center space-x-3">
                                <img
                                    v-if="article.featured_image_url"
                                    :src="article.featured_image_url"
                                    :alt="article.title"
                                    class="h-10 w-10 rounded object-cover"
                                />
                                <div>
                                    <div class="font-medium">{{ article.title }}</div>
                                    <div class="text-sm text-gray-500">{{ article.reading_time }} min read</div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>{{ article.author_name || article.author.name }}</TableCell>
                        <TableCell>
                            <Badge :variant="article.status === 'published' ? 'default' : article.status === 'scheduled' ? 'outline' : 'secondary'">
                                {{ article.status }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="category in article.categories" :key="category.id" variant="outline">
                                    {{ category.name }}
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell>
                            {{ article.published_at ? formatDate(article.published_at) : '-' }}
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <!-- Bouton d'édition simple pour test -->
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="editArticle(article)"
                                    class="text-xs"
                                >
                                    ✏️ Edit
                                </Button>
                                
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" class="h-8 w-8 p-0">
                                            <MoreHorizontal class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="editArticle(article)">
                                            <PencilIcon class="mr-2 h-4 w-4" />
                                            Edittttt
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="article.status === 'draft'" @click="publishArticle(article)">
                                            <CheckIcon class="mr-2 h-4 w-4" />
                                            Publish
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="article.status === 'published'" @click="unpublishArticle(article)">
                                            <XIcon class="mr-2 h-4 w-4" />
                                            Unpublish
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem class="text-red-600" @click="deleteArticle(article)">
                                            <TrashIcon class="mr-2 h-4 w-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div class="mt-4">
                <Pagination :links="articles.links" :items-per-page="10" :total="articles.total" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useRoutes } from '@/composables/useRoutes';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckIcon, MoreHorizontal, PencilIcon, PlusIcon, TrashIcon, XIcon } from 'lucide-vue-next';
import { ref } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface Author {
    name: string;
}

interface Article {
    id: number;
    title: string;
    excerpt: string;
    content: string;
    featured_image_url: string | null;
    status: 'draft' | 'published' | 'scheduled';
    published_at: string | null;
    reading_time: number;
    author: Author;
    author_name: string | null;
    author_bio: string | null;
    categories: Category[];
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    canonical_url: string | null;
}

defineProps<{
    articles: {
        data: Article[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        total: number;
    };
    categories: Category[];
    tags: Array<{
        id: number;
        name: string;
    }>;
}>();

const { appRoutes, articleRoutes } = useRoutes();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: appRoutes.dashboard(),
    },
    {
        title: 'Articles',
        href: articleRoutes.index(),
    },
];

const filters = ref({
    status: 'all',
    search: '',
});

const publishArticle = (article: Article) => {
    router.put(articleRoutes.update(article.id), {
        status: 'published',
        published_at: new Date().toISOString(),
    });
};

const unpublishArticle = (article: Article) => {
    router.put(articleRoutes.update(article.id), {
        status: 'draft',
        published_at: null,
    });
};

const deleteArticle = (article: Article) => {
    if (confirm('Are you sure you want to delete this article?')) {
        router.delete(articleRoutes.destroy(article.id));
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString();
};

const editArticle = (article: Article) => {
    console.log('🚀 Navigating to edit page for article:', article.id);
    const editUrl = articleRoutes.edit(article.id);
    console.log('📍 Edit URL:', editUrl);
    
    // Méthode 1: Utiliser router.visit (Inertia)
    router.visit(editUrl, {
        onError: (errors) => {
            console.error('❌ Navigation errors:', errors);
            // Fallback: utiliser window.location
            console.log('🔄 Falling back to window.location');
            window.location.href = editUrl;
        },
        onSuccess: () => {
            console.log('✅ Navigation successful');
        },
        onStart: () => {
            console.log('🔄 Navigation started');
        }
    });
};
</script>
