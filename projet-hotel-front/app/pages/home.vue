<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

definePageMeta({
  layout: false,
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

const hotelInfo = ref<any>(null)
const chambres = ref<any[]>([])
const menuItems = ref<any[]>([])
const isLoading = ref(true)

const fetchHotelConfig = async () => {
  try {
    const response = await $fetch(`${apiBase}/hotel-config`)
    hotelInfo.value = response
  } catch (error) {
    console.error('Error fetching hotel config:', error)
  }
}

const fetchChambres = async () => {
  try {
    const response = await $fetch(`${apiBase}/chambres?per_page=100&disponible=true`)
    chambres.value = response.data || response || []
  } catch (error) {
    console.error('Error fetching chambres:', error)
  }
}

const fetchMenuItems = async () => {
  try {
    const response = await $fetch(`${apiBase}/menu-items?per_page=100`)
    menuItems.value = response.data || response || []
  } catch (error) {
    console.error('Error fetching menu items:', error)
  }
}

const menuItemsByCategory = computed(() => {
  const grouped: Record<string, any[]> = {}
  menuItems.value.forEach(item => {
    const category = item.category || 'Autres'
    if (!grouped[category]) {
      grouped[category] = []
    }
    grouped[category].push(item)
  })
  return grouped
})

const formatPrice = (price: number) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0
  }).format(price)
}

const getImageUrl = (path: string | null) => {
  if (!path) return null
  if (path.startsWith('http')) return path
  if (path.startsWith('/storage/')) {
    return `${apiBase.replace('/api', '')}${path}`
  }
  return `${apiBase.replace('/api', '')}/storage/${path}`
}

