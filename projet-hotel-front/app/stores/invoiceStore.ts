import { defineStore } from "pinia";
import type { AxiosInstance } from "axios";
import type { Reservation } from "./reservation"; // Importer pour la relation

export interface InvoiceItem {
  id: number;
  invoice_id: number;
  description: string;
  quantity: number;
  unit_price: number;
  line_total: number;
}

export interface Invoice {
  id: number;
  reservation_id: number;
  invoice_number: string;
  total_amount: number;
  amount_paid?: number;
  status: "unpaid" | "paid" | "void";
  issued_date: string;
  due_date?: string;
  items?: InvoiceItem[];
  reservation: Reservation; // Contient les détails du client et de la chambre
}

export const useInvoiceStore = defineStore("invoice", {
  state: () => ({
    invoices: [] as Invoice[],
    currentInvoice: null as Invoice | null,
    isLoading: false,
  }),

  actions: {
    async fetchAll() {
      if (this.invoices.length > 0 && !this.isLoading) return;
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).get("/invoices");
        this.invoices = response.data.data;
      } catch (error) {
        useToast().add({
          title: "Erreur",
          description: "Impossible de charger les factures.",
          color: "error",
        });
      } finally {
        this.isLoading = false;
      }
    },

    async generate(reservationId: number) {
      const { $api } = useNuxtApp();
      const response = await ($api as AxiosInstance).post(
        `/invoices/generate/${reservationId}`
      );
      this.invoices.unshift(response.data.invoice); // Ajoute la nouvelle facture à la liste
      return response.data.invoice;
    },

    async markAsPaid(id: number) {
      const { $api } = useNuxtApp();
      const response = await ($api as AxiosInstance).patch(
        `/invoices/${id}/pay`
      );
      const index = this.invoices.findIndex((inv) => inv.id === id);
      if (index !== -1) {
        this.invoices[index] = response.data.invoice;
      }
      if (this.currentInvoice?.id === id) {
        this.currentInvoice = response.data.invoice;
      }
    },

    async fetchOne(id: number) {
      console.log("[InvoiceStore] fetchOne called with ID:", id);
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        console.log("[InvoiceStore] Making API call to /invoices/" + id);
        const response = await ($api as AxiosInstance).get(`/invoices/${id}`);
        console.log("[InvoiceStore] API response:", response);
        console.log("[InvoiceStore] Response data:", response.data);
        
        // La réponse peut être directement l'objet facture ou dans response.data
        this.currentInvoice = response.data?.data || response.data;
        
        console.log("[InvoiceStore] Current invoice set to:", this.currentInvoice);
        
        if (!this.currentInvoice) {
          console.error("[InvoiceStore] Invoice is null after parsing response");
          throw new Error("Facture introuvable");
        }
        
        console.log("[InvoiceStore] Invoice loaded successfully");
      } catch (error: any) {
        console.error("[InvoiceStore] Error fetching invoice:", error);
        console.error("[InvoiceStore] Error response:", error.response);
        console.error("[InvoiceStore] Error message:", error.message);
        
        const errorMessage = error.response?.data?.message || error.message || "Impossible de charger la facture.";
        console.error("[InvoiceStore] Error message to display:", errorMessage);
        
        useToast().add({
          title: "Erreur",
          description: errorMessage,
          color: "error",
        });
        throw error;
      } finally {
        this.isLoading = false;
        console.log("[InvoiceStore] fetchOne finished, isLoading:", this.isLoading);
      }
    },

    async initPayment(id: number, phoneNumber?: string) {
      const { $api } = useNuxtApp();
      try {
        const payload: any = {};
        if (phoneNumber) {
          payload.phone_number = phoneNumber;
        }
        const response = await ($api as AxiosInstance).post(
          `/invoices/${id}/payment`,
          payload
        );
        return response.data;
      } catch (error: any) {
        const errorMessage =
          error.response?.data?.message ||
          error.response?.data?.error ||
          error.message ||
          "Impossible d'initier le paiement.";
        useToast().add({
          title: "Erreur",
          description: errorMessage,
          color: "error",
        });
        throw error;
      }
    },
  },
});
