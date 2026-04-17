<script setup lang="ts">
import { ref, computed, toRaw, onMounted, h } from "vue";
import { useReservationStore, type Reservation } from "~/stores/reservation";
import { useAuthStore } from "~/stores/auth";
import { useInvoiceStore } from "~/stores/invoiceStore";
import { storeToRefs } from "pinia";
import type { ColumnDef } from "@tanstack/vue-table";
import AdminReservationsFormModal from "~/components/admin/reservations/FormModal.vue";
// ConfirmModal importé
import ConfirmModal from "~/components/admin/chambres/ConfirmModal.vue";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
  permission: "reservation-list-all",
});

const authStore = useAuthStore();
const reservationStore = useReservationStore();
const invoiceStore = useInvoiceStore();
const { reservations, isLoading } = storeToRefs(reservationStore);
const toast = useToast();

onMounted(() => {
  reservationStore.fetchAll();
});

type ReservationRow = Reservation & {
  client: string;
  chambre: string;
  dates: string;
};

// --- State pour les modales ---
const isFormModalOpen = ref(false);
const isConfirmModalOpen = ref(false);
const selectedReservation = ref<Reservation | null>(null);
const reservationToDeleteId = ref<number | null>(null);

// --- Fonctions d'action ---
function openCreateModal() {
  selectedReservation.value = null;
  isFormModalOpen.value = true;
}
function openEditModal(reservation: Reservation) {
  selectedReservation.value = reservation;
  isFormModalOpen.value = true;
}
function openDeleteConfirm(reservation: Reservation) {
  reservationToDeleteId.value = reservation.id;
  isConfirmModalOpen.value = true;
}
async function handleDeleteConfirmed() {
  if (reservationToDeleteId.value === null) return;
  try {
    await reservationStore.remove(reservationToDeleteId.value);
    toast.add({ title: "Réservation annulée", color: "success" });
  } catch (error: any) {
    toast.add({
      title: "Erreur",
      description: error.response?.data?.message,
      color: "error",
    });
  } finally {
    isConfirmModalOpen.value = false;
    reservationToDeleteId.value = null;
  }
}

async function handleGenerateInvoice(reservation: Reservation) {
  try {
    const invoice = await invoiceStore.generate(reservation.id);
    toast.add({
      title: "Facture générée",
      description: `La facture ${invoice.invoice_number} a été créée avec succès.`,
      color: "success",
    });
    // Rediriger vers la page des factures
    await navigateTo("/admin/invoices");
  } catch (error: any) {
    toast.add({
      title: "Erreur",
      description: error.response?.data?.message || "Impossible de générer la facture.",
      color: "error",
    });
  }
}

const formatDate = (date: string) =>
  new Date(date).toLocaleDateString("fr-FR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });

// --- Rows computed : retourne un tableau "plat" avec champs dérivés ---
const rows = computed<ReservationRow[]>(() => {
  const src = reservations.value;
  const arr = Array.isArray(src) ? src : (src as any)?.data ?? [];
  return arr.map((r: any) => {
    const raw = toRaw(r) || r;
    return {
      ...raw,
      client: raw.user?.name ?? raw.client_name ?? "-",
      chambre:
        raw.chambre?.numero_chambre || raw.chambre_no
          ? `Ch. n°${raw.chambre?.numero_chambre ?? raw.chambre_no} (${
              raw.chambre?.type ?? ""
            })`
          : "-",
      dates:
        raw.date_arrive && raw.date_depart
          ? `${formatDate(raw.date_arrive)} - ${formatDate(raw.date_depart)}`
          : "-",
      status: raw.status ?? "-",
    } as any;
  });
});

const columns = ref<ColumnDef<ReservationRow>[]>([
  { accessorKey: "numero_reservation", header: "N° Réservation" },
  { accessorKey: "client", header: "Client" },
  { accessorKey: "chambre", header: "Chambre" },
  { accessorKey: "dates", header: "Dates de séjour" },
  { accessorKey: "status", header: "Statut" },
  {
    id: "actions",
    header: "Actions",
    cell: ({ row }) =>
      h(
        "div",
        { class: "flex items-center justify-end gap-2" },
        [
          authStore.can("invoice-generate") &&
          (row.original.status ?? "").toLowerCase() === "confirmed"
            ? h(
                "button",
                {
                  class:
                    "px-2 py-1 rounded border text-sm text-blue-600 bg-white hover:bg-blue-50",
                  onClick: (e: Event) => {
                    e.stopPropagation();
                    handleGenerateInvoice(row.original);
                  },
                },
                "Générer facture"
              )
            : null,
          authStore.can("reservation-update-any")
            ? h(
                "button",
                {
                  class:
                    "px-2 py-1 rounded border text-sm bg-white hover:bg-gray-50",
                  onClick: (e: Event) => {
                    e.stopPropagation();
                    openEditModal(row.original);
                  },
                },
                "Modifier"
              )
            : null,
          authStore.can("reservation-delete-any") &&
          (row.original.status ?? "").toLowerCase() !== "cancelled"
            ? h(
                "button",
                {
                  class:
                    "px-2 py-1 rounded border text-sm text-red-600 bg-white hover:bg-red-50",
                  onClick: (e: Event) => {
                    e.stopPropagation();
                    openDeleteConfirm(row.original);
                  },
                },
                "Annuler"
              )
            : null,
        ].filter(Boolean)
      ),
  },
]);
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Gestion des Réservations">
      <template #right>
        <UButton
          v-if="authStore.can('reservation-create-for-others')"
          label="Nouvelle Réservation"
          icon="i-heroicons-plus"
          @click="openCreateModal"
        />
      </template>
    </UDashboardNavbar>

    <!-- passe rows computed (plain array) au composant table -->
    <UTable :columns="columns" :data="rows" :loading="isLoading">
      <!-- Colonne client -->
      <template #client-data="{ row }">
        {{ row.original.client }}
      </template>

      <!-- Colonne chambre -->
      <template #chambre-data="{ row }">
        <span>{{ row.original.chambre }}</span>
      </template>

      <!-- Colonne dates -->
      <template #dates-data="{ row }">
        {{ row.original.dates }}
      </template>

      <!-- Colonne statut -->
      <template #status-data="{ row }">
        <UBadge
          :color="
            (row.original.status ?? '').toLowerCase() === 'confirmed'
              ? 'success'
              : (row.original.status ?? '').toLowerCase() === 'cancelled'
              ? 'error'
              : 'neutral'
          "
          variant="subtle"
        >
          {{ row.original.status }}
        </UBadge>
      </template>
      <!-- NOTE: actions rendered by columns.cell (no slot required) -->
    </UTable>

    <!-- Modales -->
    <AdminReservationsFormModal
      v-if="isFormModalOpen"
      v-model="isFormModalOpen"
      :reservation="selectedReservation"
    />

    <ConfirmModal
      v-if="isConfirmModalOpen"
      v-model="isConfirmModalOpen"
      title="Annuler la réservation ?"
      description="Cette action mettra à jour le statut en 'annulée' et libérera la chambre."
      @confirm="handleDeleteConfirmed"
    />
  </UDashboardPanel>
</template>
