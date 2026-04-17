<script setup lang="ts">
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: false,
});

const authStore = useAuthStore();
const toast = useToast();

const state = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
});

const isLoading = ref(false);
const errors = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
});

function validateForm() {
  let isValid = true;
  errors.name = "";
  errors.email = "";
  errors.password = "";
  errors.password_confirmation = "";

  if (!state.name.trim()) {
    errors.name = "Le nom est requis";
    isValid = false;
  }

  if (!state.email.trim()) {
    errors.email = "L'email est requis";
    isValid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.email)) {
    errors.email = "L'email n'est pas valide";
    isValid = false;
  }

  if (!state.password) {
    errors.password = "Le mot de passe est requis";
    isValid = false;
  } else if (state.password.length < 8) {
    errors.password = "Le mot de passe doit contenir au moins 8 caractères";
    isValid = false;
  }

  if (!state.password_confirmation) {
    errors.password_confirmation = "La confirmation du mot de passe est requise";
    isValid = false;
  } else if (state.password !== state.password_confirmation) {
    errors.password_confirmation = "Les mots de passe ne correspondent pas";
    isValid = false;
  }

  return isValid;
}

async function handleRegister() {
  if (!validateForm()) {
    return;
  }

  isLoading.value = true;
  try {
    const response = await authStore.register(state);
    toast.add({
      title: "Inscription réussie !",
      description: response.message || "Veuillez vous connecter pour continuer.",
      color: "success",
    });
    // Rediriger vers la page de connexion après 2 secondes
    setTimeout(() => {
      navigateTo("/login");
    }, 2000);
  } catch (error: any) {
    const errorMessage = error.response?.data?.message || "Une erreur est survenue lors de l'inscription.";
    toast.add({
      title: "Erreur de création de compte",
      description: errorMessage,
      color: "error",
    });
    
    // Afficher les erreurs de validation du backend
    if (error.response?.data?.errors) {
      const backendErrors = error.response.data.errors;
      if (backendErrors.name) errors.name = backendErrors.name[0];
      if (backendErrors.email) errors.email = backendErrors.email[0];
      if (backendErrors.password) errors.password = backendErrors.password[0];
    }
  } finally {
    isLoading.value = false;
  }
}
</script>

<template>
  <div
    class="flex items-center justify-center min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900"
  >
    <div
      class="flex w-full max-w-5xl bg-white dark:bg-gray-800 shadow-2xl rounded-2xl overflow-hidden"
    >
      <!-- Section gauche : Formulaire -->
      <div class="w-full lg:w-1/2 p-8 lg:p-12 flex flex-col justify-center">
        <div class="w-full max-w-md mx-auto">
          <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
              Créez votre compte
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
              Rejoignez-nous et commencez votre expérience
            </p>
          </div>

          <UForm
            :state="state"
            class="space-y-5"
            @submit.prevent="handleRegister"
          >
            <UFormField
              label="Nom complet"
              name="name"
              :error="errors.name"
              required
            >
              <UInput
                v-model="state.name"
                type="text"
                placeholder="Jean Dupont"
                icon="i-heroicons-user"
                size="lg"
                :disabled="isLoading"
                :class="{ 'border-red-500': errors.name }"
              />
            </UFormField>

            <UFormField
              label="Adresse email"
              name="email"
              :error="errors.email"
              required
            >
              <UInput
                v-model="state.email"
                type="email"
                placeholder="jean.dupont@example.com"
                icon="i-heroicons-envelope"
                size="lg"
                :disabled="isLoading"
                :class="{ 'border-red-500': errors.email }"
              />
            </UFormField>

            <UFormField
              label="Mot de passe"
              name="password"
              :error="errors.password"
              required
            >
              <UInput
                v-model="state.password"
                type="password"
                placeholder="Minimum 8 caractères"
                icon="i-heroicons-lock-closed"
                size="lg"
                :disabled="isLoading"
                :class="{ 'border-red-500': errors.password }"
              />
            </UFormField>

            <UFormField
              label="Confirmer le mot de passe"
              name="password_confirmation"
              :error="errors.password_confirmation"
              required
            >
              <UInput
                v-model="state.password_confirmation"
                type="password"
                placeholder="Répétez votre mot de passe"
                icon="i-heroicons-lock-closed"
                size="lg"
                :disabled="isLoading"
                :class="{ 'border-red-500': errors.password_confirmation }"
              />
            </UFormField>

            <UButton
              type="submit"
              block
              size="lg"
              :loading="isLoading"
              class="mt-6"
            >
              Créer mon compte
            </UButton>
          </UForm>

          <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Déjà un compte ?
              <NuxtLink
                to="/login"
                class="font-semibold text-primary hover:text-primary-600 dark:text-primary-400 dark:hover:text-primary-300 transition-colors"
              >
                Connectez-vous
              </NuxtLink>
            </p>
          </div>
        </div>
      </div>

      <!-- Section droite : Image et texte -->
      <div
        class="hidden lg:flex w-1/2 p-12 flex-col justify-center items-center text-white bg-gradient-to-br from-blue-600 to-purple-600 relative overflow-hidden"
      >
        <div class="absolute inset-0 bg-black/20"></div>
        <div class="relative z-10 text-center">
          <div class="mb-8">
            <div class="w-20 h-20 mx-auto mb-4 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
              <UIcon name="i-heroicons-building-office-2" class="w-10 h-10 text-white" />
            </div>
            <h2 class="text-4xl font-bold mb-4">Bienvenue</h2>
            <p class="text-lg text-white/90 max-w-md">
              Créez votre compte et profitez de nos services exceptionnels
            </p>
          </div>
          <div class="mt-12 space-y-4 text-left max-w-sm">
            <div class="flex items-start space-x-3">
              <UIcon name="i-heroicons-check-circle" class="w-6 h-6 text-white/90 flex-shrink-0 mt-0.5" />
              <p class="text-white/90">Réservation facile et rapide</p>
            </div>
            <div class="flex items-start space-x-3">
              <UIcon name="i-heroicons-check-circle" class="w-6 h-6 text-white/90 flex-shrink-0 mt-0.5" />
              <p class="text-white/90">Gestion de vos réservations</p>
            </div>
            <div class="flex items-start space-x-3">
              <UIcon name="i-heroicons-check-circle" class="w-6 h-6 text-white/90 flex-shrink-0 mt-0.5" />
              <p class="text-white/90">Support client disponible 24/7</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
