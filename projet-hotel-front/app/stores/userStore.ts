import { defineStore } from "pinia";
import type { AxiosInstance } from "axios";

export interface User {
  id: number;
  name: string;
  email: string;
  roles: { name: string }[];
}

export const useUserStore = defineStore("user", {
  state: () => ({
    users: [] as User[],
    pagination: {} as any,
    isLoading: false,
  }),

  actions: {
    async fetchAll() {
      if (this.users.length > 0) return;
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).get("/users");
        console.log("datas : ", response.data.data);
        this.users = response.data.data;
        this.pagination = {
          // métadonnées
          total: response.data.total,
          current_page: response.data.current_page,
          last_page: response.data.last_page,
        };
      } catch (error) {
        console.error("Erreur lors du chargement des utilisateurs:", error);
        useToast().add({
          title: "Erreur",
          description: "Impossible de charger les utilisateurs.",
          color: "error",
        });
      } finally {
        this.isLoading = false;
      }
    },

    async create(
      userData: Omit<User, "id" | "roles"> & {
        role: string;
        password_confirmation: string;
      }
    ) {
      const { $api } = useNuxtApp();
      const response = await ($api as AxiosInstance).post("/users", userData);
      this.users.unshift(response.data.user);
    },

    async update(
      id: number,
      userData: Partial<User> & {
        role?: string;
        password?: string;
        password_confirmation?: string;
      }
    ) {
      const { $api } = useNuxtApp();
      const response = await ($api as AxiosInstance).put(
        `/users/${id}`,
        userData
      );
      const index = this.users.findIndex((u) => u.id === id);
      if (index !== -1) {
        this.users[index] = response.data.user;
      }
    },

    async remove(id: number) {
      const { $api } = useNuxtApp();
      await ($api as AxiosInstance).delete(`/users/${id}`);
      this.users = this.users.filter((u) => u.id !== id);
    },
  },
});
