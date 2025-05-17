<template>
  <ul
    v-if="filteredItems.length"
    :style="{
      position: 'absolute',
      top: position.top + 'px',
      left: position.left + 'px',
      zIndex: 1000,
      background: 'white',
      border: '1px solid #e5e7eb',
      borderRadius: '0.5rem',
      boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
      padding: '0.5rem',
      minWidth: '180px',
    }"
  >
    <li
      v-for="item in filteredItems"
      :key="item.label"
      style="padding: 0.5rem; cursor: pointer;"
      @click="$emit('select', item)"
    >
      {{ item.label }}
    </li>
  </ul>
</template>

<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{
  items: Array<{ label: string; action: () => void }>;
  position: { top: number; left: number };
  query: string;
}>();
const emit = defineEmits(['select']);
const filteredItems = computed(() => {
  if (!props.query) return props.items;
  return props.items.filter(item => item.label.toLowerCase().includes(props.query.toLowerCase()));
});
</script> 