<template>
  <div class="category-admin-dashboard">
    <!-- Header avec navigation -->
    <div class="bg-white shadow-sm border-b mb-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">
              🗂️ Administration des Catégories
            </h1>
            <p class="text-sm text-gray-600 mt-1">
              Gestion des catégories globales et suggestions IA
            </p>
          </div>
          
          <!-- Indicateur de rôle -->
          <div class="flex items-center space-x-3">
            <div class="flex items-center bg-blue-50 px-3 py-1 rounded-full">
              <span class="text-xs font-medium text-blue-800">
                {{ userRole }}
              </span>
            </div>
            <button 
              @click="refreshDashboard"
              :disabled="loading"
              class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              <RotateCcw class="w-4 h-4 mr-1" :class="{'animate-spin': loading}" />
              Actualiser
            </button>
          </div>
        </div>

        <!-- Navigation tabs -->
        <div class="border-t border-gray-200">
          <nav class="-mb-px flex space-x-8">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap',
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
              :disabled="!hasPermission(tab.permission)"
            >
              <span class="mr-2">{{ tab.icon }}</span>
              {{ tab.name }}
              <span v-if="tab.badge && stats[tab.badge]" class="ml-2 bg-red-100 text-red-600 text-xs px-2 py-1 rounded-full">
                {{ stats[tab.badge] }}
              </span>
            </button>
          </nav>
        </div>
      </div>
    </div>

    <!-- Contenu principal -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Vue Dashboard -->
      <div v-if="activeTab === 'dashboard'" class="dashboard-overview">
        <!-- Cartes de statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center text-2xl">
                  🗂️
                </div>
              </div>
              <div class="ml-4 flex-1">
                <p class="text-sm font-medium text-gray-600">Total Catégories</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.total_categories || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-lg bg-yellow-100 flex items-center justify-center text-2xl">
                  💡
                </div>
              </div>
              <div class="ml-4 flex-1">
                <p class="text-sm font-medium text-gray-600">Suggestions en attente</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.pending_suggestions || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center text-2xl">
                  ⚠️
                </div>
              </div>
              <div class="ml-4 flex-1">
                <p class="text-sm font-medium text-gray-600">Forte similarité</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.high_similarity_suggestions || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center text-2xl">
                  🌐
                </div>
              </div>
              <div class="ml-4 flex-1">
                <p class="text-sm font-medium text-gray-600">Sites liés</p>
                <p class="text-2xl font-semibold text-gray-900">{{ stats.total_sites_linked || 0 }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Vue Suggestions -->
      <SuggestionsManager
        v-if="activeTab === 'suggestions'"
        :user-permissions="userPermissions"
        @suggestion-updated="refreshDashboard"
      />

      <!-- Vue Catégories -->
      <div v-if="activeTab === 'categories'" class="bg-white shadow rounded-lg">
        <div class="p-6">
          <div class="text-center py-12">
            <div class="text-gray-400 text-6xl mb-4">🚧</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Gestion des catégories</h3>
            <p class="text-gray-600">
              Interface en cours de développement. Utilisez l'API REST en attendant.
            </p>
          </div>
        </div>
      </div>

      <!-- Vue Analytics -->
      <div v-if="activeTab === 'analytics'" class="bg-white shadow rounded-lg">
        <div class="p-6">
          <div class="text-center py-12">
            <div class="text-gray-400 text-6xl mb-4">📊</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Analytics des catégories</h3>
            <p class="text-gray-600">
              Données disponibles via <code class="bg-gray-100 px-2 py-1 rounded text-sm">/api/admin/analytics/categories</code>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { RotateCcw } from 'lucide-vue-next'
import SuggestionsManager from './admin/SuggestionsManager.vue'

const page = usePage()
const loading = ref(false)
const activeTab = ref('dashboard')

const stats = reactive({
  total_categories: 0,
  root_categories: 0,
  pending_suggestions: 0,
  high_similarity_suggestions: 0,
  categories_with_sites: 0,
  total_sites_linked: 0,
  most_used_categories: [],
  recent_suggestions: []
})

const languageStats = ref([])
const recentActivity = ref([])

// Configuration des onglets
const tabs = [
  {
    id: 'dashboard',
    name: 'Tableau de bord',
    icon: '📊',
    permission: null
  },
  {
    id: 'suggestions',
    name: 'Suggestions',
    icon: '💡',
    badge: 'pending_suggestions',
    permission: 'review suggestions'
  },
  {
    id: 'categories',
    name: 'Catégories',
    icon: '🗂️',
    permission: 'manage categories'
  },
  {
    id: 'analytics',
    name: 'Analytics',
    icon: '📈',
    permission: 'view analytics'
  }
]

// Informations utilisateur depuis Inertia
const user = computed(() => page.props.auth?.user)
const userRole = computed(() => {
  const userPerms = user.value?.permissions || []
  return userPerms.includes('administrator') ? 'Administrateur' : 'Reviewer'
})

const userPermissions = computed(() => user.value?.permissions || [])

// Vérifier les permissions
const hasPermission = (permission: string | null) => {
  if (!permission) return true
  const userPerms = user.value?.permissions || []
  return userPerms.includes(permission) || userPerms.includes('administrator')
}

// Charger les données du dashboard
const loadDashboard = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/admin/dashboard/categories', {
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })

    if (!response.ok) {
      throw new Error('Erreur lors du chargement')
    }

    const data = await response.json()
    
    if (data.success) {
      Object.assign(stats, data.data.stats)
      languageStats.value = data.data.language_stats
      recentActivity.value = data.data.recent_activity
    }
  } catch (error) {
    console.error('Erreur dashboard:', error)
  } finally {
    loading.value = false
  }
}

const refreshDashboard = () => {
  loadDashboard()
}

onMounted(() => {
  if (!hasPermission(null)) {
    console.error('Permissions insuffisantes')
    return
  }
  
  loadDashboard()
})
</script>

<style scoped>
.category-admin-dashboard {
  min-height: 100vh;
  background-color: #f9fafb;
}
</style> 