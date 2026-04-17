<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "~/stores/auth";
import { useChambreStore } from "~/stores/chambre";
import { useReservationStore } from "~/stores/reservation";
import { useOrderStore } from "~/stores/order";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
});

const router = useRouter();
const authStore = useAuthStore();
const chambreStore = useChambreStore();
const reservationStore = useReservationStore();
const orderStore = useOrderStore();

const isLoading = ref(false);

// Statistiques pour le client
const stats = ref({
  totalReservations: 0,
  activeReservations: 0,
  totalOrders: 0,
  pendingOrders: 0,
});

onMounted(async () => {
  isLoading.value = true;
  try {
    // Charger les réservations de l'utilisateur
    const reservationsResponse = await (useNuxtApp().$api as any).get("/reservations/my");
    const reservations = reservationsResponse.data.data || [];
    
    stats.value.totalReservations = reservations.length;
    stats.value.activeReservations = reservations.filter(
      (r: any) => r.status === "confirmed" || r.status === "checked_in"
    ).length;

    // Charger les commandes de l'utilisateur
    await orderStore.fetchMyOrders();
    stats.value.totalOrders = orderStore.orders.length;
    stats.value.pendingOrders = orderStore.orders.filter(
      (o) => o.status === "pending" || o.status === "preparing"
    ).length;
  } catch (error) {
    console.error("Error loading stats:", error);
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Bienvenue" :description="`Bonjour, ${authStore.user?.name || 'Client'}`" />
    
    <div class="p-6 space-y-6">
      <!-- Statistiques rapides -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <UCard>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Mes Réservations</p>
              <p class="text-2xl font-bold">{{ stats.totalReservations }}</p>
            </div>
            <div class="p-3 bg-primary-100 dark:bg-primary-900 rounded-lg">
              <UIcon name="i-lucide-calendar-check" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
            </div>
          </div>
        </UCard>

        <UCard>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Réservations Actives</p>
              <p class="text-2xl font-bold">{{ stats.activeReservations }}</p>
            </div>
            <div class="p-3 bg-success-100 dark:bg-success-900 rounded-lg">
              <UIcon name="i-lucide-calendar-days" class="w-6 h-6 text-success-600 dark:text-success-400" />
            </div>
          </div>
        </UCard>

        <UCard>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Mes Commandes</p>
              <p class="text-2xl font-bold">{{ stats.totalOrders }}</p>
            </div>
            <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-lg">
              <UIcon name="i-lucide-shopping-cart" class="w-6 h-6 text-orange-600 dark:text-orange-400" />
            </div>
          </div>
        </UCard>

        <UCard>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Commandes en Attente</p>
              <p class="text-2xl font-bold">{{ stats.pendingOrders }}</p>
            </div>
            <div class="p-3 bg-amber-100 dark:bg-amber-900 rounded-lg">
              <UIcon name="i-lucide-clock" class="w-6 h-6 text-amber-600 dark:text-amber-400" />
            </div>
          </div>
        </UCard>
      </div>

      <!-- Actions rapides -->
      <UCard>
        <template #header>
          <h3 class="text-lg font-semibold">Actions rapides</h3>
        </template>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <UButton
            label="Voir les Chambres"
            icon="i-lucide-bed-double"
            color="primary"
            size="lg"
            block
            @click="router.push('/client/rooms')"
          />
          <UButton
            label="Mes Réservations"
            icon="i-lucide-calendar-check"
            color="neutral"
            variant="outline"
            size="lg"
            block
            @click="router.push('/client/bookings')"
          />
          <UButton
            label="Commander un Service"
            icon="i-lucide-concierge-bell"
            color="neutral"
            variant="outline"
            size="lg"
            block
            @click="router.push('/client/services')"
          />
        </div>
      </UCard>
    </div>
  </UDashboardPanel>
</template>

