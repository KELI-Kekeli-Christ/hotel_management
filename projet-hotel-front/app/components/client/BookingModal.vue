<script setup lang="ts">
import { ref, computed } from "vue";
import { useReservationStore } from "~/stores/reservation";
import { useAuthStore } from "~/stores/auth";
import type { Chambre } from "~/stores/chambre";

const props = defineProps<{
  chambre: Chambre;
}>();

const emit = defineEmits<{
  success: [];
  close: [];
}>();

const reservationStore = useReservationStore();
const authStore = useAuthStore();
const toast = useToast();

const dateArrive = ref("");
const dateDepart = ref("");
const isLoading = ref(false);

const duration = computed(() => {
  if (!dateArrive.value || !dateDepart.value) return 0;
  const start = new Date(dateArrive.value);
  const end = new Date(dateDepart.value);
  const diff = end.getTime() - start.getTime();
  return Math.ceil(diff / (1000 * 60 * 60 * 24));
});

const totalPrice = computed(() => {
  return duration.value * props.chambre.prix_nuite;
});

const formatPrice = (price: number) =>
  new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "XOF",
  }).format(price);

// Définir les dates min (aujourd'hui) et max
const today = new Date().toISOString().split("T")[0];
const maxDate = new Date();
maxDate.setFullYear(maxDate.getFullYear() + 1);
const maxDateStr = maxDate.toISOString().split("T")[0];

const handleSubmit = async () => {
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
    const response = await reservationStore.create({
      user_id: authStore.user!.id,
      chambre_id: props.chambre.id,
      date_arrive: dateArrive.value,
      date_depart: dateDepart.value,
    });

    // Si la réponse contient un redirect_url (FedaPay), rediriger vers la page de paiement
    if (response?.redirect_url) {
      toast.add({
        title: "Redirection vers le paiement",
        description: "Vous allez être redirigé vers la page de paiement",
        color: "success",
      });
      // Rediriger vers FedaPay
      window.location.href = response.redirect_url;
      return;
    }

    toast.add({
      title: "Réservation créée",
      description: "Votre réservation a été créée avec succès",
      color: "success",
    });

    emit("success");
  } catch (error: any) {
    console.error("Error creating reservation:", error);
    const errorMessage = 
      error.response?.data?.message || 
      error.response?.data?.error || 
      error.message || 
      "Impossible de créer la réservation";
    
    toast.add({
      title: "Erreur",
      description: errorMessage,
      color: "error",
      timeout: 8000,
    });
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="space-y-6">
    <!-- Informations de la chambre -->
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
      <h3 class="font-semibold mb-2">Chambre {{ chambre.numero_chambre }}</h3>
      <p class="text-sm text-gray-600 dark:text-gray-400">{{ chambre.type }}</p>
      <p class="text-sm font-medium mt-2">
        Prix par nuit: {{ formatPrice(chambre.prix_nuite) }}
      </p>
    </div>

    <!-- Formulaire de réservation -->
    <div class="space-y-4">
      <UFormField label="Date d'arrivée" required>
        <UInput
          v-model="dateArrive"
          type="date"
          :min="today"
          :max="maxDateStr"
        />
      </UFormField>

      <UFormField label="Date de départ" required>
        <UInput
          v-model="dateDepart"
          type="date"
          :min="dateArrive || today"
          :max="maxDateStr"
        />
      </UFormField>

      <!-- Résumé -->
      <div v-if="duration > 0" class="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-4">
        <div class="flex justify-between items-center mb-2">
          <span class="text-sm text-gray-600 dark:text-gray-400">Durée du séjour</span>
          <span class="font-semibold">{{ duration }} {{ duration === 1 ? "nuit" : "nuits" }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-sm text-gray-600 dark:text-gray-400">Total</span>
          <span class="text-xl font-bold text-primary-600 dark:text-primary-400">
            {{ formatPrice(totalPrice) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-3 justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
      <UButton
        label="Annuler"
        color="neutral"
        variant="ghost"
        @click="emit('close')"
      />
      <UButton
        label="Réserver"
        color="primary"
        :loading="isLoading"
        :disabled="!dateArrive || !dateDepart || duration <= 0"
        @click="handleSubmit"
      />
    </div>
  </div>
</template>

