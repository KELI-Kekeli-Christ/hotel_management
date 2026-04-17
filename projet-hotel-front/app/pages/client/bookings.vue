<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useReservationStore, type Reservation } from "~/stores/reservation";
import { useAuthStore } from "~/stores/auth";
import EditReservationModal from "~/components/client/EditReservationModal.vue";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
});

const reservationStore = useReservationStore();
const authStore = useAuthStore();

const reservations = ref<Reservation[]>([]);
const isLoading = ref(false);
const isEditModalOpen = ref(false);
const selectedReservation = ref<Reservation | null>(null);

// Pagination
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const lastPage = ref(1);

const canModify = (reservation: Reservation) => {
  // Permettre la modification seulement si la réservation est payée, confirmée, ou en attente
  return ['paid', 'confirmed', 'pending'].includes(reservation.status);
};

const openEditModal = (reservation: Reservation) => {
  selectedReservation.value = reservation;
  isEditModalOpen.value = true;
};

const handleEditSuccess = async () => {
  // Recharger les réservations après modification
  await loadReservations();
};

const loadReservations = async (page: number = 1) => {
  isLoading.value = true;
  try {
    const response = await (useNuxtApp().$api as any).get("/reservations/my", {
      params: {
        page,
        per_page: perPage.value
      }
    });
    reservations.value = response.data.data || [];
    total.value = response.data.total || 0;
    currentPage.value = response.data.current_page || 1;
    lastPage.value = response.data.last_page || 1;
  } catch (error) {
    console.error("Error loading reservations:", error);
    useToast().add({
      title: "Erreur",
      description: "Impossible de charger vos réservations",
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
};

const formatDate = (date?: string) =>
  date
    ? new Date(date).toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "long",
        year: "numeric",
      })
    : "-";

const formatPrice = (price: number) =>
  new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "XOF",
  }).format(price);

const getStatusBadge = (status: string) => {
  const statusMap: Record<string, { color: string; label: string }> = {
    pending: { color: "amber", label: "En attente" },
    paid: { color: "success", label: "Payée" },
    confirmed: { color: "success", label: "Confirmée" },
    cancelled: { color: "error", label: "Annulée" },
    checked_in: { color: "primary", label: "Check-in" },
    checked_out: { color: "neutral", label: "Check-out" },
    failed: { color: "error", label: "Échouée" },
  };
  return statusMap[status] || { color: "neutral", label: status };
};

onMounted(async () => {
  await loadReservations();
});
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Mes Réservations" />

    <div class="p-6 overflow-y-auto">
      <div v-if="isLoading" class="flex justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
      </div>

      <div v-else-if="reservations.length === 0" class="text-center py-12">
        <UIcon name="i-lucide-calendar-check" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500 dark:text-gray-400 mb-4">Vous n'avez aucune réservation</p>
        <UButton
          label="Voir les chambres disponibles"
          color="primary"
          @click="$router.push('/client/rooms')"
        />
      </div>

      <div v-else class="space-y-4">
        <UCard
          v-for="reservation in reservations"
          :key="reservation.id"
          class="overflow-hidden"
        >
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold">
                  {{ reservation.numero_reservation }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Chambre {{ reservation.chambre?.numero_chambre }} - {{ reservation.chambre?.type }}
                </p>
              </div>
              <UBadge
                :color="getStatusBadge(reservation.status).color"
                variant="subtle"
              >
                {{ getStatusBadge(reservation.status).label }}
              </UBadge>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Date d'arrivée</p>
                <p class="font-medium">{{ formatDate(reservation.date_arrive) }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Date de départ</p>
                <p class="font-medium">{{ formatDate(reservation.date_depart) }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Durée</p>
                <p class="font-medium">
                  {{ reservation.dure_reservation }}
                  {{ reservation.dure_reservation === 1 ? "nuit" : "nuits" }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Prix total</p>
                <p class="text-xl font-bold text-primary-600 dark:text-primary-400">
                  {{
                    formatPrice(
                      (reservation.chambre?.prix_nuite || 0) * reservation.dure_reservation
                    )
                  }}
                </p>
              </div>
              <UButton
                v-if="canModify(reservation)"
                label="Modifier"
                icon="i-heroicons-pencil"
                color="primary"
                variant="outline"
                size="sm"
                @click="openEditModal(reservation)"
              />
            </div>
          </div>
        </UCard>
      </div>

      <!-- Pagination -->
      <div v-if="!isLoading && reservations.length > 0 && lastPage > 1" class="flex justify-center mt-6">
        <UPagination
          :page="currentPage"
          :page-count="lastPage"
          :total="total"
          :per-page="perPage"
          @update:page="(page: number) => { currentPage = page; loadReservations(page); }"
        />
      </div>
    </div>

    <!-- Modal de modification -->
    <EditReservationModal
      v-if="isEditModalOpen && selectedReservation"
      v-model="isEditModalOpen"
      :reservation="selectedReservation"
      @success="handleEditSuccess"
    />
  </UDashboardPanel>
</template>
