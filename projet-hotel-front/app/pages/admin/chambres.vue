<script setup lang="ts">
import { useChambreStore, type Chambre } from "~/stores/chambre";
import AdminChambresFormModal from "~/components/admin/chambres/FormModal.vue";
import { useAuthStore } from "~/stores/auth";
import { useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import ConfirmModal from "../../components/admin/chambres/ConfirmModal.vue";
import roomPlaceholder from "~/assets/images/room_placeholder.png";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
  roles: ["Super-Admin", "Receptionist", "Service-Manager"],
  permission: "room-list",
});

const router = useRouter();
const authStore = useAuthStore();
const chambreStore = useChambreStore();
const { chambres, isLoading } = storeToRefs(chambreStore);

// Evite d'appeler fetch plusieurs fois (race conditions)
onMounted(async () => {
  if (!isLoading.value && chambres.value.length === 0) {
    await chambreStore.fetchAll();
  }
});

const isModalOpen = ref(false);
const selectedChambre = ref<Chambre | null>(null);

// Pour bloquer les suppressions simultanées
const deletingId = ref<number | null>(null);

// Etats pour la ConfirmModal
const confirmOpen = ref(false);
const confirmTargetId = ref<number | null>(null);
const confirmLoading = ref(false);

function formatPrice(value: number | null | undefined) {
  if (value == null) return "---";
  return new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "XOF",
    minimumFractionDigits: 0,
  }).format(value);
}

function openCreateModal() {
  if (!authStore.can("room-create")) {
    useToast().add({ title: "Accès refusé", color: "error" });
    return;
  }
  selectedChambre.value = null;
  isModalOpen.value = true;
}

function openEditModal(chambre: Chambre) {
  if (!authStore.can("room-update")) {
    useToast().add({ title: "Accès refusé", color: "error" });
    return;
  }
  selectedChambre.value = { ...chambre };
  isModalOpen.value = true;
}

// Ouvre la modal de confirmation (remplace confirm())
function askDelete(id: number) {
  if (!authStore.can("room-delete")) {
    useToast().add({ title: "Accès refusé", color: "error" });
    return;
  }
  confirmTargetId.value = id;
  confirmOpen.value = true;
}

// Suppression après confirmation depuis la modal
const toast = useToast();
async function handleDeleteConfirmed() {
  if (confirmTargetId.value == null) return;
  confirmLoading.value = true;
  deletingId.value = confirmTargetId.value;

  try {
    await chambreStore.remove(confirmTargetId.value);
    toast.add({ title: "Chambre supprimée.", color: "success" });
  } catch (error: any) {
    toast.add({
      title: "Erreur",
      description: error?.response?.data?.message ?? "Impossible de supprimer.",
      color: "error",
    });
  } finally {
    confirmLoading.value = false;
    confirmOpen.value = false;
    deletingId.value = null;
    confirmTargetId.value = null;
  }
}

// Actions pour le dropdown — utilise askDelete pour l'action suppression
const actions = (chambre: Chambre) => [
  [
    {
      label: "Modifier",
      icon: "i-heroicons-pencil-20-solid",
      click: () => openEditModal(chambre),
      onClick: () => openEditModal(chambre),
      "aria-label": `Modifier la chambre ${chambre.numero_chambre}`,
    },
  ],
  [
    {
      label: "Supprimer",
      icon: "i-heroicons-trash-20-solid",
      click: () => askDelete(chambre.id),
      onClick: () => askDelete(chambre.id),
      "aria-label": `Supprimer la chambre ${chambre.numero_chambre}`,
      disabled: deletingId.value === chambre.id || confirmLoading.value,
    },
  ],
];

// Naviguer vers un détail si l'utilisateur clique sur la carte (meilleure affordance)
function openDetail(chambre: Chambre) {
  router.push(`/admin/chambres/${chambre.id}`).catch(() => {});
}

function getPhotoAlt(chambre: Chambre) {
  return (
    (chambre as unknown as { photo_alt?: string }).photo_alt ??
    `Photo de la chambre ${chambre.numero_chambre}`
  );
}

function onImageError(e: Event) {
  const img = e.currentTarget as HTMLImageElement | null;
  if (!img) return;
  img.src = roomPlaceholder;
}
</script>

