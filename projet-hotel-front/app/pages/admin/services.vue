<script setup lang="ts">
import { ref, onMounted, computed, nextTick } from "vue";
import { useOrderStore, type Order } from "~/stores/order";
import { useMenuItemStore, type MenuItem } from "~/stores/menuItemStore";
import { useAuthStore } from "~/stores/auth";
import { useUserStore } from "~/stores/userStore";
import AdminMenuFormModal from "~/components/admin/menu/FormModal.vue";
import ConfirmModal from "~/components/admin/chambres/ConfirmModal.vue";
import CustomersCreateOrderModal from "~/components/customers/CreateOrderModal.vue";
import { useStatsStore } from "~/stores/statsStore";
import servicePlaceholder from "~/assets/images/services_placeholder.png";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
  permission: "service-order-manage",
});

const authStore = useAuthStore();
const orderStore = useOrderStore();
const menuItemStore = useMenuItemStore();
const userStore = useUserStore();
const statsStore = useStatsStore();

// --- Configuration des onglets ---
const tabs = [
  { key: "orders", label: "Commandes", permission: "service-order-manage" },
  { key: "menu", label: "Menu", permission: "service-menu-manage" },
  { key: "stats", label: "Statistiques", permission: "service-stats-view" },
];

const availableTabs = computed(() =>
  tabs.filter((tab) => authStore.can(tab.permission))
);

// CORRECTION CLÉE : Initialisation APRÈS le computed
const activeTab = ref<string>("");

// Force le re-rendu avec une clé unique
const componentKey = ref(0);

function forceRerender() {
  componentKey.value += 1;
}

onMounted(async () => {
  // Attendre que Vue soit prêt
  await nextTick();

  // Définir l'onglet actif
  activeTab.value = availableTabs.value[0]?.key ?? "orders";

  console.log("🚀 Initialisation:", {
    ongletActif: activeTab.value,
    ongletsDisponibles: availableTabs.value.map((t) => t.key),
  });

  // Charger les données
  if (authStore.can("service-order-manage")) {
    await loadOrders();
    console.log("✅ Commandes chargées:", orderStore.orders?.length);
  }
  if (authStore.can("service-menu-manage")) {
    await menuItemStore.fetchAll();
  }
  if (authStore.can("service-stats-view")) {
    await statsStore.fetchStats();
  }

  // Forcer un re-rendu
  forceRerender();
});

// Computed pour garantir la réactivité
const orders = computed(() => {
  console.log(
    "📊 Computed orders appelé, longueur:",
    orderStore.orders?.length
  );
  return orderStore.orders || [];
});

const menuItems = computed(() => menuItemStore.items || []);
const stats = computed(() => statsStore.data);

// --- Modales pour le Menu ---
const isMenuFormModalOpen = ref(false);
const isConfirmDeleteModalOpen = ref(false);
const selectedMenuItem = ref<MenuItem | null>(null);
const itemToDeleteId = ref<number | null>(null);

// --- Modale pour créer une commande ---
const isCreateOrderModalOpen = ref(false);

function openCreateOrderModal() {
  // Charger les utilisateurs si nécessaire
  if (userStore.users.length === 0) {
    userStore.fetchAll().catch(() => {});
  }
  isCreateOrderModalOpen.value = true;
}

async function handleOrderCreated() {
  // Rafraîchir la liste des commandes
  await loadOrders();
  forceRerender();
}

function openCreateMenuItemModal() {
  selectedMenuItem.value = null;
  isMenuFormModalOpen.value = true;
}

function openEditMenuItemModal(item: MenuItem) {
  selectedMenuItem.value = item;
  isMenuFormModalOpen.value = true;
}

function askDeleteItem(id: number) {
  itemToDeleteId.value = id;
  isConfirmDeleteModalOpen.value = true;
}

async function handleDeleteMenuItem() {
  if (itemToDeleteId.value === null) return;
  try {
    await menuItemStore.remove(itemToDeleteId.value);
    useToast().add({ title: "Article supprimé", color: "success" });
  } catch (error: any) {
    useToast().add({
      title: "Erreur",
      description: error.response?.data?.message,
      color: "error",
    });
  } finally {
    isConfirmDeleteModalOpen.value = false;
    itemToDeleteId.value = null;
  }
}

const menuActions = (item: MenuItem) => [
  [
    {
      label: "Modifier",
      icon: "i-heroicons-pencil-20-solid",
      click: () => openEditMenuItemModal(item),
    },
  ],
  [
    {
      label: "Supprimer",
      icon: "i-heroicons-trash-20-solid",
      click: () => askDeleteItem(item.id),
    },
  ],
];

