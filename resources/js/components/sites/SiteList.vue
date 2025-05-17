<template>
    <div class="space-y-4">
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Sites</CardTitle>
                        <CardDescription>Manage your sites and their settings.</CardDescription>
                    </div>
                    <Button @click="openForm()">Add Site</Button>
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Domain</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="w-[100px]">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="site in sites" :key="site.id">
                            <TableCell>{{ site.name }}</TableCell>
                            <TableCell>{{ site.domain }}</TableCell>
                            <TableCell>
                                <span
                                    :class="{
                                        'text-green-600': site.is_active,
                                        'text-red-600': !site.is_active,
                                    }"
                                >
                                    {{ site.is_active ? 'active' : 'inactive' }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" class="h-8 w-8 p-0">
                                            <MoreHorizontal class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="openForm(site)"> Edit </DropdownMenuItem>
                                        <DropdownMenuItem asChild class="text-red-600">
                                            <Link :href="route('sites.destroy', site.id)" method="delete" as="button"> Delete </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <SiteForm :show="showForm" :site="selectedSite" @close="closeForm" />
    </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import SiteForm from './SiteForm.vue';

const props = defineProps({
    sites: {
        type: Array,
        required: true,
    },
    editingSite: {
        type: Object,
        default: null,
    },
});

const showForm = ref(false);
const selectedSite = ref(null);

// Watch for changes in editingSite prop
watch(
    () => props.editingSite,
    (newSite) => {
        if (newSite) {
            selectedSite.value = newSite;
            showForm.value = true;
        }
    },
    { immediate: true },
);

const openForm = (site = null) => {
    selectedSite.value = site;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    selectedSite.value = null;
};
</script>
