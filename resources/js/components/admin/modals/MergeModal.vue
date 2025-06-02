<template>
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$emit('cancel')"></div>

      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
              <ArrowLeftRight class="h-6 w-6 text-blue-600" />
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                Fusionner la suggestion
              </h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500 mb-4">
                  Fusionner "<strong>{{ suggestion?.suggested_name }}</strong>" avec une catégorie existante.
                </p>
                
                <div v-if="suggestion?.similar_category" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                  <p class="text-sm text-yellow-800">
                    <strong>Suggestion automatique :</strong> {{ suggestion.similar_category.name }}
                    <span class="text-yellow-600">({{ Math.round(suggestion.similarity_score * 100) }}% similaire)</span>
                  </p>
                  <button
                    @click="selectCategory(suggestion.similar_category)"
                    class="mt-2 text-sm text-blue-600 hover:text-blue-800"
                  >
                    Utiliser cette catégorie
                  </button>
                </div>
                
                <div>
                  <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                    Choisir la catégorie cible
                  </label>
                  <input
                    id="category"
                    v-model="searchQuery"
                    type="text"
                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="Rechercher une catégorie..."
                    @input="searchCategories"
                  />
                  
                  <!-- Catégorie sélectionnée -->
                  <div v-if="selectedCategory" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800">
                      <strong>Catégorie sélectionnée :</strong> {{ selectedCategory.name }}
                    </p>
                    <button
                      @click="selectedCategory = null"
                      class="mt-1 text-sm text-red-600 hover:text-red-800"
                    >
                      Supprimer la sélection
                    </button>
                  </div>
                  
                  <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            @click="handleMerge"
            :disabled="!selectedCategory || loading"
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
          >
            {{ loading ? 'Fusion...' : 'Fusionner' }}
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
import { ArrowLeftRight } from 'lucide-vue-next'

defineProps<{
  suggestion: any
}>()

const emit = defineEmits<{
  confirm: [data: { mergeWithId: number }]
  cancel: []
}>()

const searchQuery = ref('')
const selectedCategory = ref<any>(null)
const loading = ref(false)
const error = ref('')

const selectCategory = (category: any) => {
  selectedCategory.value = category
  searchQuery.value = category.name
}

const searchCategories = async () => {
  // Implémentation simplifiée pour l'instant
  console.log('Recherche:', searchQuery.value)
}

const handleMerge = () => {
  if (!selectedCategory.value) {
    error.value = 'Veuillez sélectionner une catégorie'
    return
  }

  error.value = ''
  loading.value = true
  
  emit('confirm', { mergeWithId: selectedCategory.value.id })
}
</script> 