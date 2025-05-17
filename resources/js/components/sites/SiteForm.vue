<template>
    <Dialog :open="show" @update:open="emit('close')">
        <DialogContent class="custom-dialog-content">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit Site' : 'Add New Site' }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Update your site information.' : 'Create a new site to manage.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">Site Name</Label>
                    <Input id="name" v-model="form.name" type="text" required :disabled="form.processing" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="url">Site URL</Label>
                    <Input id="url" v-model="form.url" type="url" required :disabled="form.processing" />
                    <InputError :message="form.errors.url" />
                </div>

                <div class="space-y-2">
                    <Label for="platform_type">Plateforme</Label>
                    <select
                        id="platform_type"
                        v-model="form.platform_type"
                        :disabled="form.processing"
                        class="w-full rounded border px-3 py-2"
                        required
                    >
                        <option value="" disabled>Choisir une plateforme</option>
                        <option value="laravel">Laravel</option>
                        <option value="wordpress">WordPress</option>
                        <option value="prestashop">PrestaShop</option>
                    </select>
                    <InputError :message="form.errors.platform_type" />
                </div>

                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        :disabled="form.processing"
                        placeholder="Describe the purpose of this site..."
                        class="min-h-[100px]"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="space-y-2">
                    <Label for="primary_color">Primary Color</Label>
                    <div class="flex items-center gap-2">
                        <Input id="primary_color" v-model="form.primary_color" type="color" :disabled="form.processing" class="h-12 w-12 p-1" />
                        <Input v-model="form.primary_color" type="text" :disabled="form.processing" class="flex-1" placeholder="#4E8D44" />
                    </div>
                    <InputError :message="form.errors.primary_color" />
                </div>

                <div class="space-y-2">
                    <Label for="secondary_color">Secondary Color</Label>
                    <div class="flex items-center gap-2">
                        <Input id="secondary_color" v-model="form.secondary_color" type="color" :disabled="form.processing" class="h-12 w-12 p-1" />
                        <Input v-model="form.secondary_color" type="text" :disabled="form.processing" class="flex-1" placeholder="#6b7280" />
                    </div>
                    <InputError :message="form.errors.secondary_color" />
                </div>

                <div class="space-y-2">
                    <Label for="accent_color">Accent Color</Label>
                    <div class="flex items-center gap-2">
                        <Input id="accent_color" v-model="form.accent_color" type="color" :disabled="form.processing" class="h-12 w-12 p-1" />
                        <Input v-model="form.accent_color" type="text" :disabled="form.processing" class="flex-1" placeholder="#10b981" />
                    </div>
                    <InputError :message="form.errors.accent_color" />
                </div>

                <div class="space-y-2">
                    <Label for="status">Status</Label>
                    <Select v-model="form.status" :disabled="form.processing">
                        <SelectTrigger>
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>

                <div class="space-y-2">
                    <Label>Languages</Label>
                    <Multiselect
                        v-model="form.languages"
                        :options="formattedLanguages"
                        :multiple="true"
                        :close-on-select="false"
                        :clear-on-select="false"
                        :preserve-search="true"
                        label="label"
                        track-by="value"
                        placeholder="Sélectionne les langues"
                        class="custom-multiselect"
                    >
                        <template #option="{ option }">
                            <div class="flex items-center gap-2">
                                <img :src="option.flag_url" :alt="option.label" class="h-5 w-5 rounded-full border" />
                                <span>{{ option.label }}</span>
                            </div>
                        </template>
                        <template #tag="{ option, remove }">
                            <span class="mr-1 flex items-center gap-1 rounded bg-gray-100 px-2 py-1">
                                <img :src="option.flag_url" :alt="option.label" class="h-4 w-4 rounded-full border" />
                                <span class="text-xs">{{ option.label }}</span>
                                <button type="button" @click="remove(option)" class="ml-1 text-gray-400 hover:text-red-500">&times;</button>
                            </span>
                        </template>
                    </Multiselect>
                    <InputError :message="form.errors.languages" />
                </div>

                <div v-if="apiKey || webhookUrl" class="mb-4 space-y-2">
                    <div v-if="apiKey">
                        <Label>API Key</Label>
                        <div class="flex items-center gap-2">
                            <Input :value="apiKey" readonly class="font-mono select-all" />
                            <Button type="button" @click="copyToClipboard(apiKey)" size="sm" variant="outline">Copier</Button>
                        </div>
                    </div>
                    <div v-if="webhookUrl">
                        <Label>Webhook URL</Label>
                        <div class="flex items-center gap-2">
                            <Input :value="webhookUrl" readonly class="font-mono select-all" />
                            <Button type="button" @click="copyToClipboard(webhookUrl)" size="sm" variant="outline">Copier</Button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="emit('close')"> Cancel </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Update' : 'Create' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

