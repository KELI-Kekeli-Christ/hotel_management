import { defineStore } from "pinia";
import type { AxiosInstance } from "axios";

export interface Chambre {
  id: number;
  numero_chambre: string;
  type: string;
  prix_nuite: number;
  disponibilite: boolean;
  description?: string;
  photo?: string; // Chemin relatif: chambres/xxx.png
  photo_url?: string; // URL complète: http://domain/storage/chambres/xxx.png
}

export const useChambreStore = defineStore("chambre", {
  state: () => ({
    chambres: [] as Chambre[],
    isLoading: false,
  }),

  getters: {
    // Getter pour garantir que toutes les chambres ont une photo_url
    allChambres: (state) =>
      state.chambres.map((chambre) => ({
        ...chambre,
        photo_url:
          chambre.photo_url ||
          (chambre.photo ? getPhotoUrl(chambre.photo) : undefined),
      })),
  },

  actions: {
    async fetchAll(force = false) {
      // Ne pas recharger si déjà chargé et force = false
      if (this.chambres.length > 0 && !force && !this.isLoading) return;
      this.isLoading = true;
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).get("/chambres");

        // Vérifier la structure de la réponse
        console.log("API Response:", response.data);

        // Laravel pagination renvoie: { data: [...], links: {...}, meta: {...} }
        this.chambres = response.data.data || response.data;

        // Transformer pour s'assurer que photo_url existe
        this.chambres = this.chambres.map((chambre) => ({
          ...chambre,
          photo_url:
            chambre.photo_url ||
            (chambre.photo ? getPhotoUrl(chambre.photo) : undefined),
        }));

        console.log("Chambres chargées:", this.chambres);
      } catch (error) {
        console.error("Erreur lors du chargement des chambres:", error);
        useToast().add({
          title: "Erreur",
          description: "Impossible de charger les chambres.",
          color: "error",
        });
      } finally {
        this.isLoading = false;
      }
    },

    async create(chambreData: FormData) {
      const { $api } = useNuxtApp();
      try {
        const response = await ($api as AxiosInstance).post(
          "/chambres",
          chambreData,
          {
            headers: { "Content-Type": "multipart/form-data" },
          }
        );

        const newChambre = response.data.chambre;
        // S'assurer que photo_url existe
        if (newChambre.photo && !newChambre.photo_url) {
          newChambre.photo_url = getPhotoUrl(newChambre.photo);
        }

        this.chambres.push(newChambre);
        return response.data;
      } catch (error: any) {
        console.error("Erreur création chambre:", error);
        throw error;
      }
    },

    async update(id: number, chambreData: FormData) {
      const { $api } = useNuxtApp();
      try {
        chambreData.append("_method", "PUT");
        const response = await ($api as AxiosInstance).post(
          `/chambres/${id}`,
          chambreData,
          {
            headers: { "Content-Type": "multipart/form-data" },
          }
        );

        const updatedChambre = response.data.chambre;
        // S'assurer que photo_url existe
        if (updatedChambre.photo && !updatedChambre.photo_url) {
          updatedChambre.photo_url = getPhotoUrl(updatedChambre.photo);
        }

        const index = this.chambres.findIndex((c) => c.id === id);
        if (index !== -1) {
          this.chambres[index] = updatedChambre;
        }
        return response.data;
      } catch (error: any) {
        console.error("Erreur mise à jour chambre:", error);
        throw error;
      }
    },

    async remove(id: number) {
      const { $api } = useNuxtApp();
      await ($api as AxiosInstance).delete(`/chambres/${id}`);
      this.chambres = this.chambres.filter((c) => c.id !== id);
    },
  },
});

/**
 * Helper pour construire l'URL complète de la photo
 * @param photoPath - Chemin relatif comme "chambres/xxx.png"
 * @returns URL complète comme "http://domain/storage/chambres/xxx.png"
 */
function getPhotoUrl(photoPath: string): string {
  const config = useRuntimeConfig();
  const baseUrl = config.public.apiBase || "http://localhost:8000";

  // Si le chemin commence déjà par http, le retourner tel quel
  if (photoPath.startsWith("http")) {
    return photoPath;
  }

  // Construire l'URL complète
  return `${baseUrl}/storage/${photoPath}`;
}
