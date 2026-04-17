// app/plugins/axios.ts
import axios, { type AxiosInstance, type AxiosRequestHeaders } from "axios";
import { defineNuxtPlugin, useRuntimeConfig } from "#app";

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig();
  const baseURL = config.public?.apiBase || "http://localhost:8000/api";

  const api: AxiosInstance = axios.create({
    baseURL: typeof baseURL === "string" ? baseURL : undefined,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    timeout: 15000,
  });

  api.interceptors.request.use((req) => {
    if (import.meta.client) {
      try {
        const token = localStorage.getItem("token");
        if (token) {
          if (!req.headers) req.headers = {} as any;
          req.headers.Authorization = `Bearer ${token}`;
        } else {
          if (req.headers?.Authorization) delete req.headers.Authorization;
        }
        
        // Si c'est un FormData, ne pas définir Content-Type (laisser le navigateur le faire)
        if (req.data instanceof FormData) {
          delete req.headers['Content-Type'];
        }
      } catch (e) {
        console.warn("[API REQUEST INTERCEPTOR] localStorage inaccessible:", e);
      }
    }
    return req;
  });

  api.interceptors.response.use(
    (response) => response,
    async (error) => {
      const originalRequest = error.config;
      const authStore = useAuthStore();
      if (error.response?.status === 401 && !originalRequest._retry) {
        originalRequest._retry = true;

        if (authStore.isAuthenticated) {
          try {
            console.log("Token expiré. Tentative de rafraîchissement...");
            const newToken = await authStore.refreshToken();

            originalRequest.headers["Authorization"] = `Bearer ${newToken}`;
            return api(originalRequest);
          } catch (refreshError) {
            return Promise.reject(refreshError);
          }
        } else {
          authStore.logout();
        }
      }
      return Promise.reject(error);
    }
  );

  nuxtApp.provide("api", api);
});
