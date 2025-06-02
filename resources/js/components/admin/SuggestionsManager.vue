<template>
  <div class="suggestions-manager">
    <!-- Header avec filtres -->
    <div class="bg-white shadow rounded-lg mb-6">
      <div class="p-6 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-xl font-semibold text-gray-900">
              💡 Gestion des Suggestions
            </h2>
            <p class="mt-1 text-sm text-gray-600">
              Examinez et traitez les suggestions de catégories avec IA anti-doublons
            </p>
          </div>
          <div class="mt-4 sm:mt-0">
            <button
              @click="refreshSuggestions"
              :disabled="loading"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
            >
              <RefreshIcon class="w-4 h-4 mr-2" :class="{'animate-spin': loading}" />
              Actualiser
            </button>
          </div>
        </div>
      </div>

      <!-- Filtres -->
      <div class="p-6 bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
            <select v-model="filters.status" @change="loadSuggestions" 
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
              <option value="">Tous les statuts</option>
              <option value="pending">En attente</option>
              <option value="approved">Approuvées</option>
              <option value="rejected">Rejetées</option>
              <option value="merged">Fusionnées</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Langue</label>
            <select v-model="filters.language" @change="loadSuggestions"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
              <option value="">Toutes les langues</option>
              <option value="fr">🇫🇷 Français</option>
              <option value="en">🇬🇧 Anglais</option>
              <option value="es">🇪🇸 Espagnol</option>
              <option value="de">🇩🇪 Allemand</option>
              <option value="it">🇮🇹 Italien</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Similarité</label>
            <select v-model="filters.high_similarity" @change="loadSuggestions"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
              <option :value="null">Toutes</option>
              <option :value="true">Forte similarité (>70%)</option>
              <option :value="false">Faible similarité (<70%)</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Par page</label>
            <select v-model="filters.per_page" @change="loadSuggestions"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
          
          <div class="flex items-end">
            <button @click="resetFilters" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
              Réinitialiser
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Liste des suggestions -->
    <div class="bg-white shadow rounded-lg">
      <div v-if="loading && suggestions.length === 0" class="p-6">
        <div class="space-y-4">
          <div v-for="i in 5" :key="i" class="animate-pulse">
            <div class="flex space-x-4 p-4 border border-gray-200 rounded-lg">
              <div class="w-12 h-12 bg-gray-200 rounded-full"></div>
              <div class="flex-1 space-y-2">
                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                <div class="h-3 bg-gray-100 rounded w-1/2"></div>
                <div class="h-3 bg-gray-100 rounded w-1/4"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="suggestions.length === 0" class="p-12 text-center">
        <div class="text-gray-400 text-6xl mb-4">📭</div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune suggestion trouvée</h3>
        <p class="text-gray-600">Modifiez vos filtres ou attendez de nouvelles suggestions.</p>
      </div>

      <div v-else class="divide-y divide-gray-200">
        <div 
          v-for="suggestion in suggestions" 
          :key="suggestion.id"
          class="p-6 hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <!-- En-tête de la suggestion -->
              <div class="flex items-center space-x-3 mb-3">
                <div class="flex-shrink-0">
                  <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg"
                       :class="getSimilarityClass(suggestion.similarity_score)">
                    {{ getSimilarityIcon(suggestion.similarity_score) }}
                  </div>
                </div>
                
                <div class="flex-1">
                  <div class="flex items-center space-x-2">
                    <h3 class="text-lg font-medium text-gray-900">
                      {{ suggestion.suggested_name }}
                    </h3>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          :class="getStatusClass(suggestion.status)">
                      {{ getStatusLabel(suggestion.status) }}
                    </span>
                    <span class="text-sm text-gray-500">
                      {{ getLanguageFlag(suggestion.language_code) }} {{ suggestion.language_code.toUpperCase() }}
                    </span>
                  </div>
                  
                  <div class="mt-1 text-sm text-gray-600">
                    Suggéré par <strong>{{ suggestion.suggested_by.name }}</strong>
                    • {{ formatRelativeTime(suggestion.created_at) }}
                  </div>
                </div>
              </div>

              <!-- Similarité détectée -->
              <div v-if="suggestion.similar_category" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-center mb-2">
                  <ExclamationTriangleIcon class="w-5 h-5 text-yellow-600 mr-2" />
                  <span class="text-sm font-medium text-yellow-800">
                    Similarité détectée: {{ Math.round(suggestion.similarity_score * 100) }}%
                  </span>
                </div>
                <p class="text-sm text-yellow-700">
                  Similaire à: <strong>{{ suggestion.similar_category.name }}</strong>
                  ({{ suggestion.similar_category.path }})
                </p>
                <div v-if="suggestion.ai_reasoning" class="mt-2 text-xs text-yellow-600">
                  <strong>IA:</strong> {{ suggestion.ai_reasoning }}
                </div>
              </div>

              <!-- Actions pour suggestions en attente -->
              <div v-if="suggestion.status === 'pending'" class="flex flex-wrap gap-2">
                <button
                  @click="approveSuggestion(suggestion)"
                  :disabled="processingIds.includes(suggestion.id)"
                  class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 disabled:opacity-50"
                >
                  <CheckIcon class="w-4 h-4 mr-1" />
                  Approuver
                </button>
                
                <button
                  @click="showRejectModal(suggestion)"
                  :disabled="processingIds.includes(suggestion.id)"
                  class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 disabled:opacity-50"
                >
                  <XMarkIcon class="w-4 h-4 mr-1" />
                  Rejeter
                </button>
                
                <button
                  v-if="suggestion.similar_category"
                  @click="showMergeModal(suggestion)"
                  :disabled="processingIds.includes(suggestion.id)"
                  class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                >
                  <ArrowsRightLeftIcon class="w-4 h-4 mr-1" />
                  Fusionner
                </button>
              </div>

              <!-- Information pour suggestions traitées -->
              <div v-else class="text-sm text-gray-600">
                <template v-if="suggestion.reviewed_by">
                  Traité par <strong>{{ suggestion.reviewed_by.name }}</strong>
                  le {{ formatDate(suggestion.reviewed_at) }}
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.total > 0" class="border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700">
            Affichage de {{ pagination.from }} à {{ pagination.to }} sur {{ pagination.total }} résultats
          </div>
          <div class="flex space-x-1">
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="goToPage(page)"
              :disabled="page === pagination.current_page"
              class="px-3 py-2 text-sm font-medium rounded-md"
              :class="page === pagination.current_page 
                ? 'bg-blue-600 text-white' 
                : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de rejet -->
    <RejectModal
      v-if="showRejectModalState"
      :suggestion="selectedSuggestion"
      @confirm="handleReject"
      @cancel="showRejectModalState = false"
    />

    <!-- Modal de fusion -->
    <MergeModal
      v-if="showMergeModalState"
      :suggestion="selectedSuggestion"
      @confirm="handleMerge"
      @cancel="showMergeModalState = false"
    />
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { 
  RefreshIcon, 
  CheckIcon, 
  XMarkIcon, 
  ExclamationTriangleIcon, 
  ArrowsRightLeftIcon 
} from '@heroicons/vue/24/outline'
import RejectModal from './modals/RejectModal.vue'
import MergeModal from './modals/MergeModal.vue'

