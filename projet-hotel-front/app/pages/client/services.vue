<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from "vue";
import { useMenuItemStore, type MenuItem } from "~/stores/menuItemStore";
import { useOrderStore } from "~/stores/order";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
});

const menuItemStore = useMenuItemStore();
const orderStore = useOrderStore();
const authStore = useAuthStore();

const orderType = ref<"restaurant">("restaurant");
const location = ref("");
const note = ref("");
const selectedItems = ref<
  Array<{ menu_item_id: number; quantity: number; menuItem?: MenuItem }>
>([]);
const isLoading = ref(false);
const showCategories = ref<string[]>([]);

const availableItems = computed(() => {
  return menuItemStore.items.filter((item) => item.available);
});

const categories = computed(() => {
  const cats = new Set(menuItemStore.items.map((item) => item.category));
  return Array.from(cats);
});

const itemsByCategory = computed(() => {
  const grouped: Record<string, MenuItem[]> = {};
  availableItems.value.forEach((item) => {
    if (!grouped[item.category]) {
      grouped[item.category] = [];
    }
    grouped[item.category].push(item);
  });
  return grouped;
});

const totalPrice = computed(() => {
  return selectedItems.value.reduce((sum, item) => {
    const menuItem = availableItems.value.find((mi) => mi.id === item.menu_item_id);
    return sum + (menuItem?.price || 0) * item.quantity;
  }, 0);
});

const formatPrice = (price: number | null | undefined) => {
  const numPrice = price ?? 0;
  return new Intl.NumberFormat("fr-FR").format(numPrice) + " XOF";
};

const toggleCategory = (category: string) => {
  const index = showCategories.value.indexOf(category);
  if (index > -1) {
    showCategories.value.splice(index, 1);
  } else {
    showCategories.value.push(category);
  }
};

const addItem = (item: MenuItem) => {
  const existing = selectedItems.value.find((si) => si.menu_item_id === item.id);
  if (existing) {
    existing.quantity += 1;
  } else {
    selectedItems.value.push({
      menu_item_id: item.id,
      quantity: 1,
      menuItem: item,
    });
  }
};

const removeItem = (menuItemId: number) => {
  const index = selectedItems.value.findIndex((si) => si.menu_item_id === menuItemId);
  if (index > -1) {
    selectedItems.value.splice(index, 1);
  }
};

const updateQuantity = (menuItemId: number, quantity: number) => {
  const item = selectedItems.value.find((si) => si.menu_item_id === menuItemId);
  if (item) {
    if (quantity <= 0) {
      removeItem(menuItemId);
    } else {
      item.quantity = quantity;
    }
  }
};