interface Language {
    id: number;
    name: string;
    flag_url: string;
}

interface LanguageOption {
    value: number;
    label: string;
    flag_url: string;
}

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    site: {
        type: Object,
        default: () => ({}),
    },
    availableLanguages: {
        type: Array as () => Language[],
        default: () => [],
    },
    apiKey: {
        type: String,
        default: '',
    },
    webhookUrl: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const formattedLanguages = computed<LanguageOption[]>(() => {
    return props.availableLanguages.map((lang) => ({
        value: lang.id,
        label: lang.name,
        flag_url: lang.flag_url,
    }));
});

const form = useForm({
    name: props.site?.name || '',
    url: props.site?.domain || '',
    platform_type: props.site?.platform_type || '',
    status: props.site?.is_active ? 'active' : 'inactive',
    description: props.site?.description || '',
    primary_color: props.site?.primary_color || '#4E8D44',
    secondary_color: props.site?.secondary_color || '#6b7280',
    accent_color: props.site?.accent_color || '#10b981',
    languages: props.site?.languages
        ? props.site.languages.map((l: any) => {
              const found = props.availableLanguages.find((al: any) => al.id === (l.id ?? l.value));
              return found
                  ? { value: found.id, label: found.name, flag_url: found.flag_url }
                  : { value: l.id ?? l.value, label: l.name ?? '', flag_url: l.flag_url ?? '' };
          })
        : [],
});

watch(
    () => props.site,
    (newSite) => {
        if (newSite) {
            form.name = newSite.name;
            form.url = newSite.domain;
            form.platform_type = newSite.platform_type || '';
            form.status = newSite.is_active ? 'active' : 'inactive';
            form.description = newSite.description || '';
            form.primary_color = newSite.primary_color || '#4E8D44';
            form.secondary_color = newSite.secondary_color || '#6b7280';
            form.accent_color = newSite.accent_color || '#10b981';
            form.languages = newSite.languages
                ? newSite.languages.map((l: any) => {
                      const found = props.availableLanguages.find((al: any) => al.id === (l.id ?? l.value));
                      return found
                          ? { value: found.id, label: found.name, flag_url: found.flag_url }
                          : { value: l.id ?? l.value, label: l.name ?? '', flag_url: l.flag_url ?? '' };
                  })
                : [];
        } else {
            form.name = '';
            form.url = '';
            form.platform_type = '';
            form.status = 'active';
            form.description = '';
            form.primary_color = '#4E8D44';
            form.secondary_color = '#6b7280';
            form.accent_color = '#10b981';
            form.languages = [];
        }
    },
    { immediate: true, deep: true },
);

watch(
    () => props.show,
    (newValue) => {
        if (!newValue) {
            form.reset();
        }
    },
);

const isEditing = computed(() => !!props.site?.id);

const submit = () => {
    form.languages = form.languages.map((lang: any) => lang.value);
    if (isEditing.value) {
        form.put(route('sites.update', props.site.id));
    } else {
        form.post(route('sites.store'));
    }
};

watch(
    () => form.wasSuccessful,
    (success) => {
        if (success) {
            emit('close');
            form.reset();
        }
    },
);

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
}
</script>

<style>
.custom-multiselect .multiselect__tags {
    min-height: 44px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #fff;
    padding: 6px 8px;
    font-size: 15px;
}
.custom-multiselect .multiselect__option--highlight {
    background: #f3f4f6;
    color: #111;
}
.custom-multiselect .multiselect__option--selected {
    background: #e5e7eb;
    color: #111;
}
.custom-multiselect .multiselect__tag {
    background: #f3f4f6;
    color: #111;
    border-radius: 4px;
}
.custom-dialog-content {
    max-width: 700px;
    width: 95vw;
    max-height: 80vh;
    min-height: unset;
    overflow-y: auto;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
    padding: 2.5rem 2rem;
    background: #fff;
}

@media (max-width: 800px) {
    .custom-dialog-content {
        max-width: 98vw;
        padding: 1.2rem 0.5rem;
    }
}
</style>