export default {
  name: 'SuggestionsManager',
  components: {
    RefreshIcon,
    CheckIcon,
    XMarkIcon,
    ExclamationTriangleIcon,
    ArrowsRightLeftIcon,
    RejectModal,
    MergeModal
  },
  props: {
    userPermissions: {
      type: Array,
      required: true
    }
  },
  emits: ['suggestion-updated'],
  setup(props, { emit }) {
    const authStore = useAuthStore()
    const loading = ref(false)
    const suggestions = ref([])
    const pagination = ref(null)
    const processingIds = ref([])
    
    const filters = reactive({
      status: 'pending',
      language: '',
      high_similarity: null,
      per_page: 20
    })

    // États des modals
    const showRejectModalState = ref(false)
    const showMergeModalState = ref(false)
    const selectedSuggestion = ref(null)

    // Charger les suggestions
    const loadSuggestions = async (page = 1) => {
      loading.value = true
      try {
        const params = new URLSearchParams({
          page: page.toString(),
          ...Object.fromEntries(
            Object.entries(filters).filter(([_, value]) => value !== '' && value !== null)
          )
        })

        const response = await fetch(`/api/admin/suggestions?${params}`, {
          headers: {
            'Authorization': `Bearer ${authStore.token}`,
            'Content-Type': 'application/json'
          }
        })

        if (!response.ok) throw new Error('Erreur de chargement')

        const data = await response.json()
        if (data.success) {
          suggestions.value = data.data.data
          pagination.value = {
            current_page: data.data.current_page,
            last_page: data.data.last_page,
            from: data.data.from,
            to: data.data.to,
            total: data.data.total
          }
        }
      } catch (error) {
        console.error('Erreur:', error)
      } finally {
        loading.value = false
      }
    }

    // Calculer les pages visibles pour la pagination
    const visiblePages = computed(() => {
      if (!pagination.value) return []
      
      const current = pagination.value.current_page
      const last = pagination.value.last_page
      const pages = []
      
      for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i)
      }
      
      return pages
    })

    // Navigation de pagination
    const goToPage = (page) => {
      if (page !== pagination.value.current_page) {
        loadSuggestions(page)
      }
    }

    // Approuver une suggestion
    const approveSuggestion = async (suggestion) => {
      processingIds.value.push(suggestion.id)
      try {
        const response = await fetch(`/api/admin/suggestions/${suggestion.id}/approve`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${authStore.token}`,
            'Content-Type': 'application/json'
          }
        })

        if (!response.ok) throw new Error('Erreur d\'approbation')

        const data = await response.json()
        if (data.success) {
          // Actualiser la liste
          await loadSuggestions(pagination.value.current_page)
          emit('suggestion-updated')
        }
      } catch (error) {
        console.error('Erreur:', error)
      } finally {
        processingIds.value = processingIds.value.filter(id => id !== suggestion.id)
      }
    }

    // Afficher le modal de rejet
    const showRejectModal = (suggestion) => {
      selectedSuggestion.value = suggestion
      showRejectModalState.value = true
    }

    // Gérer le rejet
    const handleReject = async ({ reason }) => {
      const suggestion = selectedSuggestion.value
      processingIds.value.push(suggestion.id)
      
      try {
        const response = await fetch(`/api/admin/suggestions/${suggestion.id}/reject`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${authStore.token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ reason })
        })

        if (!response.ok) throw new Error('Erreur de rejet')

        const data = await response.json()
        if (data.success) {
          await loadSuggestions(pagination.value.current_page)
          emit('suggestion-updated')
        }
      } catch (error) {
        console.error('Erreur:', error)
      } finally {
        processingIds.value = processingIds.value.filter(id => id !== suggestion.id)
        showRejectModalState.value = false
        selectedSuggestion.value = null
      }
    }

    // Afficher le modal de fusion
    const showMergeModal = (suggestion) => {
      selectedSuggestion.value = suggestion
      showMergeModalState.value = true
    }

    // Gérer la fusion
    const handleMerge = async ({ mergeWithId }) => {
      const suggestion = selectedSuggestion.value
      processingIds.value.push(suggestion.id)
      
      try {
        const response = await fetch(`/api/admin/suggestions/${suggestion.id}/merge`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${authStore.token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ merge_with_id: mergeWithId })
        })

        if (!response.ok) throw new Error('Erreur de fusion')

        const data = await response.json()
        if (data.success) {
          await loadSuggestions(pagination.value.current_page)
          emit('suggestion-updated')
        }
      } catch (error) {
        console.error('Erreur:', error)
      } finally {
        processingIds.value = processingIds.value.filter(id => id !== suggestion.id)
        showMergeModalState.value = false
        selectedSuggestion.value = null
      }
    }

    // Réinitialiser les filtres
    const resetFilters = () => {
      Object.assign(filters, {
        status: 'pending',
        language: '',
        high_similarity: null,
        per_page: 20
      })
      loadSuggestions()
    }

    const refreshSuggestions = () => {
      loadSuggestions(pagination.value?.current_page || 1)
    }

    // Utilitaires d'affichage
    const getSimilarityClass = (score) => {
      if (score >= 0.70) return 'bg-red-100 text-red-600'
      if (score >= 0.50) return 'bg-yellow-100 text-yellow-600'
      return 'bg-green-100 text-green-600'
    }

    const getSimilarityIcon = (score) => {
      if (score >= 0.70) return '⚠️'
      if (score >= 0.50) return '⚡'
      return '✅'
    }

    const getStatusClass = (status) => {
      const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'approved': 'bg-green-100 text-green-800',
        'rejected': 'bg-red-100 text-red-800',
        'merged': 'bg-blue-100 text-blue-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }

    const getStatusLabel = (status) => {
      const labels = {
        'pending': 'En attente',
        'approved': 'Approuvée',
        'rejected': 'Rejetée',
        'merged': 'Fusionnée'
      }
      return labels[status] || status
    }

    const getLanguageFlag = (code) => {
      const flags = {
        'fr': '🇫🇷', 'en': '🇬🇧', 'es': '🇪🇸', 'de': '🇩🇪', 'it': '🇮🇹'
      }
      return flags[code] || '🌐'
    }

    const formatRelativeTime = (datetime) => {
      const date = new Date(datetime)
      const now = new Date()
      const diffInMinutes = Math.floor((now - date) / (1000 * 60))
      
      if (diffInMinutes < 1) return 'À l\'instant'
      if (diffInMinutes < 60) return `Il y a ${diffInMinutes}min`
      
      const diffInHours = Math.floor(diffInMinutes / 60)
      if (diffInHours < 24) return `Il y a ${diffInHours}h`
      
      const diffInDays = Math.floor(diffInHours / 24)
      return `Il y a ${diffInDays}j`
    }

    const formatDate = (datetime) => {
      return new Date(datetime).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    onMounted(() => {
      loadSuggestions()
    })

    return {
      loading,
      suggestions,
      pagination,
      filters,
      processingIds,
      visiblePages,
      showRejectModalState,
      showMergeModalState,
      selectedSuggestion,
      loadSuggestions,
      goToPage,
      approveSuggestion,
      showRejectModal,
      handleReject,
      showMergeModal,
      handleMerge,
      resetFilters,
      refreshSuggestions,
      getSimilarityClass,
      getSimilarityIcon,
      getStatusClass,
      getStatusLabel,
      getLanguageFlag,
      formatRelativeTime,
      formatDate
    }
  }
}
</script>

<style scoped>
.suggestions-manager {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style> 