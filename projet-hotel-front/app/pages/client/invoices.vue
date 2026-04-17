<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useInvoiceStore, type Invoice } from "~/stores/invoiceStore";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
});

const invoiceStore = useInvoiceStore();
const authStore = useAuthStore();

const invoices = ref<Invoice[]>([]);
const isLoading = ref(false);

const formatPrice = (price: number) =>
  new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "XOF",
  }).format(price);

const formatDate = (date?: string) =>
  date
    ? new Date(date).toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "long",
        year: "numeric",
      })
    : "-";

const getStatusBadge = (status: string) => {
  const statusMap: Record<string, { color: string; label: string }> = {
    unpaid: { color: "amber", label: "Non payée" },
    paid: { color: "success", label: "Payée" },
    void: { color: "error", label: "Annulée" },
  };
  return statusMap[status] || { color: "neutral", label: status };
};

async function fetchPdfBlob(invoice: Invoice) {
  const nuxt = useNuxtApp();
  const api = nuxt.$api as any;

  // Afficher un indicateur de chargement
  const loadingToast = useToast().add({
    title: "Génération du PDF",
    description: "Veuillez patienter...",
    color: "primary",
    timeout: 0,
  });

  try {
    const response = await api.get(`/invoices/${invoice.id}/pdf`, {
      responseType: "blob",
      headers: {
        Accept: "application/pdf",
      },
    });

    // Vérifier le type de contenu
    const contentType = response.headers?.['content-type'] || response.headers?.['Content-Type'] || '';
    
    // Si c'est du JSON (erreur), parser le message
    if (contentType.includes('application/json') || contentType.includes('text/')) {
      const text = await new Promise<string>((resolve) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result as string);
        reader.readAsText(response.data);
      });
      
      try {
        const errorData = JSON.parse(text);
        throw new Error(errorData.message || "Erreur lors de la génération du PDF");
      } catch (parseError) {
        throw new Error(text || "Erreur lors de la génération du PDF");
      }
    }

    // Vérifier que c'est bien un PDF
    if (!contentType.includes('application/pdf') && !(response.data instanceof Blob)) {
      throw new Error("Le serveur n'a pas retourné un PDF valide");
    }

    const blob = new Blob([response.data], { type: "application/pdf" });

    if (blob.size === 0) {
      throw new Error("Le fichier PDF est vide ou corrompu.");
    }

    return { blob, loadingToast };
  } catch (err: any) {
    // Fermer le toast de chargement en cas d'erreur
    if (loadingToast) {
      useToast().remove(loadingToast.id);
    }
    throw err;
  }
}

async function handleViewPdf(invoice: Invoice) {
  try {
    const { blob, loadingToast } = await fetchPdfBlob(invoice);
    
    // Fermer le toast de chargement
    if (loadingToast) {
      useToast().remove(loadingToast.id);
    }

    // Ouvrir le PDF dans un nouvel onglet pour visualisation
    const urlObject = window.URL.createObjectURL(blob);
    window.open(urlObject, '_blank');

    // Nettoyer après un délai
    setTimeout(() => {
      window.URL.revokeObjectURL(urlObject);
    }, 1000);

    useToast().add({
      title: "PDF ouvert",
      description: "La facture est maintenant affichée dans un nouvel onglet",
      color: "success",
    });
  } catch (err: any) {
    console.error("PDF view error:", err);
    
    let errorMessage = "Impossible d'afficher la facture";
    
    if (err.response?.data instanceof Blob) {
      try {
        const text = await err.response.data.text();
        try {
          const errorData = JSON.parse(text);
          errorMessage = errorData.message || errorMessage;
        } catch {
          errorMessage = text || errorMessage;
        }
      } catch (blobError) {
        errorMessage = `Erreur ${err.response?.status || 'inconnue'}: ${errorMessage}`;
      }
    } else if (err.response?.data?.message) {
      errorMessage = err.response.data.message;
    } else if (err.message) {
      errorMessage = err.message;
    }
    
    useToast().add({
      title: "Erreur",
      description: errorMessage,
      color: "error",
      timeout: 5000,
    });
  }
}

