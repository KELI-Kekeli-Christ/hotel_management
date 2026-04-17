// plugins/auth-init.ts
export default defineNuxtPlugin(async () => {
  const authStore = useAuthStore();
  if (import.meta.client) {
    // Attendre que le store soit initialisé par 01.auth.ts
    await nextTick();
    
    const token = localStorage.getItem("token");
    
    if (token && !authStore.user) {
      // Si on a un token mais pas d'utilisateur, on récupère l'utilisateur
      try {
        authStore.setAuth({ token });
        await authStore.fetchUser();
      } catch (error) {
        console.error(
          "Échec de l'initialisation de la session utilisateur.",
          error
        );
        // Si le fetchUser échoue, on nettoie
        authStore.clearAuth();
      }
    } else if (token && authStore.isAuthenticated) {
      // Si on a déjà le token et l'utilisateur, on s'assure que l'API est configurée
      authStore.setAuth({ token, user: authStore.user });
    }
  }
});