// --- Utilitaires ---
const formatPrice = (value: number | null | undefined) => {
  const numValue = value ?? 0;
  return new Intl.NumberFormat("fr-FR").format(numValue) + " XOF";
};

const formatDate = (date: string) =>
  new Date(date).toLocaleTimeString("fr-FR", {
    hour: "2-digit",
    minute: "2-digit",
  });

// --- Actions sur les commandes ---
const toast = useToast();

async function handleUpdateStatus(order: Order, newStatus: string) {
  try {
    await orderStore.updateStatus(order.id, newStatus);
    toast.add({
      title: `Commande #${order.id} mise à jour`,
      color: "success",
    });
    await loadOrders();
    forceRerender();
  } catch (error) {
    console.error("Erreur handleUpdateStatus:", error);
    toast.add({ title: "Erreur de mise à jour", color: "error" });
  }
}

// Fonction pour changer d'onglet avec log
function changeTab(tabKey: string) {
  console.log("🔄 Changement d'onglet:", tabKey);
  activeTab.value = tabKey;
  console.log("📊 Nombre de commandes après changement:", orders.value.length);
}
</script>

<template>
  <UDashboardPanel grow class="flex flex-col min-h-0" :key="componentKey">
    <UDashboardNavbar title="Gestion des Services">
      <template #right>
        <UButton
          v-if="activeTab === 'orders' && authStore.can('service-order-manage')"
          label="Créer une commande"
          icon="i-heroicons-plus"
          @click="openCreateOrderModal"
        />
        <UButton
          v-if="activeTab === 'menu' && authStore.can('service-menu-manage')"
          label="Nouvel Article"
          icon="i-heroicons-plus"
          @click="openCreateMenuItemModal"
        />
      </template>
    </UDashboardNavbar>

    <!-- Navigation manuelle des onglets -->
    <div class="border-b border-gray-200 dark:border-gray-800 px-4">
      <div class="flex gap-1">
        <button
          v-for="tab in availableTabs"
          :key="tab.key"
          @click="changeTab(tab.key)"
          :class="[
            'px-4 py-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === tab.key
              ? 'border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950'
              : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300',
          ]"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto">
      <!-- Onglet Commandes -->
      <div v-show="activeTab === 'orders'" class="p-4">
        <div v-if="orderStore.isLoading" class="p-4 text-center">
          <div
            class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"
          ></div>
          <p class="mt-2">Chargement des commandes...</p>
        </div>
        <div
          v-else-if="orders.length === 0"
          class="p-8 text-center text-gray-500"
        >
          <p>Aucune commande pour le moment.</p>
          <p class="text-sm mt-2">Les nouvelles commandes apparaîtront ici.</p>
        </div>
        <div
          v-else
          class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
        >
          <UCard
            v-for="order in orders"
            :key="`order-${order.id}-${componentKey}`"
            class="flex flex-col p-4"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <h4 class="font-semibold">Commande #{{ order.id }}</h4>
                <p class="text-xs text-gray-500">{{ order.location ?? "-" }}</p>
                <p v-if="order.user" class="text-xs text-gray-600 mt-1">
                  Client: {{ order.user.name }} ({{ order.user.email }})
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  {{ formatDate(order.created_at) }}
                </p>
              </div>
              <UBadge
                :color="
                  order.status === 'ready' || order.status === 'delivered'
                    ? 'success'
                    : order.status === 'cancelled'
                    ? 'error'
                    : order.status === 'preparing'
                    ? 'blue'
                    : 'neutral'
                "
                variant="subtle"
              >
                {{ order.status === 'pending' ? 'En attente' : order.status === 'preparing' ? 'En préparation' : order.status === 'ready' ? 'Prête' : order.status === 'delivered' ? 'Livrée' : order.status === 'cancelled' ? 'Annulée' : order.status }}
              </UBadge>
            </div>

            <div class="mt-3 flex-1">
              <p class="text-sm text-gray-600">
                Articles: {{ order.order_items?.length ?? 0 }}
              </p>
              <p class="text-lg font-semibold mt-2">
                {{ formatPrice(order.total ?? 0) }}
              </p>
            </div>

            <div v-if="order.note" class="mt-3 p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
              <p class="text-xs font-medium text-blue-800 dark:text-blue-200 mb-1">Note du client:</p>
              <p class="text-xs text-blue-700 dark:text-blue-300">{{ order.note }}</p>
            </div>
          </UCard>
        </div>

        <!-- Pagination -->
        <div v-if="!orderStore.isLoading && orders.length > 0 && lastPageOrders > 1" class="flex justify-center mt-6 pb-6">
          <UPagination
            v-model="currentPage"
            :page-count="lastPageOrders"
            :total="totalOrders"
            :per-page="perPage"
            @update:model-value="loadOrders"
          />
        </div>
      </div>

      <!-- Onglet Menu -->
      <div v-show="activeTab === 'menu'" class="p-4">
        <div v-if="menuItemStore.isLoading" class="p-4 text-center">
          <div
            class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"
          ></div>
          <p class="mt-2">Chargement du menu...</p>
        </div>
        <div
          v-else-if="menuItems.length === 0"
          class="p-8 text-center text-gray-500"
        >
          <p>Le menu est vide.</p>
          <UButton
            v-if="authStore.can('service-menu-manage')"
            class="mt-4"
            label="Ajouter le premier article"
            @click="openCreateMenuItemModal"
          />
        </div>

        <div
          v-else
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4"
        >
          <UCard
            v-for="item in menuItems"
            :key="`menu-${item.id}`"
            class="p-2 sm:p-2"
          >
            <template #header>
              <div class="flex justify-between items-center">
                <h3 class="font-bold text-base truncate">{{ item.name }}</h3>

                <UDropdownMenu
                  v-if="authStore.can('service-menu-manage')"
                  :items="menuActions(item)"
                  :ui="{ content: 'w-40' }"
                  arrow
                >
                  <UButton
                    color="neutral"
                    variant="ghost"
                    icon="i-heroicons-ellipsis-vertical-20-solid"
                    aria-label="Actions article"
                  />
                </UDropdownMenu>
              </div>
            </template>

            <img
              :src="item.image_url || servicePlaceholder"
              :alt="item.name"
              class="w-full h-32 object-cover rounded-md mb-2"
            />

            <div class="flex justify-between items-center">
              <p class="text-xs text-gray-500">{{ item.category }}</p>
              <UBadge
                :color="item.available ? 'success' : 'neutral'"
                variant="subtle"
              >
                {{ item.available ? "En stock" : "Épuisé" }}
              </UBadge>
            </div>
            <p class="font-semibold mt-1">{{ formatPrice(item.price) }}</p>
          </UCard>
        </div>
      </div>

      <!-- Statistiques -->
      <div v-show="activeTab === 'stats'" class="p-4">
        <div v-if="statsStore.isLoading || !stats" class="text-center p-8">
          <div
            class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"
          ></div>
          <p class="mt-2">Chargement des statistiques...</p>
        </div>
        <div v-else class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <UCard>
              <template #header>Chiffre d'affaires (jour)</template>
              <p class="text-2xl font-bold">
                {{ formatPrice(stats.todayRevenue ?? 0) }}
              </p>
            </UCard>
            <UCard>
              <template #header>Commandes (mois)</template>
              <p class="text-2xl font-bold">
                {{ stats.monthlyOrders ?? 0 }}
              </p>
            </UCard>
            <UCard>
              <template #header>Article le plus populaire</template>
              <p class="text-2xl font-bold truncate">
                {{ stats.topItem ?? "N/A" }}
              </p>
            </UCard>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <UCard>
              <template #header>Top 5 des articles les plus vendus</template>
              <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                <li
                  v-for="item in stats.topItems ?? []"
                  :key="item.name"
                  class="py-2 flex justify-between items-center"
                >
                  <span>{{ item.name }}</span>
                  <UBadge color="primary" variant="subtle">
                    {{ item.quantity }} ventes
                  </UBadge>
                </li>
              </ul>
            </UCard>
            <UCard>
              <template #header>5 dernières commandes</template>
              <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                <li
                  v-for="order in stats.recentOrders ?? []"
                  :key="order.id"
                  class="py-2"
                >
                  <div class="flex justify-between items-center">
                    <span>#{{ order.id }} - {{ order.location }}</span>
                    <span class="font-semibold">{{
                      formatPrice(order.total)
                    }}</span>
                  </div>
                  <p class="text-xs text-gray-500">
                    {{ formatDate(order.date) }}
                  </p>
                </li>
              </ul>
            </UCard>
          </div>
        </div>
      </div>
    </div>

    <!-- Modales -->
    <AdminMenuFormModal
      v-if="isMenuFormModalOpen"
      v-model="isMenuFormModalOpen"
      :item="selectedMenuItem"
    />
    <ConfirmModal
      v-if="isConfirmDeleteModalOpen"
      v-model="isConfirmDeleteModalOpen"
      title="Supprimer cet article ?"
      @confirm="handleDeleteMenuItem"
    />
    <CustomersCreateOrderModal
      v-if="isCreateOrderModalOpen"
      v-model="isCreateOrderModalOpen"
      @success="handleOrderCreated"
    />
  </UDashboardPanel>
</template>
