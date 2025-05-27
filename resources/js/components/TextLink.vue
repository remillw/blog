<script setup lang="ts">
import { Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    href: string;
    tabindex?: number;
    method?: Method;
    as?: string;
    external?: boolean;
}

const props = defineProps<Props>();

const isExternal = computed(() => {
    return props.external || props.href.startsWith('http') || props.href.startsWith('mailto:') || props.href.startsWith('tel:');
});
</script>

<template>
    <a
        v-if="isExternal"
        :href="href"
        :tabindex="tabindex"
        target="_blank"
        rel="noopener noreferrer"
        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
    >
        <slot />
    </a>
    
    <Link
        v-else
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
    >
        <slot />
    </Link>
</template>
