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
                    <Label>Sites</Label>
                    <Multiselect
                        v-model="selectedSites"
                        :options="formattedSites"
                        :multiple="true"
                        :close-on-select="false"
                        :clear-on-select="false"
                        :preserve-search="true"
                        label="label"
                        track-by="value"
                        placeholder="Sélectionnez les sites..."
                        class="custom-multiselect"
                        :disabled="form.processing"
                    >
                        <template #option="{ option }">
                            <div class="flex items-center gap-3 px-3 py-2 transition-colors duration-150 hover:bg-gray-50">
                                <div class="h-3 w-3 rounded-full bg-gray-400 shadow-sm"></div>
                                <span class="font-medium text-gray-700">{{ option.label }}</span>
                            </div>
                        </template>
                        <template #tag="{ option, remove }">
                            <span
                                class="group m-0.5 inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition-all duration-200 hover:shadow-md"
                            >
                                <div class="h-1.5 w-1.5 rounded-full bg-gray-500"></div>
                                <span>{{ option.label }}</span>
                                <button
                                    type="button"
                                    @click="remove(option)"
                                    class="ml-1 flex h-4 w-4 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors duration-150 group-hover:scale-110 hover:bg-red-100 hover:text-red-600"
                                >
                                    <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </span>
                        </template>
                        <template #noResult>
                            <div class="px-4 py-3 text-center text-gray-500">
                                <div class="mb-2 text-2xl">🔍</div>
                                <div class="text-sm">Aucun site trouvé</div>
                            </div>
                        </template>
                        <template #noOptions>
                            <div class="px-4 py-3 text-center text-gray-500">
                                <div class="mb-2 text-2xl">📝</div>
                                <div class="text-sm">Aucun site disponible</div>
                            </div>
                        </template>
                    </Multiselect>
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
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

interface Site {
    id: number;
    name: string;
}

interface SiteOption {
    value: number;
    label: string;
}

interface Category {
    id: number;
    name: string;
    description: string;
    sites: Site[];
}

const props = defineProps<{
    show: boolean;
    category?: Category;
    availableSites: Site[];
}>();

const emit = defineEmits(['close']);

const selectedSites = ref<SiteOption[]>([]);

const formattedSites = computed<SiteOption[]>(() => {
    return props.availableSites.map((site) => ({
        value: site.id,
        label: site.name,
    }));
});

const form = useForm({
    name: '',
    description: '',
    sites: [] as number[],
});

const isEditing = computed(() => !!props.category?.id);

// Watch pour synchroniser les changements du multiselect avec le form
watch(
    selectedSites,
    (newSites) => {
        form.sites = newSites.map((site) => site.value);
    },
    { deep: true },
);

watch(
    () => props.category,
    (newCategory) => {
        if (newCategory && 'id' in newCategory) {
            form.name = newCategory.name || '';
            form.description = newCategory.description || '';
            selectedSites.value =
                newCategory.sites?.map((s) => ({
                    value: s.id,
                    label: s.name,
                })) || [];
            form.sites = newCategory.sites?.map((s) => s.id) || [];
        } else {
            form.reset();
            selectedSites.value = [];
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
        form.put(`/categories/${props.category.id}`, {
            onSuccess: () => {
                emit('close');
                form.reset();
                selectedSites.value = [];
            },
        });
    } else {
        form.post('/categories', {
            onSuccess: () => {
                emit('close');
                form.reset();
                selectedSites.value = [];
            },
        });
    }
};

const closeModal = () => {
    emit('close');
    form.reset();
    selectedSites.value = [];
};
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

    .custom-multiselect .multiselect__tags {
        min-height: 44px;
        padding: 6px 10px;
    }
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
