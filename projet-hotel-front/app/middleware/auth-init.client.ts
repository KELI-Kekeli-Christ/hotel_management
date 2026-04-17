// ~/plugins/auth-init.client.ts
import { defineNuxtPlugin } from "#app";

export default defineNuxtPlugin(() => {
  // initialisation client-only du store auth
  try {
    const authStore = useAuthStore();
    authStore.init();
  } catch (e) {
    // si useAuthStore n'est pas disponible, on l'ignore proprement
    // (utile pendant tests/unit ou si Pinia pas ready)
    // eslint-disable-next-line no-console
    console.warn("[auth-init] init failed", e);
  }
});
