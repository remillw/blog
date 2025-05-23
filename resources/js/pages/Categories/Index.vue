<template>
    <Head title="Categories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl leading-tight font-semibold text-gray-800">Categories</h2>
                <Button @click="openCreateModal">
                    <PlusIcon class="mr-2 h-4 w-4" />
                    New Category
                </Button>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Sites</TableHead>
                        <TableHead class="w-[100px]">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="category in categories" :key="category.id">
                        <TableCell class="font-medium">{{ category.name }}</TableCell>
                        <TableCell>{{ category.description || 'No description' }}</TableCell>
                        <TableCell>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="site in category.sites" :key="site.id" variant="outline">
                                    {{ site.name }}
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" class="h-8 w-8 p-0">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="editCategory(category)">
                                        <PencilIcon class="mr-2 h-4 w-4" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-red-600" @click="deleteCategory(category)">
                                        <TrashIcon class="mr-2 h-4 w-4" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <CategoryForm v-if="showForm" :show="showForm" :category="selectedCategory" :available-sites="availableSites" @close="closeForm" />

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
    </AppLayout>
</template>

<script setup lang="ts">
import CategoryForm from '@/components/categories/CategoryForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CheckIcon, MoreHorizontal, PencilIcon, PlusIcon, TrashIcon, XIcon } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface Site {
    id: number;
    name: string;
}

interface Category {
    id: number;
    name: string;
    description: string;
    sites: Site[];
}

const { categories, availableSites } = defineProps<{
    categories: Category[];
    availableSites: Site[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Categories',
        href: '/categories',
    },
];

const showForm = ref(false);
const selectedCategory = ref<Category | undefined>(undefined);

const notification = reactive({
    show: false,
    type: 'success',
    title: '',
    message: '',
    timeout: null as number | null,
});

const openCreateModal = () => {
    selectedCategory.value = undefined;
    showForm.value = true;
};

const editCategory = (category: Category) => {
    selectedCategory.value = category;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    selectedCategory.value = undefined;
};

const deleteCategory = (category: Category) => {
    if (confirm('Are you sure you want to delete this category?')) {
        router.delete(`/categories/${category.id}`);
    }
};

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

    // Auto-hide after 3 seconds
    notification.timeout = setTimeout(() => {
        notification.show = false;
    }, 3000) as unknown as number;
}
</script>