onMounted(async () => {
  isLoading.value = true
  await Promise.all([
    fetchHotelConfig(),
    fetchChambres(),
    fetchMenuItems()
  ])
  isLoading.value = false
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
    <!-- Header avec effet glassmorphism -->
    <header class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-md shadow-lg sticky top-0 z-50 border-b border-gray-200/50 dark:border-gray-700/50">
      <div class="container mx-auto px-4 py-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="relative">
              <div class="absolute inset-0 bg-gradient-to-r from-primary-500 to-primary-600 rounded-lg blur opacity-50"></div>
              <h1 class="text-3xl font-bold bg-gradient-to-r from-primary-600 to-indigo-600 bg-clip-text text-transparent relative">
                {{ hotelInfo?.name || 'Hôtel de Luxe' }}
              </h1>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <NuxtLink
              to="/login"
              class="px-6 py-2.5 text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition-all duration-300 hover:scale-105"
            >
              Connexion
            </NuxtLink>
            <NuxtLink
              to="/register"
              class="px-6 py-2.5 bg-gradient-to-r from-primary-600 to-indigo-600 text-white rounded-lg hover:from-primary-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105 font-semibold"
            >
              Réserver maintenant
            </NuxtLink>
          </div>
        </div>
      </div>
    </header>

    <!-- Hero Section avec animation -->
    <section class="relative overflow-hidden py-24 md:py-32">
      <!-- Fond animé -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-500/10 via-indigo-500/10 to-purple-500/10"></div>
      <div class="absolute inset-0 pattern-bg opacity-20"></div>
      
      <div class="container mx-auto px-4 text-center relative z-10">
        <div class="animate-fade-in-up">
          <h2 class="text-5xl md:text-7xl font-bold mb-6 bg-gradient-to-r from-gray-900 via-primary-700 to-indigo-700 dark:from-white dark:via-primary-400 dark:to-indigo-400 bg-clip-text text-transparent leading-tight">
            Bienvenue à<br/>
            <span class="bg-gradient-to-r from-primary-600 to-indigo-600 bg-clip-text text-transparent">
              {{ hotelInfo?.name || 'notre Hôtel' }}
            </span>
          </h2>
          <p class="text-xl md:text-2xl mb-8 text-gray-700 dark:text-gray-300 max-w-3xl mx-auto leading-relaxed">
            {{ hotelInfo?.description || 'Votre séjour de rêve commence ici. Luxe, confort et hospitalité exceptionnelle vous attendent.' }}
          </p>
          <div class="flex flex-wrap justify-center gap-6 text-sm md:text-base">
            <div v-if="hotelInfo?.address" class="flex items-center gap-3 px-4 py-2 bg-white/60 dark:bg-gray-800/60 backdrop-blur-sm rounded-full shadow-md hover:shadow-lg transition-shadow">
              <UIcon name="i-lucide-map-pin" class="w-5 h-5 text-primary-600" />
              <span class="text-gray-700 dark:text-gray-300">{{ hotelInfo.address }}</span>
            </div>
            <div v-if="hotelInfo?.phone" class="flex items-center gap-3 px-4 py-2 bg-white/60 dark:bg-gray-800/60 backdrop-blur-sm rounded-full shadow-md hover:shadow-lg transition-shadow">
              <UIcon name="i-lucide-phone" class="w-5 h-5 text-primary-600" />
              <span class="text-gray-700 dark:text-gray-300">{{ hotelInfo.phone }}</span>
            </div>
            <div v-if="hotelInfo?.email" class="flex items-center gap-3 px-4 py-2 bg-white/60 dark:bg-gray-800/60 backdrop-blur-sm rounded-full shadow-md hover:shadow-lg transition-shadow">
              <UIcon name="i-lucide-mail" class="w-5 h-5 text-primary-600" />
              <span class="text-gray-700 dark:text-gray-300">{{ hotelInfo.email }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Loading State -->
    <div v-if="isLoading" class="container mx-auto px-4 py-20 text-center">
      <div class="inline-block animate-spin rounded-full h-16 w-16 border-4 border-primary-600 border-t-transparent"></div>
      <p class="mt-6 text-gray-600 dark:text-gray-400 text-lg">Chargement de nos meilleures offres...</p>
    </div>

    <!-- Chambres Section -->
    <section v-else class="container mx-auto px-4 py-20">
      <div class="text-center mb-16">
        <h2 class="text-4xl md:text-5xl font-bold mb-4 bg-gradient-to-r from-gray-900 to-gray-700 dark:from-white dark:to-gray-300 bg-clip-text text-transparent">
          Nos Chambres Exclusives
        </h2>
        <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
          Découvrez nos suites élégantes où chaque détail a été pensé pour votre confort
        </p>
      </div>
      
      <div v-if="chambres.length === 0" class="text-center py-20">
        <div class="inline-block p-6 bg-white/60 dark:bg-gray-800/60 backdrop-blur-sm rounded-2xl shadow-lg">
          <UIcon name="i-lucide-bed-double" class="w-20 h-20 mx-auto text-gray-400 mb-4" />
          <p class="text-gray-600 dark:text-gray-400 text-lg">Aucune chambre disponible pour le moment</p>
        </div>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div
          v-for="(chambre, index) in chambres"
          :key="chambre.id"
          class="group bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2"
          :style="{ animationDelay: `${index * 0.1}s` }"
        >
          <div class="relative h-64 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-600 overflow-hidden">
            <img
              v-if="getImageUrl(chambre.photo_url || chambre.photo)"
              :src="getImageUrl(chambre.photo_url || chambre.photo)"
              :alt="`Chambre ${chambre.numero_chambre}`"
              class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
            />
            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-100 to-indigo-100 dark:from-primary-900 dark:to-indigo-900">
              <UIcon name="i-lucide-bed-double" class="w-20 h-20 text-primary-400" />
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div
              class="absolute top-4 right-4 px-4 py-2 rounded-full text-sm font-semibold shadow-lg backdrop-blur-sm transition-transform duration-300 group-hover:scale-110"
              :class="chambre.disponibilite ? 'bg-green-500/90 text-white' : 'bg-red-500/90 text-white'"
            >
              {{ chambre.disponibilite ? 'Disponible' : 'Occupée' }}
            </div>
          </div>
          <div class="p-6">
            <h3 class="text-2xl font-bold mb-2 text-gray-900 dark:text-white">
              Chambre {{ chambre.numero_chambre }}
            </h3>
            <p class="text-primary-600 dark:text-primary-400 font-semibold mb-3">
              {{ chambre.type || 'Standard' }}
            </p>
            <p v-if="chambre.description" class="text-sm text-gray-600 dark:text-gray-400 mb-6 line-clamp-3">
              {{ chambre.description }}
            </p>
            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
              <div>
                <span class="text-3xl font-bold text-primary-600 dark:text-primary-400">
                  {{ formatPrice(chambre.prix_nuite || 0) }}
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-500 ml-2">/ nuit</span>
              </div>
              <NuxtLink
                to="/register"
                class="px-6 py-3 bg-gradient-to-r from-primary-600 to-indigo-600 text-white rounded-lg hover:from-primary-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105 font-semibold"
              >
                Réserver
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Services Section -->
    <section v-if="menuItems.length > 0" class="bg-gradient-to-br from-gray-100 via-blue-50 to-indigo-50 dark:from-gray-800 dark:via-gray-900 dark:to-gray-800 py-20">
      <div class="container mx-auto px-4">
        <div class="text-center mb-16">
          <h2 class="text-4xl md:text-5xl font-bold mb-4 bg-gradient-to-r from-gray-900 to-gray-700 dark:from-white dark:to-gray-300 bg-clip-text text-transparent">
            Nos Services Premium
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Une expérience culinaire et de bien-être exceptionnelle
          </p>
        </div>

        <div
          v-for="(items, category) in menuItemsByCategory"
          :key="category"
          class="mb-16"
        >
          <div class="flex items-center gap-4 mb-8">
            <div class="h-1 flex-1 bg-gradient-to-r from-transparent via-primary-500 to-transparent"></div>
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white px-6 py-2 bg-white/60 dark:bg-gray-800/60 backdrop-blur-sm rounded-full shadow-lg">
              {{ category }}
            </h3>
            <div class="h-1 flex-1 bg-gradient-to-r from-transparent via-primary-500 to-transparent"></div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="(item, index) in items"
              :key="item.id"
              class="group bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1"
            >
              <div class="relative h-48 bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-600 overflow-hidden">
                <img
                  v-if="getImageUrl(item.image_url || item.image)"
                  :src="getImageUrl(item.image_url || item.image)"
                  :alt="item.name"
                  class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                />
                <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-amber-100 to-orange-100 dark:from-amber-900 dark:to-orange-900">
                  <UIcon name="i-lucide-utensils" class="w-16 h-16 text-amber-400" />
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
              </div>
              <div class="p-5">
                <h4 class="text-xl font-bold mb-2 text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                  {{ item.name }}
                </h4>
                <p v-if="item.description" class="text-sm text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                  {{ item.description }}
                </p>
                <div class="flex items-center justify-between">
                  <span class="text-2xl font-bold bg-gradient-to-r from-primary-600 to-indigo-600 bg-clip-text text-transparent">
                    {{ formatPrice(item.price || 0) }}
                  </span>
                  <UBadge
                    v-if="item.available"
                    color="success"
                    variant="subtle"
                    class="font-semibold"
                  >
                    Disponible
                  </UBadge>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer élégant -->
    <footer class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white py-16">
      <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
          <div>
            <h3 class="text-2xl font-bold mb-4 bg-gradient-to-r from-primary-400 to-indigo-400 bg-clip-text text-transparent">
              {{ hotelInfo?.name || 'Hôtel' }}
            </h3>
            <p class="text-gray-400 leading-relaxed">{{ hotelInfo?.description || '' }}</p>
          </div>
          <div>
            <h4 class="font-bold text-lg mb-6 text-primary-300">Contact</h4>
            <ul class="space-y-4 text-gray-400">
              <li v-if="hotelInfo?.address" class="flex items-start gap-3">
                <UIcon name="i-lucide-map-pin" class="w-5 h-5 text-primary-400 mt-1 flex-shrink-0" />
                <span>{{ hotelInfo.address }}</span>
              </li>
              <li v-if="hotelInfo?.phone" class="flex items-center gap-3">
                <UIcon name="i-lucide-phone" class="w-5 h-5 text-primary-400 flex-shrink-0" />
                <span>{{ hotelInfo.phone }}</span>
              </li>
              <li v-if="hotelInfo?.email" class="flex items-center gap-3">
                <UIcon name="i-lucide-mail" class="w-5 h-5 text-primary-400 flex-shrink-0" />
                <span>{{ hotelInfo.email }}</span>
              </li>
            </ul>
          </div>
          <div>
            <h4 class="font-bold text-lg mb-6 text-primary-300">Navigation</h4>
            <ul class="space-y-4 text-gray-400">
              <li>
                <NuxtLink to="/home" class="hover:text-primary-400 transition-colors duration-300 flex items-center gap-2">
                  <UIcon name="i-lucide-home" class="w-4 h-4" />
                  Accueil
                </NuxtLink>
              </li>
              <li>
                <NuxtLink to="/login" class="hover:text-primary-400 transition-colors duration-300 flex items-center gap-2">
                  <UIcon name="i-lucide-log-in" class="w-4 h-4" />
                  Connexion
                </NuxtLink>
              </li>
              <li>
                <NuxtLink to="/register" class="hover:text-primary-400 transition-colors duration-300 flex items-center gap-2">
                  <UIcon name="i-lucide-user-plus" class="w-4 h-4" />
                  Inscription
                </NuxtLink>
              </li>
            </ul>
          </div>
        </div>
        <div class="border-t border-gray-700 pt-8 text-center">
          <p class="text-gray-400">
            &copy; {{ new Date().getFullYear() }} {{ hotelInfo?.name || 'Hôtel' }}. Tous droits réservés.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
@keyframes fade-in-up {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in-up {
  animation: fade-in-up 0.8s ease-out;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.pattern-bg {
  background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  background-repeat: repeat;
}

/* Effet de brillance au survol */
.group:hover {
  position: relative;
}

.group::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.5s;
  z-index: 1;
  pointer-events: none;
}

.group:hover::before {
  left: 100%;
}
</style>
