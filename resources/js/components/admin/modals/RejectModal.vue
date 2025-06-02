<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$emit('cancel')"></div>

      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
              <X class="h-6 w-6 text-red-600" />
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                Rejeter la suggestion
              </h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500 mb-4">
                  Vous êtes sur le point de rejeter la suggestion "<strong>{{ suggestion?.suggested_name }}</strong>".
                </p>
                
                <div>
                  <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
                    Raison du rejet (obligatoire)
                  </label>
                  <textarea
                    id="reason"
                    v-model="reason"
                    rows="4"
                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 sm:text-sm"
                    placeholder="Expliquez pourquoi cette suggestion est rejetée..."
                  ></textarea>
                  <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            @click="handleReject"
            :disabled="!reason.trim() || loading"
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
          >
            {{ loading ? 'Rejet...' : 'Rejeter' }}
          </button>
          <button
            @click="$emit('cancel')"
            :disabled="loading"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Annuler
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { X } from 'lucide-vue-next'

defineProps<{
  suggestion: any
}>()

const emit = defineEmits<{
  confirm: [data: { reason: string }]
  cancel: []
}>()

const reason = ref('')
const loading = ref(false)
const error = ref('')

const handleReject = () => {
  if (!reason.value.trim()) {
    error.value = 'La raison du rejet est obligatoire'
    return
  }

  error.value = ''
  loading.value = true
  
  emit('confirm', { reason: reason.value.trim() })
}
</script> 