const handleSubmit = async () => {
  if (selectedItems.value.length === 0) {
    useToast().add({
      title: "Erreur",
      description: "Veuillez sélectionner au moins un article",
      color: "error",
    });
    return;
  }

  if (!location.value.trim()) {
    useToast().add({
      title: "Erreur",
      description: "Veuillez indiquer la localisation (ex: Chambre 101)",
      color: "error",
    });
    return;
  }

  isLoading.value = true;
  try {
    const response = await orderStore.create({
      order_type: orderType.value,
      location: location.value,
      note: note.value || undefined,
      items: selectedItems.value.map((item) => ({
        menu_item_id: item.menu_item_id,
        quantity: item.quantity,
      })),
    });

    // Si la réponse contient un redirect_url (FedaPay), rediriger vers la page de paiement
    if (response?.redirect_url) {
      useToast().add({
        title: "Redirection vers le paiement",
        description: "Vous allez être redirigé vers la page de paiement",
        color: "success",
      });
      window.location.href = response.redirect_url;
      return;
    }

    useToast().add({
      title: "Commande créée",
      description: "Votre commande a été créée avec succès",
      color: "success",
    });

    // Réinitialiser le formulaire
    selectedItems.value = [];
    location.value = "";
    note.value = "";
    
    // Rediriger vers les commandes
    setTimeout(() => {
      navigateTo("/client/orders");
    }, 1500);
  } catch (error: any) {
    useToast().add({
      title: "Erreur",
      description: error.response?.data?.message || "Impossible de créer la commande",
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
};

onMounted(async () => {
  await menuItemStore.fetchAll();
  // Afficher toutes les catégories par défaut après le chargement
  await nextTick();
  if (categories.value.length > 0) {
    showCategories.value = [...categories.value];
  }
});
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Commander un Service" />

    <div class="p-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Menu -->
        <div class="lg:col-span-2 space-y-6">
          <!-- En-tête -->
          <div class="text-center lg:text-left mb-6">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Menu du Restaurant</h2>
            <p class="text-gray-600 dark:text-gray-400">Découvrez notre sélection de plats délicieux</p>
          </div>

          <UCard class="border-0 shadow-lg">
            <div v-if="menuItemStore.isLoading" class="flex justify-center py-12">
              <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-500"></div>
            </div>
            <div v-else class="space-y-6">
              <div
                v-for="category in categories"
                :key="category"
                class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow"
              >
                <button
                  @click="toggleCategory(category)"
                  class="w-full px-6 py-4 bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-between hover:from-primary-100 hover:to-primary-200 dark:hover:from-primary-900/50 dark:hover:to-primary-800/50 transition-all"
                >
                  <div class="flex items-center gap-3">
                    <UIcon name="i-lucide-utensils-crossed" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    <span class="font-bold text-lg text-gray-900 dark:text-white">{{ category }}</span>
                  </div>
                  <UIcon
                    :name="showCategories.includes(category) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                    class="w-5 h-5 text-gray-600 dark:text-gray-400"
                  />
                </button>

                <div v-if="showCategories.includes(category)" class="p-6 bg-white dark:bg-gray-900 space-y-4">
                  <div
                    v-for="item in itemsByCategory[category]"
                    :key="item.id"
                    class="flex items-start justify-between p-4 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800/50 dark:to-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700 hover:shadow-md transition-all group"
                  >
                    <div class="flex-1">
                      <div class="flex items-start justify-between gap-4 mb-2">
                        <h4 class="font-bold text-lg text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                          {{ item.name }}
                        </h4>
                        <span class="text-xl font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap ml-4">
                          {{ formatPrice(item.price) }}
                        </span>
                      </div>
                      <p v-if="item.description" class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                        {{ item.description }}
                      </p>
                    </div>
                    <UButton
                      icon="i-lucide-plus"
                      label="Ajouter"
                      size="md"
                      color="primary"
                      class="ml-4 flex-shrink-0"
                      @click="addItem(item)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </UCard>
        </div>

        <!-- Panier -->
        <div class="lg:col-span-1">
          <UCard class="sticky top-6 border-0 shadow-xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-800">
            <template #header>
              <div class="flex items-center gap-2">
                <UIcon name="i-lucide-shopping-cart" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Votre commande</h3>
              </div>
            </template>

            <div class="space-y-6">
              <!-- Articles sélectionnés -->
              <div v-if="selectedItems.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                <UIcon name="i-lucide-shopping-cart" class="w-16 h-16 mx-auto mb-4 opacity-30" />
                <p class="font-medium">Votre panier est vide</p>
                <p class="text-sm mt-2">Ajoutez des articles pour commencer</p>
              </div>

              <div v-else class="space-y-4 max-h-[400px] overflow-y-auto pr-2">
                <div
                  v-for="item in selectedItems"
                  :key="item.menu_item_id"
                  class="flex items-start gap-3 p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow"
                >
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-gray-900 dark:text-white mb-1 truncate">
                      {{ availableItems.find((mi) => mi.id === item.menu_item_id)?.name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                      {{ formatPrice(availableItems.find((mi) => mi.id === item.menu_item_id)?.price || 0) }} × {{ item.quantity }}
                    </p>
                    <div class="flex items-center gap-2">
                      <UInput
                        :model-value="item.quantity"
                        type="number"
                        min="1"
                        class="w-20"
                        size="sm"
                        @update:model-value="updateQuantity(item.menu_item_id, Number($event))"
                      />
                      <UButton
                        icon="i-lucide-trash-2"
                        size="sm"
                        color="error"
                        variant="ghost"
                        @click="removeItem(item.menu_item_id)"
                      />
                    </div>
                  </div>
                  <p class="font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap">
                    {{ formatPrice((availableItems.find((mi) => mi.id === item.menu_item_id)?.price || 0) * item.quantity) }}
                  </p>
                </div>
              </div>

              <!-- Total -->
              <div v-if="selectedItems.length > 0" class="pt-4 border-t-2 border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center mb-6">
                  <span class="text-lg font-bold text-gray-900 dark:text-white">Total</span>
                  <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {{ formatPrice(totalPrice) }}
                  </span>
                </div>
              </div>

              <!-- Localisation -->
              <UFormField label="Localisation" required>
                <UInput
                  v-model="location"
                  placeholder="Ex: Table 5, Chambre 101..."
                  icon="i-lucide-map-pin"
                />
              </UFormField>

              <!-- Note -->
              <UFormField label="Note (optionnel)">
                <UTextarea
                  v-model="note"
                  placeholder="Instructions spéciales, allergies..."
                  :rows="3"
                  icon="i-lucide-message-square"
                />
              </UFormField>

              <!-- Bouton de commande -->
              <UButton
                label="Passer la commande"
                icon="i-lucide-credit-card"
                color="primary"
                size="lg"
                block
                :loading="isLoading"
                :disabled="selectedItems.length === 0 || !location.trim()"
                class="mt-4"
                @click="handleSubmit"
              />
            </div>
          </UCard>
        </div>
      </div>
    </div>
  </UDashboardPanel>
</template>

