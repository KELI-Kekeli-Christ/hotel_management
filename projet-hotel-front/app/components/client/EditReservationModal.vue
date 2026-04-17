<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useReservationStore, type Reservation } from "~/stores/reservation";
import {
  DialogRoot,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "reka-ui";

const props = defineProps<{
  modelValue: boolean;
  reservation: Reservation;
}>();

const emit = defineEmits(["update:modelValue", "success"]);

const reservationStore = useReservationStore();
const toast = useToast();

// local open state + sync with parent
const localOpen = ref(!!props.modelValue);
watch(
  () => props.modelValue,
  (v) => {
    localOpen.value = !!v;
  }
);
watch(localOpen, (v) => {
  emit("update:modelValue", v);
});

// form state
const dateArrive = ref("");
const dateDepart = ref("");
const isLoading = ref(false);

// Définir les dates min (aujourd'hui) et max
const today = new Date().toISOString().split("T")[0];
const maxDate = new Date();
maxDate.setFullYear(maxDate.getFullYear() + 1);
const maxDateStr = maxDate.toISOString().split("T")[0];

// Initialiser les dates depuis la réservation
watch(
  () => props.reservation,
  (res) => {
    if (res) {
      try {
        dateArrive.value = (res.date_arrive ?? "").split("T")[0] || "";
        dateDepart.value = (res.date_depart ?? "").split("T")[0] || "";
      } catch {
        dateArrive.value = "";
        dateDepart.value = "";
      }
    }
  },
  { immediate: true }
);

// Calculer la durée
const duration = computed(() => {
  if (!dateArrive.value || !dateDepart.value) return 0;
  const start = new Date(dateArrive.value);
  const end = new Date(dateDepart.value);
  const diff = end.getTime() - start.getTime();
  return Math.ceil(diff / (1000 * 60 * 60 * 24));
});

// Calculer le prix total
const totalPrice = computed(() => {
  if (!props.reservation?.chambre) return 0;
  return duration.value * props.reservation.chambre.prix_nuite;
});

const formatPrice = (price: number) =>
  new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "XOF",
  }).format(price);

async function handleSubmit() {
  if (!dateArrive.value || !dateDepart.value) {
    toast.add({
      title: "Erreur",
      description: "Veuillez sélectionner les dates d'arrivée et de départ",
      color: "error",
    });
    return;
  }

  if (duration.value <= 0) {
    toast.add({
      title: "Erreur",
      description: "La date de départ doit être après la date d'arrivée",
      color: "error",
    });
    return;
  }

  isLoading.value = true;
  try {
    const payload = {
      date_arrive: dateArrive.value,
      date_depart: dateDepart.value,
    };

    const response = await reservationStore.update(props.reservation.id, payload);

    // Si la réponse contient un redirect_url (FedaPay), rediriger vers la page de paiement
    if (response?.redirect_url) {
      toast.add({
        title: "Redirection vers le paiement",
        description: "Vous allez être redirigé vers la page de paiement pour valider la modification",
        color: "success",
      });
      // Rediriger vers FedaPay
      window.location.href = response.redirect_url;
      return;
    }

    toast.add({
      title: "Réservation mise à jour",
      description: "Votre réservation a été modifiée avec succès",
      color: "success",
    });

    emit("success");
    localOpen.value = false;
  } catch (error: any) {
    console.error("Error updating reservation:", error);
    const errorMessage =
      error?.response?.data?.message ||
      error?.response?.data?.error ||
      error.message ||
      "Impossible de modifier la réservation";

    toast.add({
      title: "Erreur",
      description: errorMessage,
      color: "error",
      timeout: 8000,
    });
  } finally {
    isLoading.value = false;
  }
}

const descriptionId = `dialog-desc-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
  <div v-if="localOpen">
    <DialogRoot v-model:open="localOpen">
      <DialogOverlay />

      <DialogContent
        :aria-describedby="descriptionId"
        class="max-w-2xl mx-auto"
      >
        <div class="p-6">
          <DialogTitle>
            <h2 class="text-xl font-semibold">Modifier la Réservation</h2>
          </DialogTitle>

          <DialogDescription
            :id="descriptionId"
            class="text-sm text-gray-600 mt-1"
          >
            Modifiez les dates de votre réservation. Un acompte sera demandé pour valider les modifications.
          </DialogDescription>

          <form @submit.prevent="handleSubmit" class="space-y-4 mt-4">
            <!-- Informations de la chambre (en lecture seule) -->
            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
              <p class="text-sm text-gray-500 dark:text-gray-400">Chambre</p>
              <p class="font-medium">
                Chambre {{ reservation.chambre?.numero_chambre }} -
                {{ reservation.chambre?.type }}
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ formatPrice(reservation.chambre?.prix_nuite || 0) }} / nuit
              </p>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label
                  class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                  >Date d'arrivée</label
                >
                <input
                  v-model="dateArrive"
                  type="date"
                  :min="today"
                  :max="maxDateStr"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white"
                  required
                />
              </div>
              <div>
                <label
                  class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                  >Date de départ</label
                >
                <input
                  v-model="dateDepart"
                  type="date"
                  :min="dateArrive || today"
                  :max="maxDateStr"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white"
                  required
                />
              </div>
            </div>

            <!-- Résumé -->
            <div
              v-if="duration > 0"
              class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg"
            >
              <div class="flex justify-between items-center">
                <div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Durée: {{ duration }} {{ duration === 1 ? "nuit" : "nuits" }}
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Nouveau prix total: {{ formatPrice(totalPrice) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-4">
              <button
                type="button"
                class="px-3 py-2 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700"
                @click="localOpen = false"
              >
                Annuler
              </button>
              <button
                type="submit"
                class="px-3 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 disabled:opacity-60"
                :disabled="isLoading"
              >
                <span v-if="isLoading">Enregistrement...</span>
                <span v-else>Modifier</span>
              </button>
            </div>
          </form>
        </div>
      </DialogContent>
    </DialogRoot>
  </div>
</template>

