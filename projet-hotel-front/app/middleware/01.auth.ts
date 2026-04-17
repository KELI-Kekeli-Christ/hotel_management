// plugins/01.auth.ts
export default defineNuxtPlugin(async (nuxtApp) => {
  // Ce plugin s'exécute une seule fois, au démarrage de l'app.
  const authStore = useAuthStore();

  // On initialise le store SEULEMENT côté client, où localStorage est disponible.
  if (import.meta.client) {
    authStore.init();
  }
});
