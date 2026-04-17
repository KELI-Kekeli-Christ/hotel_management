<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from "vue";
import { useOrderStore } from "~/stores/order";
import { useMenuItemStore, type MenuItem } from "~/stores/menuItemStore";
import { useUserStore } from "~/stores/userStore";
import type { User } from "~/types";

const props = defineProps<{
  modelValue: boolean;
  customer?: User | null;
}>();

const emit = defineEmits(["update:modelValue", "success"]);

const orderStore = useOrderStore();
const menuItemStore = useMenuItemStore();
const userStore = useUserStore();
const toast = useToast();

const localOpen = ref(!!props.modelValue);
watch(
  () => props.modelValue,
  (v) => (localOpen.value = !!v)
);
watch(localOpen, (v) => emit("update:modelValue", v));

// Charger le menu et les utilisateurs au montage
onMounted(async () => {
  if (menuItemStore.items.length === 0) {
    await menuItemStore.fetchAll();
  }
  if (userStore.users.length === 0) {
    await userStore.fetchAll();
  }
});

// Utilisateurs disponibles (clients)
const availableUsers = computed(() => {
  return userStore.users.filter((user) => {
    // Filtrer pour ne montrer que les clients (rôle Client)
    const hasClientRole = user.roles?.some((role: any) => role.name === "Client");
    return hasClientRole;
  });
});

// État du formulaire
const state = reactive({
  user_id: props.customer?.id || null as number | null,
  order_type: "room_service" as "room_service" | "restaurant",
  location: "",
  note: "",
  items: [] as Array<{ menu_item_id: number; quantity: number; menuItem?: MenuItem }>,
});

// Items sélectionnés
const selectedItems = computed(() => {
  return state.items
    .map((item) => {
      const menuItem = menuItemStore.items.find((mi) => mi.id === item.menu_item_id);
      return {
        ...item,
        menuItem,
      };
    })
    .filter((item) => item.menuItem);
});

// Total calculé
const total = computed(() => {
  return selectedItems.value.reduce((sum, item) => {
    return sum + (item.menuItem?.price || 0) * item.quantity;
  }, 0);
});

// Menu items disponibles
const availableMenuItems = computed(() => {
  return menuItemStore.items.filter((item) => item.available);
});

// Fonctions pour gérer les items
function addItem() {
  if (availableMenuItems.value.length > 0) {
    state.items.push({
      menu_item_id: availableMenuItems.value[0].id,
      quantity: 1,
    });
  }
}

function removeItem(index: number) {
  state.items.splice(index, 1);
}

function updateItemQuantity(index: number, quantity: number) {
  if (quantity < 1) {
    state.items[index].quantity = 1;
  } else {
    state.items[index].quantity = quantity;
  }
}

// Réinitialiser le formulaire
function resetForm() {
  state.user_id = props.customer?.id || null;
  state.order_type = "room_service";
  state.location = "";
  state.note = "";
  state.items = [];
}

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    resetForm();
  }
});

// Soumission
const isLoading = ref(false);

