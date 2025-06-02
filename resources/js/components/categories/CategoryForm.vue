<template>
    <Dialog :open="show" @update:open="handleDialogChange">
        <DialogContent class="custom-dialog-content">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit Category' : 'Create Category' }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" type="text" required :disabled="form.processing" placeholder="Enter category name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="description">Description (optional)</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        :disabled="form.processing"
                        placeholder="Enter category description"
                        rows="3"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="space-y-2">
                    <Label for="language_code">Langue</Label>
                    <Select v-model="form.language_code" :disabled="form.processing">
                        <SelectTrigger>
                            <SelectValue placeholder="Sélectionner une langue" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="fr">🇫🇷 Français</SelectItem>
                            <SelectItem value="en">🇬🇧 English</SelectItem>
                            <SelectItem value="es">🇪🇸 Español</SelectItem>
                            <SelectItem value="de">🇩🇪 Deutsch</SelectItem>
                            <SelectItem value="it">🇮🇹 Italiano</SelectItem>
                            <SelectItem value="pt">🇵🇹 Português</SelectItem>
                            <SelectItem value="nl">🇳🇱 Nederlands</SelectItem>
                            <SelectItem value="ru">🇷🇺 Русский</SelectItem>
                            <SelectItem value="ja">🇯🇵 日本語</SelectItem>
                            <SelectItem value="zh">🇨🇳 中文</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.language_code" />
                    <p class="text-xs text-gray-500">Une catégorie ne peut être que dans une seule langue</p>
                </div>

                <div class="space-y-2">
                    <Label>Sites</Label>
                    <MultiSelect
                        v-model="selectedSiteValues"
                        :options="formattedSites"
                        placeholder="Sélectionnez les sites..."
                        :disabled="form.processing"
                        class="w-full"
                    />
                    <InputError :message="form.errors.sites" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeModal" :disabled="form.processing"> Cancel </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Update' : 'Create' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import MultiSelect from '@/components/ui/MultiSelect.vue';
import { useRoutes } from '@/composables/useRoutes';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Site {
    id: number;
    name: string;
}

interface SiteOption {
    value: string;
    label: string;
}

interface Category {
    id: number;
    name: string;
    description: string;
    language_code?: string;
    sites: Site[];
}

const props = defineProps<{
    show: boolean;
    category?: Category;
    availableSites: Site[];
}>();

const emit = defineEmits(['close']);

const { categoryRoutes } = useRoutes();

const formattedSites = computed<SiteOption[]>(() => {
    return props.availableSites.map((site) => ({
        value: site.id.toString(),
        label: site.name,
    }));
});

const form = useForm({
    name: '',
    description: '',
    language_code: 'fr',
    sites: [] as number[],
});

const selectedSiteValues = ref<string[]>([]);

const isEditing = computed(() => !!props.category?.id);

// Watch pour synchroniser les changements du multiselect avec le form
watch(
    selectedSiteValues,
    (newValues) => {
        form.sites = newValues.map((value) => parseInt(value));
    },
    { deep: true },
);

watch(
    () => props.category,
    (newCategory) => {
        if (newCategory && 'id' in newCategory) {
            form.name = newCategory.name || '';
            form.description = newCategory.description || '';
            form.language_code = newCategory.language_code || 'fr';
            selectedSiteValues.value = newCategory.sites?.map((s) => s.id.toString()) || [];
            form.sites = newCategory.sites?.map((s) => s.id) || [];
        } else {
            form.reset();
            form.language_code = 'fr'; // Valeur par défaut
            selectedSiteValues.value = [];
            form.sites = [];
        }
    },
    { immediate: true },
);

const handleDialogChange = (open: boolean) => {
    if (!open) {
        emit('close');
    }
};

const submit = () => {
    if (isEditing.value && props.category) {
        form.put(categoryRoutes.update(props.category.id), {
            onSuccess: () => {
                emit('close');
                form.reset();
                form.language_code = 'fr';
                selectedSiteValues.value = [];
            },
        });
    } else {
        form.post(categoryRoutes.store(), {
            onSuccess: () => {
                emit('close');
                form.reset();
                form.language_code = 'fr';
                selectedSiteValues.value = [];
            },
        });
    }
};

const closeModal = () => {
    emit('close');
    form.reset();
    form.language_code = 'fr';
    selectedSiteValues.value = [];
};
</script>

<style>
.custom-dialog-content {
    max-width: 500px;
    width: 95vw;
    max-height: 85vh;
    min-height: unset;
    overflow-y: auto;
    border-radius: 12px;
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
    padding: 2rem 1.5rem;
    background: #ffffff;
    border: 1px solid #e5e7eb;
}

@media (max-width: 600px) {
    .custom-dialog-content {
        max-width: 98vw;
        padding: 1.2rem 0.8rem;
    }
}
</style>