async function handleDownloadPdf(invoice: Invoice) {
  try {
    const { blob, loadingToast } = await fetchPdfBlob(invoice);
    
    // Fermer le toast de chargement
    if (loadingToast) {
      useToast().remove(loadingToast.id);
    }

    // Créer et déclencher le téléchargement
    const urlObject = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = urlObject;
    a.download = `facture-${invoice.invoice_number}.pdf`;
    document.body.appendChild(a);
    a.click();

    setTimeout(() => {
      window.URL.revokeObjectURL(urlObject);
      document.body.removeChild(a);
    }, 100);

    useToast().add({
      title: "Téléchargement réussi",
      description: "La facture a été téléchargée avec succès",
      color: "success",
    });
  } catch (err: any) {
    console.error("PDF download error:", err);
    
    let errorMessage = "Impossible de télécharger la facture";
    
    if (err.response?.data instanceof Blob) {
      try {
        const text = await err.response.data.text();
        try {
          const errorData = JSON.parse(text);
          errorMessage = errorData.message || errorMessage;
        } catch {
          errorMessage = text || errorMessage;
        }
      } catch (blobError) {
        errorMessage = `Erreur ${err.response?.status || 'inconnue'}: ${errorMessage}`;
      }
    } else if (err.response?.data?.message) {
      errorMessage = err.response.data.message;
    } else if (err.message) {
      errorMessage = err.message;
    } else if (err.response?.status) {
      errorMessage = `Erreur ${err.response.status}: ${errorMessage}`;
    }
    
    useToast().add({
      title: "Erreur",
      description: errorMessage,
      color: "error",
      timeout: 5000,
    });
  }
}

onMounted(async () => {
  isLoading.value = true;
  try {
    const response = await (useNuxtApp().$api as any).get("/invoices/my");
    invoices.value = response.data.data || [];
  } catch (error) {
    console.error("Error loading invoices:", error);
    useToast().add({
      title: "Erreur",
      description: "Impossible de charger vos factures",
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Mes Factures" />

    <div class="p-6">
      <div v-if="isLoading" class="flex justify-center py-12">
        <div
          class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"
        ></div>
      </div>

      <div
        v-else-if="invoices.length === 0"
        class="text-center py-12"
      >
        <UIcon
          name="i-lucide-file-text"
          class="w-16 h-16 text-gray-400 mx-auto mb-4"
        />
        <p class="text-gray-500 dark:text-gray-400">
          Vous n'avez aucune facture
        </p>
      </div>

      <div v-else class="space-y-4">
        <UCard
          v-for="invoice in invoices"
          :key="invoice.id"
          class="overflow-hidden"
        >
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold">
                  {{ invoice.invoice_number }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Réservation
                  {{ invoice.reservation?.numero_reservation || "N/A" }}
                </p>
                <p
                  v-if="invoice.reservation?.chambre"
                  class="text-sm text-gray-500 dark:text-gray-400"
                >
                  Chambre {{ invoice.reservation.chambre.numero_chambre }}
                </p>
              </div>
              <UBadge
                :color="getStatusBadge(invoice.status).color"
                variant="subtle"
              >
                {{ getStatusBadge(invoice.status).label }}
              </UBadge>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Date d'émission
                </p>
                <p class="font-medium">{{ formatDate(invoice.issued_date) }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Date d'échéance
                </p>
                <p class="font-medium">{{ formatDate(invoice.due_date) }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                <p
                  class="text-xl font-bold text-primary-600 dark:text-primary-400"
                >
                  {{ formatPrice(invoice.total_amount) }}
                </p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
              <UButton
                label="Visualiser"
                icon="i-lucide-file-text"
                color="neutral"
                variant="outline"
                @click="handleViewPdf(invoice)"
              />
              <UButton
                label="Télécharger PDF"
                icon="i-lucide-download"
                color="primary"
                @click="handleDownloadPdf(invoice)"
              />
            </div>
          </div>
        </UCard>
      </div>
    </div>
  </UDashboardPanel>
</template>

