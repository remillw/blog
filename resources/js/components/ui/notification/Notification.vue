<template>
  <div v-if="show" :class="notificationClasses" class="fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm">
    <div class="flex items-start">
      <div class="flex-shrink-0">
        <component :is="iconComponent" class="h-5 w-5" />
      </div>
      <div class="ml-3 flex-1">
        <p class="text-sm font-medium" v-html="title"></p>
        <p v-if="message" class="mt-1 text-sm opacity-90" v-html="message"></p>
      </div>
      <div class="ml-4 flex-shrink-0">
        <button @click="close" class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2">
          <span class="sr-only">Fermer</span>
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

interface Props {
  type?: 'success' | 'error' | 'warning' | 'info'
  title: string
  message?: string
  duration?: number
  autoClose?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  type: 'info',
  duration: 5000,
  autoClose: true
})

const emit = defineEmits<{
  close: []
}>()

const show = ref(true)

const notificationClasses = computed(() => {
  const baseClasses = 'border-l-4'
  
  switch (props.type) {
    case 'success':
      return `${baseClasses} bg-green-50 border-green-400 text-green-800`
    case 'error':
      return `${baseClasses} bg-red-50 border-red-400 text-red-800`
    case 'warning':
      return `${baseClasses} bg-yellow-50 border-yellow-400 text-yellow-800`
    case 'info':
    default:
      return `${baseClasses} bg-blue-50 border-blue-400 text-blue-800`
  }
})

const iconComponent = computed(() => {
  switch (props.type) {
    case 'success':
      return 'CheckCircleIcon'
    case 'error':
      return 'XCircleIcon'
    case 'warning':
      return 'ExclamationTriangleIcon'
    case 'info':
    default:
      return 'InformationCircleIcon'
  }
})

const close = () => {
  show.value = false
  emit('close')
}

onMounted(() => {
  if (props.autoClose && props.duration > 0) {
    setTimeout(() => {
      close()
    }, props.duration)
  }
})
</script> 