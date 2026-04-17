export default defineNuxtRouteMiddleware(async (to, from) => {
  const authStore = useAuthStore();

  const publicRoutes = ["/login", "/register", "/home"];
  const isGoingToPublicRoute = publicRoutes.includes(to.path);

  // Si on a un token mais pas d'utilisateur, on essaie de le récupérer
  if (authStore.token && !authStore.user && import.meta.client) {
    try {
      await authStore.fetchUser();
    } catch (error) {
      console.warn("[Auth middleware] Failed to fetch user:", error);
      // Si le fetchUser échoue, on ne redirige pas immédiatement si on a un token
      // On laisse l'utilisateur essayer de continuer
    }
  }

  // Vérifier si on a un token dans localStorage même si le store n'est pas initialisé
  if (import.meta.client && !authStore.token) {
    const token = localStorage.getItem("token");
    if (token) {
      authStore.setAuth({ token });
      try {
        await authStore.fetchUser();
      } catch (error) {
        console.warn("[Auth middleware] Failed to fetch user from localStorage token:", error);
      }
    }
  }

  // --- CAS 1: L'utilisateur N'EST PAS authentifié ---
  // On vérifie à la fois isAuthenticated et la présence d'un token
  const hasToken = authStore.token || (import.meta.client && localStorage.getItem("token"));
  
  if (!authStore.isAuthenticated && !hasToken) {
    if (!isGoingToPublicRoute) {
      // Pas authentifié et essaie d'accéder à une page privée -> redirection.
      return navigateTo("/login");
    }
    // Laisse passer vers les pages publiques.
    return;
  }
  
  // Si on a un token mais pas d'utilisateur encore, on laisse passer (l'utilisateur sera chargé en arrière-plan)
  if (hasToken && !authStore.user && !isGoingToPublicRoute) {
    // Ne pas bloquer, laisser l'application charger
    return;
  }

  // --- CAS 2: L'utilisateur EST authentifié ---
  if (isGoingToPublicRoute && to.path !== "/home") {
    // Déjà connecté et essaie d'aller sur /login ou /register -> redirection vers la page d'accueil selon le rôle.
    // Mais /home reste accessible même si authentifié (c'est la landing page publique)
    if (authStore.hasRole("Client")) {
      return navigateTo("/client");
    } else {
      return navigateTo("/dashboard");
    }
  }

  // --- CAS 3: Vérification des permissions pour la route cible ---
  const requiredPermission = to.meta.permission as string | undefined;

  if (requiredPermission && !authStore.can(requiredPermission)) {
    useToast().add({ title: "Accès non autorisé", color: "error" });
    return false;
  }

  // Si tout est en ordre, la navigation continue.
  return;
});
