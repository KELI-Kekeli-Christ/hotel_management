<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useChambreStore, type Chambre } from "~/stores/chambre";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
});

const router = useRouter();
const chambreStore = useChambreStore();
const authStore = useAuthStore();

const searchQuery = ref("");
const showOnlyAvailable = ref(true);
const selectedChambre = ref<Chambre | null>(null);
const showBookingModal = ref(false);

const availableChambres = computed(() => {
  let chambres = chambreStore.chambres;
  
  // Filtrer par disponibilité
  if (showOnlyAvailable.value) {
    chambres = chambres.filter((c) => c.disponibilite === true || c.disponibilite === 1);
  }
  
  // Filtrer par recherche
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    chambres = chambres.filter(
      (c) =>
        String(c.numero_chambre).toLowerCase().includes(query) ||
        c.type?.toLowerCase().includes(query) ||
        c.description?.toLowerCase().includes(query)
    );
  }
  
  return chambres;
});

onMounted(async () => {
  await chambreStore.fetchAll();
});

const openBookingModal = (chambre: Chambre) => {
  selectedChambre.value = chambre;
  showBookingModal.value = true;
};

const handleBookingSuccess = async () => {
  showBookingModal.value = false;
  await chambreStore.fetchAll(true); // Force le rechargement pour mettre à jour les disponibilités
  router.push('/client/bookings');
};

const formatPrice = (price: number) =>
  new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "XOF",
  }).format(price);
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Nos Chambres">
      <template #right>
        <UInput
          v-model="searchQuery"
          icon="i-lucide-search"
          placeholder="Rechercher une chambre..."
          class="w-64"
        />
      </template>
    </UDashboardNavbar>

    <div class="p-6 space-y-6">
      <!-- Filtres -->
      <div class="flex items-center gap-4">
        <UCheckbox
          v-model="showOnlyAvailable"
          label="Afficher uniquement les chambres disponibles"
        />
      </div>

      <!-- Liste des chambres -->
      <div v-if="chambreStore.isLoading" class="flex justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
      </div>

      <div v-else-if="availableChambres.length === 0" class="text-center py-12">
        <UIcon name="i-lucide-bed-double" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500 dark:text-gray-400">Aucune chambre trouvée</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <UCard
          v-for="chambre in availableChambres"
          :key="chambre.id"
          class="overflow-hidden hover:shadow-lg transition-shadow"
        >
          <div class="relative h-48 bg-gray-200 dark:bg-gray-800">
            <img
              v-if="chambre.photo_url"
              :src="chambre.photo_url"
              :alt="`Chambre ${chambre.numero_chambre}`"
              class="w-full h-full object-cover"
            />
            <div v-else class="flex items-center justify-center h-full">
              <UIcon name="i-lucide-bed-double" class="w-16 h-16 text-gray-400" />
            </div>
            <UBadge
              :color="chambre.disponibilite ? 'success' : 'neutral'"
              class="absolute top-2 right-2"
            >
              {{ chambre.disponibilite ? "Disponible" : "Indisponible" }}
            </UBadge>
          </div>

          <div class="p-4 space-y-3">
            <div>
              <h3 class="text-lg font-semibold">Chambre {{ chambre.numero_chambre }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ chambre.type }}</p>
            </div>

            <p v-if="chambre.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
              {{ chambre.description }}
            </p>

            <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Prix par nuit</p>
                <p class="text-xl font-bold text-primary-600 dark:text-primary-400">
                  {{ formatPrice(chambre.prix_nuite) }}
                </p>
              </div>
              <UButton
                :disabled="!chambre.disponibilite"
                color="primary"
                @click="openBookingModal(chambre)"
              >
                Réserver
              </UButton>
            </div>
          </div>
        </UCard>
      </div>
    </div>

    <!-- Modal de réservation -->
    <UModal 
      v-model:open="showBookingModal" 
      title="Réserver une chambre"
      description="Sélectionnez vos dates d'arrivée et de départ pour réserver cette chambre"
    >
      <template #body>
        <ClientBookingModal
          v-if="selectedChambre"
          :chambre="selectedChambre"
          @success="handleBookingSuccess"
          @close="showBookingModal = false"
        />
      </template>
    </UModal>
  </UDashboardPanel>
</template>

