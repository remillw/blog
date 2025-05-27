<template>
  <div class="article-recovery">
    <!-- Bouton de récupération -->
    <button
      v-if="showRecoveryButton"
      @click="recoverArticle"
      :disabled="isRecovering"
      class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-md transition-colors duration-200"
    >
      <svg v-if="isRecovering" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <svg v-else class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
      </svg>
      {{ isRecovering ? 'Récupération...' : 'Récupérer depuis le SaaS' }}
    </button>

    <!-- Message de succès -->
    <div v-if="recoverySuccess" class="mt-3 p-3 bg-green-100 border border-green-400 text-green-700 rounded-md">
      <div class="flex items-center">
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Article récupéré avec succès ! 
        <router-link 
          :to="`/articles/${recoveredArticle.id}/edit`" 
          class="ml-2 text-green-800 underline hover:text-green-900"
        >
          Éditer maintenant
        </router-link>
      </div>
    </div>

    <!-- Message d'erreur -->
    <div v-if="recoveryError" class="mt-3 p-3 bg-red-100 border border-red-400 text-red-700 rounded-md">
      <div class="flex items-center">
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        {{ recoveryError }}
      </div>
    </div>

    <!-- Formulaire de recherche manuelle -->
    <div v-if="showManualSearch" class="mt-4 p-4 bg-gray-50 rounded-md">
      <h4 class="text-sm font-medium text-gray-900 mb-3">Recherche manuelle</h4>
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Titre de l'article</label>
          <input
            v-model="manualSearchTitle"
            type="text"
            placeholder="Entrez le titre de l'article..."
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">ID externe (optionnel)</label>
          <input
            v-model="manualSearchExternalId"
            type="text"
            placeholder="ID externe de l'article..."
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <button
          @click="recoverArticleManually"
          :disabled="isRecovering || (!manualSearchTitle && !manualSearchExternalId)"
          class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white text-sm font-medium rounded-md transition-colors duration-200"
        >
          Rechercher et récupérer
        </button>
      </div>
    </div>

    <!-- Bouton pour afficher la recherche manuelle -->
    <button
      v-if="!showManualSearch && !recoverySuccess"
      @click="showManualSearch = true"
      class="mt-2 text-sm text-blue-600 hover:text-blue-800 underline"
    >
      Recherche manuelle
    </button>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  articleId: {
    type: Number,
    default: null
  },
  externalId: {
    type: String,
    default: null
  },
  title: {
    type: String,
    default: null
  },
  autoShow: {
    type: Boolean,
    default: true
  }
})

const isRecovering = ref(false)
const recoverySuccess = ref(false)
const recoveryError = ref(null)
const recoveredArticle = ref(null)
const showManualSearch = ref(false)
const manualSearchTitle = ref('')
const manualSearchExternalId = ref('')

const showRecoveryButton = computed(() => {
  return props.autoShow && !recoverySuccess.value && !showManualSearch.value
})

const recoverArticle = async () => {
  isRecovering.value = true
  recoveryError.value = null

  try {
    const response = await fetch('/articles/recover', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        article_id: props.articleId,
        external_id: props.externalId,
        title: props.title
      })
    })

    const data = await response.json()

    if (data.success) {
      recoverySuccess.value = true
      recoveredArticle.value = data.article
    } else {
      recoveryError.value = data.message || 'Erreur lors de la récupération'
    }
  } catch (error) {
    console.error('Erreur de récupération:', error)
    recoveryError.value = 'Erreur de connexion au serveur'
  } finally {
    isRecovering.value = false
  }
}

const recoverArticleManually = async () => {
  isRecovering.value = true
  recoveryError.value = null

  try {
    const response = await fetch('/articles/recover', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        external_id: manualSearchExternalId.value || null,
        title: manualSearchTitle.value || null
      })
    })

    const data = await response.json()

    if (data.success) {
      recoverySuccess.value = true
      recoveredArticle.value = data.article
      showManualSearch.value = false
    } else {
      recoveryError.value = data.message || 'Article non trouvé'
    }
  } catch (error) {
    console.error('Erreur de récupération manuelle:', error)
    recoveryError.value = 'Erreur de connexion au serveur'
  } finally {
    isRecovering.value = false
  }
}
</script> 