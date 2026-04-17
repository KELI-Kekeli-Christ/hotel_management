// menuItemStore.ts
import { defineStore } from "pinia";
import type { AxiosInstance } from "axios";

export interface MenuItem {
  id: number;
  name: string;
  category: string;
  price: number;
  available: boolean;
  description?: string;
  image?: string;
  image_url?: string;
}

export const useMenuItemStore = defineStore("menuItem", {
  state: () => ({
    items: [] as MenuItem[],
    isLoading: false,
  }),

  actions: {
    async fetchAll() {
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).get("/menu-items");
        this.items = response.data.data;
      } catch (error) {
        useToast().add({
          title: "Erreur",
          description: "Impossible de charger le menu.",
          color: "error",
        });
      } finally {
        this.isLoading = false;
      }
    },

    async create(itemData: FormData) {
      const { $api } = useNuxtApp();
      const response = await ($api as AxiosInstance).post(
        "/menu-items",
        itemData,
        {
          headers: { "Content-Type": "multipart/form-data" },
        }
      );
      this.items.unshift(response.data.menuItem);
    },

    async update(id: number, itemData: FormData) {
      const { $api } = useNuxtApp();
      itemData.append("_method", "PUT");
      const response = await ($api as AxiosInstance).post(
        `/menu-items/${id}`,
        itemData,
        {
          headers: { "Content-Type": "multipart/form-data" },
        }
      );

      const index = this.items.findIndex((item) => item.id === id);
      if (index !== -1) {
        this.items[index] = response.data.menuItem;
      }
    },

    async remove(id: number) {
      const { $api } = useNuxtApp();
      await ($api as AxiosInstance).delete(`/menu-items/${id}`);
      this.items = this.items.filter((item) => item.id !== id);
    },
  },
});
