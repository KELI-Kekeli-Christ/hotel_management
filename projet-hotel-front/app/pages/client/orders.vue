<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useOrderStore, type Order } from "~/stores/order";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
});

const orderStore = useOrderStore();

// Pagination
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const lastPage = ref(1);

const formatPrice = (price: number | null | undefined) => {
  const numPrice = price ?? 0;
  return new Intl.NumberFormat("fr-FR").format(numPrice) + " XOF";
};

const formatDate = (date?: string) =>
  date
    ? new Date(date).toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "long",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      })
    : "-";

const getStatusBadge = (status: string) => {
  const statusMap: Record<string, { color: string; label: string }> = {
    pending: { color: "amber", label: "En attente" },
    preparing: { color: "blue", label: "En préparation" },
    ready: { color: "success", label: "Prête" },
    delivered: { color: "success", label: "Livrée" },
    cancelled: { color: "error", label: "Annulée" },
  };
  return statusMap[status] || { color: "neutral", label: status };
};

const getOrderTypeLabel = (type: string) => {
  return type === "room_service" ? "Room Service" : "Restaurant";
};

const loadOrders = async () => {
  try {
    const response = await orderStore.fetchMyOrders(currentPage.value, perPage.value);
    if (response) {
      total.value = response.total || 0;
      lastPage.value = response.last_page || 1;
    }
  } catch (error) {
    console.error("Error loading orders:", error);
  }
};

onMounted(async () => {
  await loadOrders();
});
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Mes Commandes" />

    <div class="p-6 overflow-y-auto">
      <div v-if="orderStore.isLoading" class="flex justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
      </div>

      <div v-else-if="orderStore.orders.length === 0" class="text-center py-12">
        <UIcon name="i-lucide-shopping-cart" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500 dark:text-gray-400 mb-4">Vous n'avez aucune commande</p>
        <UButton
          label="Commander un service"
          color="primary"
          @click="$router.push('/client/services')"
        />
      </div>

      <div v-else class="space-y-6">
        <UCard
          v-for="order in orderStore.orders"
          :key="order.id"
          class="overflow-hidden shadow-sm hover:shadow-md transition-shadow"
        >
          <div class="p-6">
            <!-- En-tête de la commande -->
            <div class="flex items-start justify-between mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                    Commande #{{ order.id }}
                  </h3>
                  <UBadge
                    :color="getStatusBadge(order.status).color"
                    variant="subtle"
                    size="md"
                  >
                    {{ getStatusBadge(order.status).label }}
                  </UBadge>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
                  <div class="flex items-center gap-1">
                    <UIcon name="i-lucide-utensils-crossed" class="w-4 h-4" />
                    <span>{{ getOrderTypeLabel(order.order_type) }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <UIcon name="i-lucide-map-pin" class="w-4 h-4" />
                    <span>{{ order.location }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <UIcon name="i-lucide-calendar" class="w-4 h-4" />
                    <span>{{ formatDate(order.created_at) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Articles de la commande -->
            <div class="space-y-3 mb-6">
              <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Articles commandés</h4>
              <div
                v-for="item in order.order_items"
                :key="item.id"
                class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700"
              >
                <div class="flex-1">
                  <p class="font-semibold text-base text-gray-900 dark:text-white mb-1">
                    {{ item.menu_item?.name || "Article supprimé" }}
                  </p>
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Quantité: {{ item.quantity }} × {{ formatPrice(item.unit_price ?? 0) }}
                  </p>
                </div>
                <p class="font-bold text-lg text-gray-900 dark:text-white ml-4">
                  {{ formatPrice(item.line_total ?? 0) }}
                </p>
              </div>
            </div>

            <!-- Note du client -->
            <div v-if="order.note" class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
              <div class="flex items-start gap-2">
                <UIcon name="i-lucide-message-square" class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                <div class="flex-1">
                  <p class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-1">Note</p>
                  <p class="text-sm text-blue-800 dark:text-blue-200 leading-relaxed">{{ order.note }}</p>
                </div>
              </div>
            </div>

            <!-- Total -->
            <div class="flex items-center justify-between pt-4 border-t-2 border-gray-200 dark:border-gray-700">
              <p class="text-lg font-semibold text-gray-700 dark:text-gray-300">Total</p>
              <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                {{ formatPrice(order.total ?? 0) }}
              </p>
            </div>
          </div>
        </UCard>
      </div>

      <!-- Pagination -->
      <div v-if="!orderStore.isLoading && orderStore.orders.length > 0 && lastPage > 1" class="flex justify-center mt-6 pb-6">
        <UPagination
          v-model="currentPage"
          :page-count="lastPage"
          :total="total"
          :per-page="perPage"
          @update:model-value="loadOrders"
        />
      </div>
    </div>
  </UDashboardPanel>
</template>

