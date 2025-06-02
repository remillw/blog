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
              <RefreshIcon class="w-4 h-4 mr-1" :class="{'animate-spin': loading}" />
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
      <DashboardOverview 
        v-if="activeTab === 'dashboard'"
        :stats="stats"
        :language-stats="languageStats"
        :recent-activity="recentActivity"
        :loading="loading"
      />

      <!-- Vue Suggestions -->
      <SuggestionsManager
        v-if="activeTab === 'suggestions'"
        :user-permissions="userPermissions"
        @suggestion-updated="refreshDashboard"
      />

      <!-- Vue Catégories -->
      <CategoriesManager
        v-if="activeTab === 'categories'"
        :user-permissions="userPermissions"
        @category-updated="refreshDashboard"
      />

      <!-- Vue Analytics -->
      <AnalyticsView
        v-if="activeTab === 'analytics'"
        :user-permissions="userPermissions"
      />
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import DashboardOverview from './admin/DashboardOverview.vue'
import SuggestionsManager from './admin/SuggestionsManager.vue'
import CategoriesManager from './admin/CategoriesManager.vue'
import AnalyticsView from './admin/AnalyticsView.vue'
import RefreshIcon from '@heroicons/vue/outline/RefreshIcon'

export default {
  name: 'CategoryAdminDashboard',
  components: {
    DashboardOverview,
    SuggestionsManager,
    CategoriesManager,
    AnalyticsView,
    RefreshIcon
  },
  setup() {
    const authStore = useAuthStore()
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
        permission: 'review_suggestions'
      },
      {
        id: 'categories',
        name: 'Catégories',
        icon: '🗂️',
        permission: 'manage_categories'
      },
      {
        id: 'analytics',
        name: 'Analytics',
        icon: '📈',
        permission: 'view_analytics'
      }
    ]

    // Informations utilisateur
    const userRole = computed(() => {
      const roleLabels = {
        'super_admin': 'Super Administrateur',
        'admin': 'Administrateur',
        'moderator': 'Modérateur',
        'user': 'Utilisateur'
      }
      return roleLabels[authStore.user?.role] || 'Utilisateur'
    })

    const userPermissions = computed(() => authStore.user?.permissions || [])

    // Vérifier les permissions
    const hasPermission = (permission) => {
      if (!permission) return true
      return authStore.user?.permissions?.includes(permission) || 
             authStore.user?.role === 'super_admin' ||
             (authStore.user?.role === 'admin' && ['manage_categories', 'review_suggestions', 'view_analytics'].includes(permission)) ||
             (authStore.user?.role === 'moderator' && permission === 'review_suggestions')
    }

    // Charger les données du dashboard
    const loadDashboard = async () => {
      loading.value = true
      try {
        const response = await fetch('/api/admin/dashboard/categories', {
          headers: {
            'Authorization': `Bearer ${authStore.token}`,
            'Content-Type': 'application/json'
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
        // TODO: Afficher notification d'erreur
      } finally {
        loading.value = false
      }
    }

    const refreshDashboard = () => {
      loadDashboard()
    }

    onMounted(() => {
      // Vérifier les permissions avant de charger
      if (!hasPermission(null)) {
        // Rediriger vers une page d'erreur ou afficher un message
        console.error('Permissions insuffisantes')
        return
      }
      
      loadDashboard()
    })

    return {
      loading,
      activeTab,
      stats,
      languageStats,
      recentActivity,
      tabs,
      userRole,
      userPermissions,
      hasPermission,
      refreshDashboard
    }
  }
}
</script>

<style scoped>
.category-admin-dashboard {
  min-height: 100vh;
  background-color: #f9fafb;
}

/* Animations pour les transitions */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* Styles pour les badges */
.badge {
  @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
}

.badge-red {
  @apply bg-red-100 text-red-800;
}

.badge-yellow {
  @apply bg-yellow-100 text-yellow-800;
}

.badge-green {
  @apply bg-green-100 text-green-800;
}

.badge-blue {
  @apply bg-blue-100 text-blue-800;
}
</style> 