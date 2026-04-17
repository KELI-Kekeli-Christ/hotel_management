<script setup lang="ts">
import { useAuthStore } from "~/stores/auth";
import { ref, reactive } from "vue";

definePageMeta({
  layout: false,
});

const authStore = useAuthStore();
const toast = useToast();

const state = reactive({
  email: "",
  password: "",
});

const isLoading = ref(false);
const errorMessage = ref<string | null>(null);
let response: any = null;

async function handleLogin() {
  isLoading.value = true;
  errorMessage.value = null;
  try {
    authStore.init();
    response = await authStore.login(state);
    console.log("Login response:", response);
    toast.add({ title: "Connexion réussie !", color: "success" });
    // Rediriger selon le rôle
    if (authStore.hasRole("Client")) {
      await navigateTo("/client");
    } else {
      await navigateTo("/dashboard");
    }
  } catch (error: any) {
    // console.error("Erreur complète Axios:", error);
    // console.error("Détails:", JSON.stringify(error, null, 2));
    if (error.response) {
      console.error("Erreur Axios (response):", error.response);
      console.error("Status:", error.response.status);
      console.error("Data:", error.response.data);
      errorMessage.value = error.response.data?.message || "Erreur serveur.";
    } else if (error.request) {
      console.error("Erreur Axios (request):", error.request);
      errorMessage.value =
        "Aucune réponse du serveur. Vérifie ta connexion ou CORS.";
    } else {
      console.error("Erreur Axios (message):", error);
      errorMessage.value = error.message;
    }
    const msg = error.response?.data?.message || response;
    errorMessage.value = msg;
    toast.add({
      title: "Erreur de connexion",
      description: msg,
      color: "error",
    });
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
      <!-- Section gauche : Image et texte -->
      <div
        class="hidden lg:flex w-1/2 p-12 flex-col justify-center items-center text-white bg-gradient-to-br from-blue-600 to-purple-600 relative overflow-hidden"
      >
        <div class="absolute inset-0 bg-black/20"></div>
        <div class="relative z-10 text-center">
          <div class="mb-8">
            <div class="w-20 h-20 mx-auto mb-4 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
              <UIcon name="i-heroicons-key" class="w-10 h-10 text-white" />
            </div>
            <h2 class="text-4xl font-bold mb-4">Bienvenue</h2>
            <p class="text-lg text-white/90 max-w-md">
              Connectez-vous pour accéder à votre tableau de bord et gérer vos réservations
            </p>
          </div>
          <div class="mt-12 space-y-4 text-left max-w-sm">
            <div class="flex items-start space-x-3">
              <UIcon name="i-heroicons-shield-check" class="w-6 h-6 text-white/90 flex-shrink-0 mt-0.5" />
              <p class="text-white/90">Connexion sécurisée</p>
            </div>
            <div class="flex items-start space-x-3">
              <UIcon name="i-heroicons-clock" class="w-6 h-6 text-white/90 flex-shrink-0 mt-0.5" />
              <p class="text-white/90">Accès rapide à vos services</p>
            </div>
            <div class="flex items-start space-x-3">
              <UIcon name="i-heroicons-user-circle" class="w-6 h-6 text-white/90 flex-shrink-0 mt-0.5" />
              <p class="text-white/90">Gestion personnalisée</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Section droite : Formulaire -->
      <div class="w-full lg:w-1/2 p-8 lg:p-12 flex flex-col justify-center">
        <div class="w-full max-w-md mx-auto">
          <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
              Connexion
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
              Entrez vos identifiants pour accéder à votre compte
            </p>
          </div>

          <UForm
            :state="state"
            class="space-y-5"
            @submit.prevent="handleLogin"
          >
            <UFormField
              label="Adresse email"
              name="email"
              required
            >
              <UInput
                v-model="state.email"
                type="email"
                placeholder="jean.dupont@example.com"
                icon="i-heroicons-envelope"
                size="lg"
                :disabled="isLoading"
              />
            </UFormField>

            <UFormField
              label="Mot de passe"
              name="password"
              required
            >
              <UInput
                v-model="state.password"
                type="password"
                placeholder="Votre mot de passe"
                icon="i-heroicons-lock-closed"
                size="lg"
                :disabled="isLoading"
              />
            </UFormField>

            <div v-if="errorMessage" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
              <p class="text-sm text-red-600 dark:text-red-400">{{ errorMessage }}</p>
            </div>

            <UButton
              type="submit"
              block
              size="lg"
              :loading="isLoading"
              class="mt-6"
            >
              Se connecter
            </UButton>
          </UForm>

          <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Pas encore de compte ?
              <NuxtLink
                to="/register"
                class="font-semibold text-primary hover:text-primary-600 dark:text-primary-400 dark:hover:text-primary-300 transition-colors"
              >
                Créez un compte
              </NuxtLink>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
