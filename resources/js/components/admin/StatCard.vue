<template>
  <div class="stat-card bg-white rounded-lg shadow p-6 transition-all duration-200 hover:shadow-lg"
       :class="[
         alert ? 'ring-2 ring-red-200' : '',
         loading ? 'animate-pulse' : ''
       ]">
    <div class="flex items-center">
      <div class="flex-shrink-0">
        <div class="w-12 h-12 rounded-lg flex items-center justify-center text-2xl"
             :class="iconClasses">
          <span v-if="!loading">{{ icon }}</span>
          <div v-else class="w-6 h-6 bg-gray-200 rounded"></div>
        </div>
      </div>
      
      <div class="ml-4 flex-1">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 truncate">
              {{ title }}
            </p>
            <div class="mt-1">
              <div v-if="loading" class="h-8 w-16 bg-gray-200 rounded"></div>
              <p v-else class="text-2xl font-semibold text-gray-900">
                {{ formattedValue }}
              </p>
            </div>
          </div>
          
          <!-- Badge d'alerte -->
          <div v-if="alert && !loading" class="flex-shrink-0">
            <div class="flex items-center justify-center w-6 h-6 bg-red-100 rounded-full">
              <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
            </div>
          </div>
        </div>
        
        <!-- Indicateur de changement -->
        <div v-if="change && !loading" class="mt-2 flex items-center text-sm"
             :class="change > 0 ? 'text-green-600' : 'text-red-600'">
          <span class="mr-1">
            {{ change > 0 ? '↗️' : '↘️' }}
          </span>
          <span>{{ Math.abs(change) }}%</span>
          <span class="text-gray-500 ml-1">vs. période précédente</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'

export default {
  name: 'StatCard',
  props: {
    title: {
      type: String,
      required: true
    },
    value: {
      type: [Number, String],
      required: true
    },
    icon: {
      type: String,
      required: true
    },
    color: {
      type: String,
      default: 'blue',
      validator: (value) => ['blue', 'green', 'yellow', 'red', 'purple', 'indigo'].includes(value)
    },
    alert: {
      type: Boolean,
      default: false
    },
    loading: {
      type: Boolean,
      default: false
    },
    change: {
      type: Number,
      default: null
    }
  },
  setup(props) {
    const iconClasses = computed(() => {
      const colorClasses = {
        blue: 'bg-blue-100 text-blue-600',
        green: 'bg-green-100 text-green-600',
        yellow: 'bg-yellow-100 text-yellow-600',
        red: 'bg-red-100 text-red-600',
        purple: 'bg-purple-100 text-purple-600',
        indigo: 'bg-indigo-100 text-indigo-600'
      }
      return colorClasses[props.color] || colorClasses.blue
    })

    const formattedValue = computed(() => {
      if (typeof props.value === 'number') {
        // Formatage des nombres avec séparateurs de milliers
        return props.value.toLocaleString('fr-FR')
      }
      return props.value
    })

    return {
      iconClasses,
      formattedValue
    }
  }
}
</script>

<style scoped>
.stat-card {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid #e2e8f0;
}

.stat-card:hover {
  transform: translateY(-1px);
}

/* Animation pour l'effet de pulsation des alertes */
@keyframes pulse-ring {
  0% {
    transform: scale(1);
    opacity: 1;
  }
  100% {
    transform: scale(1.3);
    opacity: 0;
  }
}

.animate-pulse-ring {
  animation: pulse-ring 2s infinite;
}
</style> 