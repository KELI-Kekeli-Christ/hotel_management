// stores/auth.ts (Pinia)
import { defineStore } from "pinia";
import type { AxiosInstance } from "axios";
import { AxiosError } from "axios";

interface Role {
  name: string;
}
interface User {
  id: number;
  name: string;
  email: string;
  roles: Role[];
  permissions_list: string[];
  roles_list?: string[];
}

function isTokenExpired(token: string | null): boolean {
  if (!token) return true;
  try {
    const payloadBase64 = token.split(".")[1];
    if (!payloadBase64) return true;
    const decodedJson = atob(payloadBase64);
    const decoded = JSON.parse(decodedJson);
    const exp = decoded.exp;
    return Date.now() >= exp * 1000;
  } catch {
    return true;
  }
}
export const useAuthStore = defineStore("auth", {
  state: () => ({
    token: null as string | null,
    user: null as User | null,
  }),

  getters: {
    isAuthenticated: (state) =>
      !!state.token && !!state.user && !isTokenExpired(state.token),
    hasRole: (state) => (role: string) =>
      !!state.user?.roles?.some((r) => r.name === role),
    getUser: (state) => state.user,
    can: (state) => (permission: string) =>
      !!state.user?.permissions_list?.includes(permission),
  },

  actions: {
    /**
     * Action pour définir le token et l'utilisateur, et persister l'état.
     * C'est une action centrale pour éviter la duplication de code.
     */
    setAuth({ token, user }: { token: string; user?: User }) {
      this.token = token;
      if (user) this.user = user;

      const { $api } = useNuxtApp();
      ($api as AxiosInstance).defaults.headers.common[
        "Authorization"
      ] = `Bearer ${token}`;

      if (import.meta.client) {
        localStorage.setItem("token", token);
        if (user) {
          localStorage.setItem("user", JSON.stringify(user));
        }
      }
    },

    /**
     * Action appelée par le plugin au démarrage pour hydrater le store
     * depuis le localStorage.
     */
    init() {
      if (!import.meta.client) return;
      if ((this as any)._initialized) return;
      (this as any)._initialized = true;

      const storedToken = localStorage.getItem("token");
      const storedUser = localStorage.getItem("user");
      
      if (storedToken && !isTokenExpired(storedToken)) {
        this.token = storedToken;
        
        // Configurer l'API avec le token
        try {
          const nuxt = useNuxtApp();
          const api = (nuxt as any)?.$api as AxiosInstance | undefined;
          if (api) {
            api.defaults = api.defaults || {};
            api.defaults.headers = api.defaults.headers || {};
            api.defaults.headers.common = api.defaults.headers.common || {};
            api.defaults.headers.common[
              "Authorization"
            ] = `Bearer ${this.token}`;
          }
        } catch (e) {
          console.warn(
            "[Auth.init] useNuxtApp() failed or $api not available:",
            e
          );
        }
        
        // Si on a un utilisateur en cache, on l'utilise temporairement
        if (storedUser) {
          try {
            this.user = JSON.parse(storedUser);
          } catch (e) {
            console.warn("[Auth.init] invalid user data in localStorage:", e);
            this.user = null;
          }
        }
        
        // Note: L'utilisateur sera récupéré par le plugin 02.auth.ts via fetchUser()
        // pour avoir des données à jour
      } else if (storedToken && isTokenExpired(storedToken)) {
        // Token expiré, on nettoie
        console.warn("[Auth.init] Token expired, clearing auth");
        this.clearAuth();
      } else {
        // Pas de token, on nettoie
        this.clearAuth();
      }
    },

    /**
     * Action pour nettoyer l'état d'authentification.
     */
    clearAuth() {
      this.token = null;
      this.user = null;

      if (import.meta.client) {
        localStorage.removeItem("token");
        localStorage.removeItem("user");
      }

      const { $api } = useNuxtApp();
      delete ($api as AxiosInstance).defaults.headers.common["Authorization"];
    },

    async login(credentials: { email: string; password: string }) {
      const nuxt = useNuxtApp();
      const api = (nuxt as any)?.$api as AxiosInstance | undefined;
      if (!api) throw new Error("API instance not available");

      try {
        const res = await api.post("/login", credentials);
        const data = res.data;

        if (!data.access_token || !data.user) {
          throw new Error(
            "Réponse de l'API de connexion invalide."
          );
        }
        this.setAuth({ token: data.access_token, user: data.user });
      } catch (error) {
        this.clearAuth();
        throw error;
      }
    },

    /**
     * Récupère les informations de l'utilisateur à partir du token existant.
     * C'est la méthode d'initialisation principale.
     */
    async fetchUser() {
      if (!this.token) {
        this.clearAuth();
        return;
      }
      
      let api: AxiosInstance | undefined = undefined;
      try {
        const nuxt = useNuxtApp();
        api = (nuxt as any)?.$api as AxiosInstance | undefined;
        if (!api) {
          console.warn("[Auth.fetchUser] API not available");
          return;
        }
        
        const { data } = await (api as AxiosInstance).get("/me");
        this.user = data;
        
        // Sauvegarder l'utilisateur dans localStorage
        if (import.meta.client) {
          localStorage.setItem("user", JSON.stringify(data));
        }
      } catch (error: any) {
        console.error("Erreur lors de la récupération de l'utilisateur:", error);
        
        // Si c'est une erreur 401, le token est invalide
        if (error?.response?.status === 401) {
          this.clearAuth();
        } else {
          // Pour d'autres erreurs, on garde le token mais on nettoie l'utilisateur
          this.user = null;
        }
      }
    },

    /**
     * Tente de rafraîchir le token JWT.
     */
    async refreshToken() {
      if (!this.token) throw new Error("No token to refresh");
      let api: AxiosInstance | undefined = undefined;
      try {
        const nuxt = useNuxtApp();
        api = (nuxt as any)?.$api as AxiosInstance | undefined;
        const { data } = await (api as AxiosInstance).post("/refresh");
        this.setAuth({ token: data.access_token });
        return data.access_token;
      } catch (error) {
        this.logout();
        throw error;
      }
    },
    async logout() {
      let api: AxiosInstance | undefined = undefined;
      try {
        const nuxt = useNuxtApp();
        api = (nuxt as any)?.$api as AxiosInstance | undefined;
      } catch (e) {
        // useNuxtApp() peut échouer si appelé en dehors du contexte — on ignore
        api = undefined;
      }
      if (this.token && api) {
        try {
          await api.post("/logout");
        } catch (error) {
          console.warn(
            "[Auth.logout] server logout failed, continuing client-side cleanup",
            error
          );
        }
      }
      this.clearAuth();
      await navigateTo("/login");
    },

    async register(credentials: {
      name: string;
      email: string;
      password: string;
      password_confirmation?: string;
    }) {
      const nuxt = useNuxtApp();
      const api = nuxt.$api as AxiosInstance | undefined;
      if (!api) throw new Error("API instance not available");

      if (!credentials.name || !credentials.email || !credentials.password) {
        throw new Error("Champs requis manquants");
      }
      if (
        credentials.password_confirmation !== undefined &&
        credentials.password !== credentials.password_confirmation
      ) {
        throw new Error("La confirmation du mot de passe ne correspond pas");
      }

      try {
        const res = await api.post("/register", credentials);
        const data = res.data;

        // Le backend retourne { message, user } sans token
        // L'utilisateur doit se connecter après l'inscription
        return data;
      } catch (err: unknown) {
        const axiosErr = err as AxiosError;
        if (axiosErr.isAxiosError) {
          console.error(
            "[Auth.register] axios error:",
            axiosErr.response?.data || axiosErr.message
          );
        } else {
          console.error("[Auth.register] error:", err);
        }
        throw err;
      }
    },
  },
});
