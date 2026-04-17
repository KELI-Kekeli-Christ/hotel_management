<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: "default",
  // Pas de middleware spécifique, utilise le middleware global qui vérifie l'auth
});

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const toast = useToast();

const status = ref<string>("");
const isLoading = ref(true);

onMounted(() => {
  // Récupérer les paramètres de l'URL
  status.value = (route.query.status as string) || "";
  const pendingKey = route.query.pending_key as string;

  // Déterminer le message et l'action selon le statut
  const statusLower = status.value.toLowerCase();

  const successStatuses = ["approved", "paid", "completed", "succeeded", "confirmed"];
  const failedStatuses = ["canceled", "cancelled", "declined", "failed", "rejected"];

  if (successStatuses.includes(statusLower)) {
    // Paiement réussi
    toast.add({
      title: "Paiement réussi",
      description: "Votre réservation a été confirmée avec succès",
      color: "success",
    });
    
    // Rediriger vers les réservations après 2 secondes
    setTimeout(() => {
      router.push("/client/bookings");
    }, 2000);
  } else if (failedStatuses.includes(statusLower)) {
    // Paiement annulé/échoué
    toast.add({
      title: "Paiement annulé",
      description: "Votre paiement a été annulé. La réservation n'a pas été confirmée.",
      color: "warning",
    });
    
    // Rediriger vers les chambres après 2 secondes
    setTimeout(() => {
      router.push("/client/rooms");
    }, 2000);
  } else {
    // Statut inconnu ou pending
    toast.add({
      title: "Paiement en attente",
      description: "Votre paiement est en cours de traitement",
      color: "info",
    });
    
    // Rediriger vers les réservations après 2 secondes
    setTimeout(() => {
      router.push("/client/bookings");
    }, 2000);
  }

  isLoading.value = false;
});
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Résultat du paiement">
      <template #right>
        <UButton
          label="Mes réservations"
          icon="i-heroicons-calendar"
          color="primary"
          @click="router.push('/client/bookings')"
        />
        <UButton
          label="Voir les chambres"
          icon="i-heroicons-home"
          variant="outline"
          @click="router.push('/client/rooms')"
        />
      </template>
    </UDashboardNavbar>

    <div class="p-8">
      <div v-if="isLoading" class="flex justify-center items-center min-h-[400px]">
        <div class="text-center">
          <div
            class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mb-4"
          ></div>
          <p class="text-gray-600 dark:text-gray-400">Traitement en cours...</p>
        </div>
      </div>

      <div v-else class="max-w-2xl mx-auto">
        <UCard class="text-center">
          <div class="py-8">
            <!-- Icône selon le statut -->
            <div class="mb-6">
              <div
                v-if="['approved', 'paid', 'completed', 'succeeded', 'confirmed'].includes(status.toLowerCase())"
                class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-success-100 dark:bg-success-900/20 mb-4"
              >
                <UIcon
                  name="i-heroicons-check-circle"
                  class="w-12 h-12 text-success-600 dark:text-success-400"
                />
              </div>
              <div
                v-else-if="['canceled', 'cancelled', 'declined', 'failed', 'rejected'].includes(status.toLowerCase())"
                class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-warning-100 dark:bg-warning-900/20 mb-4"
              >
                <UIcon
                  name="i-heroicons-x-circle"
                  class="w-12 h-12 text-warning-600 dark:text-warning-400"
                />
              </div>
              <div
                v-else
                class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary-100 dark:bg-primary-900/20 mb-4"
              >
                <UIcon
                  name="i-heroicons-clock"
                  class="w-12 h-12 text-primary-600 dark:text-primary-400"
                />
              </div>
            </div>

            <!-- Message selon le statut -->
            <h2 class="text-2xl font-bold mb-4">
              <span
                v-if="['approved', 'paid', 'completed', 'succeeded', 'confirmed'].includes(status.toLowerCase())"
              >
                Paiement réussi !
              </span>
              <span
                v-else-if="['canceled', 'cancelled', 'declined', 'failed', 'rejected'].includes(status.toLowerCase())"
              >
                Paiement annulé
              </span>
              <span v-else> Paiement en attente </span>
            </h2>

            <p class="text-gray-600 dark:text-gray-400 mb-8">
              <span
                v-if="['approved', 'paid', 'completed', 'succeeded', 'confirmed'].includes(status.toLowerCase())"
              >
                Votre réservation a été confirmée avec succès. Vous allez être redirigé vers vos
                réservations.
              </span>
              <span
                v-else-if="['canceled', 'cancelled', 'declined', 'failed', 'rejected'].includes(status.toLowerCase())"
              >
                Votre paiement a été annulé. La réservation n'a pas été confirmée et la chambre
                reste disponible.
              </span>
              <span v-else>
                Votre paiement est en cours de traitement. Vous serez notifié une fois qu'il sera
                confirmé.
              </span>
            </p>

            <!-- Boutons d'action -->
            <div class="flex gap-4 justify-center">
              <UButton
                v-if="['approved', 'paid', 'completed', 'succeeded', 'confirmed'].includes(status.toLowerCase())"
                label="Voir mes réservations"
                icon="i-heroicons-calendar"
                color="primary"
                size="lg"
                @click="router.push('/client/bookings')"
              />
              <UButton
                v-else-if="['canceled', 'cancelled', 'declined', 'failed', 'rejected'].includes(status.toLowerCase())"
                label="Réserver une chambre"
                icon="i-heroicons-home"
                color="primary"
                size="lg"
                @click="router.push('/client/rooms')"
              />
              <UButton
                v-else
                label="Voir mes réservations"
                icon="i-heroicons-calendar"
                color="primary"
                size="lg"
                @click="router.push('/client/bookings')"
              />
            </div>
          </div>
        </UCard>
      </div>
    </div>
  </UDashboardPanel>
</template>

