<template>
    <Head title="Sites" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl leading-tight font-semibold text-gray-800">Sites</h2>
                <Button @click="openCreateModal">
                    <PlusIcon class="mr-2 h-4 w-4" />
                    New Site
                </Button>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Domain</TableHead>
                        <TableHead>Plateforme</TableHead>
                        <TableHead>Langues</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="w-[100px]">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="site in sites" :key="site.id">
                        <TableCell>{{ site.name }}</TableCell>
                        <TableCell>{{ site.domain }}</TableCell>
                        <TableCell
                            ><span class="capitalize">{{ site.platform_type }}</span></TableCell
                        >
                        <TableCell>
                            <span v-for="lang in site.languages" :key="lang.id" class="mr-1 inline-block">
                                <img :src="lang.flag_url" :alt="lang.name" class="h-5 w-5 rounded-full border" />
                            </span>
                        </TableCell>
                        <TableCell>
                            <Badge :variant="site.is_active ? 'default' : 'secondary'">
                                {{ site.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" class="h-8 w-8 p-0">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="viewSite(site)">
                                        <EyeIcon class="mr-2 h-4 w-4" />
                                        Voir
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="editSite(site)">
                                        <PencilIcon class="mr-2 h-4 w-4" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="site.is_active" @click="deactivateSite(site)">
                                        <XIcon class="mr-2 h-4 w-4" />
                                        Deactivate
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-else @click="activateSite(site)">
                                        <CheckIcon class="mr-2 h-4 w-4" />
                                        Activate
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-red-600" @click="deleteSite(site)">
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

        <SiteForm v-if="showForm" :show="showForm" :site="selectedSite" :available-languages="availableLanguages" @close="closeForm" />

        <Dialog v-model:open="showViewModal">
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center">
                        <span class="text-xl">{{ siteToView?.name }}</span>
                        <Badge class="ml-2" :variant="siteToView?.is_active ? 'default' : 'secondary'">
                            {{ siteToView?.is_active ? 'Actif' : 'Inactif' }}
                        </Badge>
                    </DialogTitle>
                    <p class="text-muted-foreground">{{ siteToView?.domain }}</p>
                </DialogHeader>

                <div v-if="siteToView" class="space-y-6">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle>Informations générales</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label class="text-muted-foreground text-sm font-medium">Plateforme</Label>
                                    <div class="mt-1">
                                        <Badge variant="outline" class="uppercase">{{ siteToView.platform_type }}</Badge>
                                    </div>
                                </div>
                                <div>
                                    <Label class="text-muted-foreground text-sm font-medium">Langues</Label>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <div v-for="lang in siteToView.languages" :key="lang.id" class="group relative">
                                            <Avatar class="h-6 w-6">
                                                <AvatarImage :src="lang.flag_url" :alt="lang.name" />
                                                <AvatarFallback>{{ lang.name.substring(0, 2) }}</AvatarFallback>
                                            </Avatar>
                                            <div class="absolute z-10 -mt-1 hidden rounded bg-black px-2 py-1 text-xs text-white group-hover:block">
                                                {{ lang.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <Label class="text-muted-foreground text-sm font-medium">Description</Label>
                                <p class="mt-1">{{ siteToView.description || 'Aucune description' }}</p>
                            </div>

                            <div class="mt-4">
                                <Label class="text-muted-foreground text-sm font-medium">Couleurs</Label>
                                <div class="mt-1 flex gap-2">
                                    <div class="flex flex-col items-center">
                                        <div class="h-8 w-8 rounded" :style="`background:${siteToView.primary_color}`"></div>
                                        <span class="mt-1 text-xs">Primary</span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <div class="h-8 w-8 rounded" :style="`background:${siteToView.secondary_color}`"></div>
                                        <span class="mt-1 text-xs">Secondary</span>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <div class="h-8 w-8 rounded" :style="`background:${siteToView.accent_color}`"></div>
                                        <span class="mt-1 text-xs">Accent</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle>Informations techniques</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label class="text-muted-foreground text-sm font-medium">API Key</Label>
                                <div class="mt-1 flex items-center gap-2">
                                    <Input readonly :value="siteToView.api_key" class="bg-muted font-mono" />
                                    <div class="relative">
                                        <Button variant="outline" class="h-10 w-10 p-0" @click="copyToClipboard(siteToView.api_key, 'api')">
                                            <CheckIcon v-if="copied.api" class="h-4 w-4 text-green-500" />
                                            <CopyIcon v-else class="h-4 w-4" />
                                        </Button>
                                        <div
                                            class="absolute -top-8 left-1/2 z-10 -translate-x-1/2 rounded bg-black px-2 py-1 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100"
                                        >
                                            {{ copied.api ? 'Copié!' : 'Copier' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <Label class="text-muted-foreground text-sm font-medium">Webhook URL</Label>
                                <div class="mt-1 flex items-center gap-2">
                                    <Input readonly :value="siteToView.webhook_url" class="bg-muted font-mono" />
                                    <div class="relative">
                                        <Button variant="outline" class="h-10 w-10 p-0" @click="copyToClipboard(siteToView.webhook_url, 'webhook')">
                                            <CheckIcon v-if="copied.webhook" class="h-4 w-4 text-green-500" />
                                            <CopyIcon v-else class="h-4 w-4" />
                                        </Button>
                                        <div
                                            class="absolute -top-8 left-1/2 z-10 -translate-x-1/2 rounded bg-black px-2 py-1 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100"
                                        >
                                            {{ copied.webhook ? 'Copié!' : 'Copier' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showViewModal = false">Fermer</Button>
                    <Button @click="editSite(siteToView as Site)">Modifier</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

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
import SiteForm from '@/components/sites/SiteForm.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CheckIcon, CopyIcon, EyeIcon, MoreHorizontal, PencilIcon, PlusIcon, TrashIcon, XIcon } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface Site {
    id: number;
    name: string;
    domain: string;
    is_active: boolean;
    platform_type: string;
    description: string;
    primary_color: string;
    secondary_color: string;
    accent_color: string;
    languages: {
        id: number;
        name: string;
        flag_url: string;
    }[];
    api_key: string;
    webhook_url: string;
}

const { sites, availableLanguages } = defineProps<{
    sites: Site[];
    availableLanguages: {
        id: number;
        name: string;
        flag_url: string;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Sites',
        href: '/sites',
    },
];

const showForm = ref(false);
const selectedSite = ref<Site | undefined>(undefined);
const showViewModal = ref(false);
const siteToView = ref<Site | null>(null);
const copied = reactive({
    api: false,
    webhook: false,
});

const notification = reactive({
    show: false,
    type: 'success',
    title: '',
    message: '',
    timeout: null as number | null,
});

const openCreateModal = () => {
    selectedSite.value = undefined;
    showForm.value = true;
};

const editSite = (site: Site) => {
    selectedSite.value = site;
    showForm.value = true;
    if (showViewModal.value) {
        showViewModal.value = false;
    }
};

const closeForm = () => {
    showForm.value = false;
    selectedSite.value = undefined;
};

const activateSite = (site: Site) => {
    router.put(route('sites.update', site.id), {
        status: 'active',
    });
};

const deactivateSite = (site: Site) => {
    router.put(route('sites.update', site.id), {
        status: 'inactive',
    });
};

const deleteSite = (site: Site) => {
    if (confirm('Are you sure you want to delete this site?')) {
        router.delete(route('sites.destroy', site.id));
    }
};

const viewSite = (site: Site) => {
    siteToView.value = site;
    showViewModal.value = true;
    copied.api = false;
    copied.webhook = false;
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

function copyToClipboard(text: string, type: 'api' | 'webhook') {
    navigator.clipboard
        .writeText(text)
        .then(() => {
            copied[type] = true;

            showNotification(
                'success',
                'Copié avec succès',
                type === 'api' ? 'Clé API copiée dans le presse-papier' : 'URL webhook copiée dans le presse-papier',
            );

            setTimeout(() => {
                copied[type] = false;
            }, 2000);
        })
        .catch(() => {
            showNotification('error', 'Erreur de copie', 'Impossible de copier dans le presse-papier');
        });
}
</script>
