// statsStore.ts
import { defineStore } from "pinia";
import type { AxiosInstance } from "axios";

export interface TopItem {
  name: string;
  quantity?: number;
  total_quantity?: number;
}

export interface RecentOrder {
  id: number;
  total: number;
  date: string;
  location: string;
}

export interface StatsData {
  todayRevenue: number;
  monthlyOrders: number;
  topItem: string | null;
  topItems: TopItem[];
  recentOrders: RecentOrder[];
}

export const useStatsStore = defineStore("stats", {
  state: () => ({
    data: null as StatsData | null,
    isLoading: false,
  }),

  actions: {
    async fetchStats() {
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).get("/stats");
        this.data = response.data;
      } catch (error) {
        useToast().add({
          title: "Erreur",
          description: "Impossible de charger les statistiques.",
          color: "error",
        });
      } finally {
        this.isLoading = false;
      }
    },
  },
});
