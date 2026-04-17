// orderStore.ts
import { defineStore } from "pinia";
import type { AxiosInstance } from "axios";
import type { MenuItem } from "./menuItemStore";

export interface OrderItem {
  id: number;
  quantity: number;
  unit_price: number;
  line_total: number;
  menu_item: MenuItem;
}

export interface Order {
  id: number;
  location: string;
  order_type: "room_service" | "restaurant";
  status: "pending" | "preparing" | "ready" | "delivered" | "cancelled";
  total: number;
  note?: string;
  created_at: string;
  order_items: OrderItem[];
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

export const useOrderStore = defineStore("order", {
  state: () => ({
    orders: [] as Order[],
    isLoading: false,
  }),

  actions: {
    async fetchAll(page: number = 1, perPage: number = 15) {
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).get("/orders", {
          params: { page, per_page: perPage }
        });
        // Force le remplacement complet du tableau pour garantir la réactivité
        this.orders = [...response.data.data];
        return response.data;
      } catch (error) {
        console.error("Erreur fetchAll orders:", error);
        useToast().add({
          title: "Erreur",
          description: "Impossible de charger les commandes.",
          color: "error",
        });
        // Important : ne pas laisser isLoading à true en cas d'erreur
        this.orders = [];
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async create(payload: {
      user_id?: number;
      order_type: string;
      location: string;
      note?: string;
      items: { menu_item_id: number; quantity: number }[];
    }) {
      const { $api } = useNuxtApp();
      const response = await ($api as AxiosInstance).post("/orders", payload);
      // Utilise splice ou unshift avec spread pour garantir la réactivité
      if (response.data.data) {
        this.orders = [response.data.data, ...this.orders];
      }
      // Retourner la réponse complète pour gérer la redirection FedaPay
      return response.data;
    },

    async updateStatus(id: number, status: string) {
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).put(`/orders/${id}`, {
          status,
        });

        const index = this.orders.findIndex((o) => o.id === id);
        if (index !== -1 && this.orders[index]) {
          // SOLUTION 1 : Mettre à jour les propriétés individuellement (RECOMMANDÉ)
          Object.assign(this.orders[index], response.data.data);

          // OU SOLUTION 2 : Recréer le tableau complet
          // this.orders = this.orders.map(order =>
          //   order.id === id ? response.data.data : order
          // );
        }
      } catch (error) {
        console.error("Erreur updateStatus:", error);
        throw error;
      }
    },

    async remove(id: number) {
      const { $api } = useNuxtApp();
      await ($api as AxiosInstance).delete(`/orders/${id}`);
      // Recréer un nouveau tableau pour forcer la réactivité
      this.orders = this.orders.filter((o) => o.id !== id);
    },

    async fetchMyOrders(page: number = 1, perPage: number = 10) {
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).get("/orders/my", {
          params: { page, per_page: perPage }
        });
        this.orders = response.data.data || [];
        return response.data;
      } catch (error) {
        console.error("Erreur fetchMyOrders:", error);
        useToast().add({
          title: "Erreur",
          description: "Impossible de charger vos commandes.",
          color: "error",
        });
        this.orders = [];
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
  },
});