async function handleSubmit() {
  if (!state.user_id) {
    toast.add({
      title: "Erreur",
      description: "Veuillez sélectionner un client.",
      color: "error",
    });
    return;
  }

  if (state.items.length === 0) {
    toast.add({
      title: "Erreur",
      description: "Veuillez ajouter au moins un article.",
      color: "error",
    });
    return;
  }

  if (!state.location.trim()) {
    toast.add({
      title: "Erreur",
      description: "Veuillez spécifier la location.",
      color: "error",
    });
    return;
  }

  isLoading.value = true;
  try {
    const payload = {
      user_id: state.user_id,
      order_type: state.order_type,
      location: state.location,
      note: state.note || undefined,
      items: state.items.map((item) => ({
        menu_item_id: item.menu_item_id,
        quantity: item.quantity,
      })),
    };

    const response = await orderStore.create(payload);

    // Si la réponse contient un redirect_url (FedaPay), rediriger vers la page de paiement
    if (response?.redirect_url) {
      toast.add({
        title: "Redirection vers le paiement",
        description: "Vous allez être redirigé vers la page de paiement",
        color: "success",
      });
      window.location.href = response.redirect_url;
      return;
    }

    toast.add({
      title: "Commande créée",
      description: "La commande a été créée avec succès.",
      color: "success",
    });

    emit("success");
    localOpen.value = false;
  } catch (error: any) {
    toast.add({
      title: "Erreur",
      description: error?.response?.data?.message || "Une erreur est survenue.",
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
}
</script>

<template>
  <UModal v-model:open="localOpen" title="Créer une commande" description="Créer une nouvelle commande pour le client">
    <template #body>
      <div class="space-y-6">
        <!-- Client (sélection) -->
        <UFormField label="Client" required>
          <USelect
            v-model="state.user_id"
            :items="availableUsers.map((user) => ({
              label: `${user.name} (${user.email})`,
              value: user.id,
            }))"
            placeholder="Sélectionner un client"
            searchable
            :disabled="!!customer"
          />
        </UFormField>

        <!-- Type de commande -->
        <UFormField label="Type de commande" required>
          <USelect
            v-model="state.order_type"
            :items="[
              { label: 'Room Service', value: 'room_service' },
              { label: 'Restaurant', value: 'restaurant' },
            ]"
          />
        </UFormField>

        <!-- Location -->
        <UFormField label="Location" required>
          <UInput
            v-model="state.location"
            placeholder="Ex: Chambre 101, Table 5, etc."
          />
        </UFormField>

        <!-- Articles -->
        <div>
          <div class="flex justify-between items-center mb-3">
            <label class="text-sm font-medium">Articles</label>
            <UButton
              size="sm"
              icon="i-heroicons-plus"
              @click="addItem"
              :disabled="availableMenuItems.length === 0"
            >
              Ajouter
            </UButton>
          </div>

          <div v-if="state.items.length === 0" class="text-sm text-gray-500 p-4 border border-dashed rounded-lg text-center">
            Aucun article. Cliquez sur "Ajouter" pour commencer.
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="(item, index) in state.items"
              :key="index"
              class="p-3 border rounded-lg"
            >
              <div class="flex items-start gap-3">
                <div class="flex-1">
                  <USelect
                    v-model="item.menu_item_id"
                    :items="availableMenuItems.map((mi) => ({
                      label: `${mi.name} - ${mi.price} XOF`,
                      value: mi.id,
                    }))"
                    placeholder="Sélectionner un article"
                  />
                  <div class="mt-2 flex items-center gap-2">
                    <label class="text-sm text-gray-600">Quantité:</label>
                    <UInput
                      v-model.number="item.quantity"
                      type="number"
                      min="1"
                      class="w-20"
                      @update:model-value="updateItemQuantity(index, $event)"
                    />
                    <span class="text-sm text-gray-500">
                      = {{ ((selectedItems[index]?.menuItem?.price || 0) * item.quantity).toLocaleString() }} XOF
                    </span>
                  </div>
                </div>
                <UButton
                  size="sm"
                  color="error"
                  variant="ghost"
                  icon="i-heroicons-trash"
                  @click="removeItem(index)"
                />
              </div>
            </div>
          </div>

          <div v-if="state.items.length > 0" class="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex justify-between items-center">
              <span class="font-semibold">Total:</span>
              <span class="text-lg font-bold">{{ total.toLocaleString() }} XOF</span>
            </div>
          </div>
        </div>

        <!-- Note -->
        <UFormField label="Note (optionnel)">
          <UTextarea
            v-model="state.note"
            placeholder="Instructions spéciales, allergies, etc."
            rows="3"
          />
        </UFormField>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton
          label="Annuler"
          color="neutral"
          variant="subtle"
          @click="localOpen = false"
          :disabled="isLoading"
        />
        <UButton
          label="Créer la commande"
          color="primary"
          @click="handleSubmit"
          :loading="isLoading"
        />
      </div>
    </template>
  </UModal>
</template>

