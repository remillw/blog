<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const errors = page.props.errors || {};
const success = page.props.success;
const error = page.props.error;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <template #header>
            <slot name="header" />
        </template>
        <div v-if="Object.keys(errors).length" class="mb-4">
            <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                <div v-for="(message, key) in errors" :key="key">
                    <span v-if="Array.isArray(message)">
                        <span v-for="(msg, i) in message" :key="i">{{ msg }}</span>
                    </span>
                    <span v-else>{{ message }}</span>
                </div>
            </div>
        </div>
        <div v-if="success" class="mb-4">
            <div class="rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                {{ success }}
            </div>
        </div>
        <div v-if="error" class="mb-4">
            <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                {{ error }}
            </div>
        </div>
        <slot />
    </AppLayout>
</template>
