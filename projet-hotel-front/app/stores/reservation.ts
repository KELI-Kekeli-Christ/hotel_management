import { defineStore } from "pinia";
import type { AxiosInstance } from "axios";
import type { User } from "./userStore";
import type { Chambre } from "./chambre";

// Interface pour une Réservation
export interface Reservation {
  id: number;
  user_id: number;
  chambre_id: number;
  numero_reservation: string;
  date_arrive: string;
  date_depart: string;
  status: string;
  user: User; // Relation chargée
  chambre: Chambre; // Relation chargée
}

export const useReservationStore = defineStore("reservation", {
  state: () => ({
    reservations: [] as Reservation[],
    isLoading: false,
  }),

  actions: {
    async fetchAll() {
      if (this.reservations.length > 0) return;
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).get("/reservations");
        this.reservations = response.data.data;
      } catch (error) {
        useToast().add({
          title: "Erreur",
          description: "Impossible de charger les réservations.",
          color: "error",
        });
      } finally {
        this.isLoading = false;
      }
    },

    async create(payload: {
      user_id: number;
      chambre_id: number;
      date_arrive: string;
      date_depart: string;
      phone_number?: string;
    }) {
      const { $api } = useNuxtApp();
      const response = await ($api as AxiosInstance).post(
        "/reservations",
        payload
      );
      // La réponse peut contenir reservation ou error selon le cas
      if (response.data.reservation) {
        this.reservations.unshift(response.data.reservation);
      }
      // Retourner toute la réponse pour accéder à redirect_url si présent
      return response.data;
    },

    async update(
      id: number,
      payload: { date_arrive: string; date_depart: string }
    ) {
      const { $api } = useNuxtApp();
      const response = await ($api as AxiosInstance).put(
        `/reservations/${id}`,
        payload
      );
      const index = this.reservations.findIndex((r) => r.id === id);
      if (index !== -1 && response.data.reservation) {
        this.reservations[index] = {
          ...this.reservations[index],
          ...response.data.reservation,
        };
      }
      // Retourner toute la réponse pour accéder à redirect_url si présent
      return response.data;
    },

    async remove(id: number) {
      const { $api } = useNuxtApp();
      await ($api as AxiosInstance).delete(`/reservations/${id}`);
      const index = this.reservations.findIndex((r) => r.id === id);
      // Au lieu de supprimer, on met à jour le statut pour refléter l'annulation
      if (index !== -1 && this.reservations[index]) {
        this.reservations[index].status = "cancelled";
      }
    },
  },
});