<template>
  <UDashboardPanel class="flex flex-col min-h-0" grow>
    <UDashboardNavbar title="Gestion des Chambres">
      <template #right>
        <UButton
          v-if="authStore.can('room-create')"
          label="Ajouter une chambre"
          icon="i-heroicons-plus"
          @click="openCreateModal"
          aria-label="Ajouter une nouvelle chambre"
        />
      </template>
    </UDashboardNavbar>

    <!-- Conteneur scrollable correct dans un parent flex : min-h-0 + overflow-auto -->
    <div class="flex-1 min-h-0 overflow-auto p-4">
      <!-- Loading -->
      <div
        v-if="isLoading"
        class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-stretch"
        style="grid-auto-rows: 1fr"
      >
        <template v-for="n in 8" :key="`skeleton-${n}`">
          <UCard
            :ui="{ body: 'px-4 py-4 sm:p-4' }"
            class="animate-pulse h-full"
          >
            <div class="h-48 bg-gray-200 rounded-md mb-3"></div>
            <div class="h-4 bg-gray-200 rounded w-2/3 mb-2"></div>
            <div class="h-4 bg-gray-200 rounded w-1/3"></div>
          </UCard>
        </template>
      </div>

      <!-- Empty -->
      <div
        v-else-if="!isLoading && chambres.length === 0"
        class="p-8 text-center text-gray-500"
      >
        <p class="text-lg font-medium mb-2">Aucune chambre n'a été trouvée.</p>
        <p class="text-sm mb-4">
          Créez des chambres pour gérer les disponibilités et les réservations.
        </p>
        <UButton
          v-if="authStore.can('room-create')"
          class="mt-4"
          label="Créer la première chambre"
          @click="openCreateModal"
        />
      </div>

      <!-- Grid -->
      <div
        v-else
        class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-stretch"
        style="grid-auto-rows: 1fr"
      >
        <UCard
          v-for="chambre in chambres"
          :key="chambre.id"
          :ui="{ body: 'px-4 py-4 sm:p-4' }"
          class="cursor-pointer hover:shadow-lg transition-shadow flex flex-col h-full"
          @click.self="openDetail(chambre)"
          role="article"
          :aria-label="`Chambre ${chambre.numero_chambre} - ${chambre.type}`"
        >
          <template #header>
            <div class="flex justify-between items-center">
              <h3 class="font-bold text-base truncate">
                Ch. n°{{ chambre.numero_chambre }}
              </h3>
              <UDropdownMenu
                :items="actions(chambre)"
                :ui="{ content: 'w-40' }"
                arrow
              >
                <UButton
                  color="neutral"
                  variant="ghost"
                  icon="i-heroicons-ellipsis-vertical-20-solid"
                  aria-label="Actions sur la chambre"
                />
              </UDropdownMenu>
            </div>
          </template>

          <img
            :src="chambre.photo_url || roomPlaceholder"
            :alt="getPhotoAlt(chambre)"
            class="w-full object-cover rounded-md mb-3 flex-shrink-0"
            style="height: 14rem"
            loading="lazy"
            @error="onImageError"
          />

          <div class="flex-1 flex flex-col justify-between">
            <div>
              <div class="flex justify-between items-center">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                  {{ chambre.type }}
                </p>
                <UBadge
                  :color="chambre.disponibilite ? 'success' : 'error'"
                  variant="subtle"
                >
                  {{ chambre.disponibilite ? "Disponible" : "Occupée" }}
                </UBadge>
              </div>

              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ formatPrice(chambre.prix_nuite) }} / nuit
              </p>
            </div>
          </div>
        </UCard>
      </div>
    </div>

    <AdminChambresFormModal
      v-if="isModalOpen"
      v-model="isModalOpen"
      :chambre="selectedChambre"
    />

    <ConfirmModal
      v-model="confirmOpen"
      title="Supprimer la chambre ?"
      description="Cette action est irréversible. Voulez-vous continuer ?"
      confirmLabel="Supprimer"
      cancelLabel="Annuler"
      :loading="confirmLoading"
      @confirm="handleDeleteConfirmed"
    />

    <!-- FAB (optionnel) -->
    <div
      v-if="authStore.can('room-create')"
      class="fixed bottom-6 right-6 z-50"
    >
      <UButton rounded icon="i-heroicons-plus" @click="openCreateModal" />
    </div>
  </UDashboardPanel>
</template>
