<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useInvoiceStore, type Invoice } from "~/stores/invoiceStore";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
});

const route = useRoute();
const router = useRouter();
const invoiceStore = useInvoiceStore();
const authStore = useAuthStore();
const toast = useToast();

const invoice = ref<Invoice | null>(null);
const isLoading = ref(true);
const errorMessage = ref<string | null>(null);

const invoiceId = computed(() => {
  const id = route.params.id;
  const numId = typeof id === "string" ? Number(id) : Number(id);
  return isNaN(numId) ? null : numId;
});

const loadInvoice = async () => {
  if (!invoiceId.value) {
    errorMessage.value = "ID de facture invalide";
    isLoading.value = false;
    return;
  }

  isLoading.value = true;
  errorMessage.value = null;

  try {
    const nuxt = useNuxtApp();
    const api = nuxt.$api as any;

    const response = await api.get(`/invoices/${invoiceId.value}`);
    invoice.value = response.data;

    if (!invoice.value || !invoice.value.id) {
      throw new Error("Données de facture invalides");
    }
  } catch (error: any) {
    console.error("Error loading invoice:", error);
    errorMessage.value =
      error.response?.data?.message ||
      error.message ||
      "Impossible de charger la facture.";

    toast.add({
      title: "Erreur",
      description: errorMessage.value,
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
};

onMounted(loadInvoice);

const formatPrice = (value: number) =>
  new Intl.NumberFormat("fr-FR", { style: "currency", currency: "XOF" }).format(
    value ?? 0
  );

const formatDate = (date?: string) =>
  date
    ? new Date(date).toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "long",
        year: "numeric",
      })
    : "-";

const getStatusColor = computed(() => {
  if (!invoice.value) return "neutral";
  if (invoice.value.status === "paid") return "success";
  if (invoice.value.status === "void") return "error";
  return "neutral";
});

const tableColumns = [
  { id: "description", header: "Description" },
  { id: "quantity", header: "Quantité" },
  { id: "unit_price", header: "Prix unitaire" },
  { id: "line_total", header: "Total" },
];

async function fetchPdfBlob() {
  if (!invoice.value) {
    throw new Error("Aucune facture sélectionnée");
  }

  const nuxt = useNuxtApp();
  const api = nuxt.$api as any;

  // Afficher un indicateur de chargement
  const loadingToast = toast.add({
    title: "Génération du PDF",
    description: "Veuillez patienter...",
    color: "primary",
    timeout: 0,
  });

  try {
    const response = await api.get(`/invoices/${invoice.value.id}/pdf`, {
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
      toast.remove(loadingToast.id);
    }
    throw err;
  }
}

async function handleViewPdf() {
  try {
    const { blob, loadingToast } = await fetchPdfBlob();
    
    // Fermer le toast de chargement
    if (loadingToast) {
      toast.remove(loadingToast.id);
    }

    // Ouvrir le PDF dans un nouvel onglet pour visualisation
    const urlObject = window.URL.createObjectURL(blob);
    window.open(urlObject, '_blank');

    // Nettoyer après un délai
    setTimeout(() => {
      window.URL.revokeObjectURL(urlObject);
    }, 1000);

    toast.add({
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
    
    toast.add({
      title: "Erreur",
      description: errorMessage,
      color: "error",
      timeout: 5000,
    });
  }
}

async function handleDownloadPdf() {
  try {
    const { blob, loadingToast } = await fetchPdfBlob();
    
    // Fermer le toast de chargement
    if (loadingToast) {
      toast.remove(loadingToast.id);
    }

    // Créer et déclencher le téléchargement
    const urlObject = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = urlObject;
    a.download = `facture-${invoice.value.invoice_number}.pdf`;
    document.body.appendChild(a);
    a.click();

    setTimeout(() => {
      window.URL.revokeObjectURL(urlObject);
      document.body.removeChild(a);
    }, 100);

    toast.add({
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
    
    toast.add({
      title: "Erreur",
      description: errorMessage,
      color: "error",
      timeout: 5000,
    });
  }
}

const isPaying = ref(false);

async function handlePayInvoice() {
  if (!invoice.value || !invoiceId.value) {
    return;
  }

  if (invoice.value.status === "paid") {
    toast.add({
      title: "Information",
      description: "Cette facture est déjà payée",
      color: "info",
    });
    return;
  }

  isPaying.value = true;

  try {
    const response = await invoiceStore.initPayment(invoiceId.value);
    
    // Si la réponse contient un redirect_url (FedaPay), rediriger vers la page de paiement
    if (response?.redirect_url) {
      toast.add({
        title: "Redirection vers le paiement",
        description: "Vous allez être redirigé vers la page de paiement",
        color: "success",
      });
      // Rediriger vers FedaPay
      window.location.href = response.redirect_url;
      return;
    }

    toast.add({
      title: "Paiement initié",
      description: "Le paiement a été initié avec succès",
      color: "success",
    });
  } catch (error: any) {
    console.error("Error initiating payment:", error);
    // L'erreur est déjà gérée dans le store
  } finally {
    isPaying.value = false;
  }
}
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Détail de la Facture">
      <template #right>
        <UButton
          label="Retour"
          icon="i-heroicons-arrow-left"
          variant="ghost"
          @click="router.push('/client/invoices')"
        />
        <UButton
          label="Visualiser"
          icon="i-lucide-eye"
          color="neutral"
          variant="outline"
          @click="handleViewPdf"
        />
        <UButton
          label="Télécharger PDF"
          icon="i-heroicons-arrow-down-tray"
          color="primary"
          @click="handleDownloadPdf"
        />
      </template>
    </UDashboardNavbar>

    <div v-if="isLoading" class="p-8 text-center">
      <div
        class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"
      ></div>
      <p class="mt-2">Chargement de la facture...</p>
    </div>

    <div v-else-if="errorMessage" class="p-8 text-center text-red-500">
      <p class="text-lg font-semibold">Erreur de chargement</p>
      <p class="mt-2">{{ errorMessage }}</p>
      <UButton class="mt-4" color="primary" @click="loadInvoice"
        >Réessayer</UButton
      >
      <UButton
        class="mt-4 ml-2"
        color="neutral"
        variant="ghost"
        @click="router.push('/client/invoices')"
        >Retour à la liste</UButton
      >
    </div>

    <div v-else-if="invoice" class="p-6 space-y-6">
      <!-- En-tête de la facture -->
      <UCard class="relative overflow-hidden">
        <div
          class="absolute inset-0 bg-gradient-to-br from-primary-500/10 to-primary-400/10 dark:from-primary-400/10 dark:to-primary-500/10 opacity-50"
        ></div>
        <div
          class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
        >
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
              Facture #{{ invoice.invoice_number }}
            </h1>
            <UBadge
              :color="getStatusColor"
              variant="subtle"
              class="mt-2 text-lg"
            >
              {{ invoice.status }}
            </UBadge>
          </div>
          <div class="flex-shrink-0 flex gap-3">
            <UButton
              label="Visualiser"
              icon="i-lucide-eye"
              color="neutral"
              variant="outline"
              size="lg"
              class="shadow-lg"
              @click="handleViewPdf"
            />
            <UButton
              label="Télécharger PDF"
              icon="i-heroicons-arrow-down-tray"
              color="primary"
              size="lg"
              class="shadow-lg"
              @click="handleDownloadPdf"
            />
          </div>
        </div>
      </UCard>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <!-- Informations de la facture et client -->
          <UCard>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-lg font-semibold mb-4">
                  Informations de la facture
                </h3>
                <dl class="space-y-2">
                  <div>
                    <dt class="text-sm text-gray-500">Numéro de facture</dt>
                    <dd class="text-lg font-semibold">
                      {{ invoice.invoice_number }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Date d'émission</dt>
                    <dd>{{ formatDate(invoice.issued_date) }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Date d'échéance</dt>
                    <dd>{{ formatDate(invoice.due_date) }}</dd>
                  </div>
                </dl>
              </div>
              <div>
                <h3 class="text-lg font-semibold mb-4">Informations client</h3>
                <dl class="space-y-2">
                  <div>
                    <dt class="text-sm text-gray-500">Nom</dt>
                    <dd class="font-medium">
                      {{ invoice.reservation?.user?.name ?? "N/A" }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Email</dt>
                    <dd>{{ invoice.reservation?.user?.email ?? "N/A" }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm text-gray-500">Chambre</dt>
                    <dd>
                      {{
                        invoice.reservation?.chambre?.numero_chambre
                          ? `Chambre n°${invoice.reservation.chambre.numero_chambre} (${invoice.reservation.chambre.type ?? ""})`
                          : "N/A"
                      }}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          </UCard>

          <!-- Détails des prestations -->
          <UCard>
            <template #header>
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold">Détails des prestations</h3>
                <div class="flex gap-2">
                  <UButton
                    label="Visualiser"
                    icon="i-lucide-eye"
                    color="neutral"
                    variant="outline"
                    size="sm"
                    @click="handleViewPdf"
                  />
                  <UButton
                    label="Télécharger PDF"
                    icon="i-heroicons-arrow-down-tray"
                    color="primary"
                    variant="outline"
                    size="sm"
                    @click="handleDownloadPdf"
                  />
                </div>
              </div>
            </template>
            <div class="overflow-x-auto">
              <UTable
                :columns="tableColumns"
                :data="invoice.items ?? []"
                class="min-w-full"
              >
                <template #description-data="{ row }">
                  <span class="font-medium">{{ row.original.description }}</span>
                </template>
                <template #quantity-data="{ row }">
                  <span class="text-center block">{{
                    row.original.quantity
                  }}</span>
                </template>
                <template #unit_price-data="{ row }">
                  <span class="text-right block">{{
                    formatPrice(row.original.unit_price)
                  }}</span>
                </template>
                <template #line_total-data="{ row }">
                  <span class="font-semibold text-right block">{{
                    formatPrice(row.original.line_total)
                  }}</span>
                </template>
              </UTable>
            </div>
          </UCard>
        </div>

        <!-- Totaux -->
        <div class="lg:col-span-1 space-y-6">
          <UCard>
            <div class="space-y-4">
              <h3 class="text-lg font-semibold mb-4">Résumé de la facture</h3>
              <div class="space-y-3">
                <div
                  class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700"
                >
                  <span class="text-gray-600 dark:text-gray-400"
                    >Montant total</span
                  >
                  <span
                    class="text-xl font-bold text-gray-900 dark:text-white"
                    >{{ formatPrice(invoice.total_amount) }}</span
                  >
                </div>
                <div
                  v-if="invoice.amount_paid"
                  class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700"
                >
                  <span class="text-gray-600 dark:text-gray-400"
                    >Montant payé</span
                  >
                  <span
                    class="text-lg font-semibold text-success-600 dark:text-success-400"
                    >{{ formatPrice(invoice.amount_paid) }}</span
                  >
                </div>
                <div
                  v-if="invoice.amount_paid"
                  class="flex justify-between items-center py-3 bg-gray-50 dark:bg-gray-800 rounded-lg px-4"
                >
                  <span class="font-semibold text-gray-900 dark:text-white"
                    >Reste à payer</span
                  >
                  <span
                    class="text-xl font-bold text-primary-600 dark:text-primary-400"
                  >
                    {{
                      formatPrice(invoice.total_amount - invoice.amount_paid)
                    }}
                  </span>
                </div>
              </div>
              <!-- Bouton de paiement -->
              <div v-if="invoice.status === 'unpaid'" class="pt-4">
                <UButton
                  label="Payer la facture"
                  icon="i-heroicons-credit-card"
                  color="success"
                  size="lg"
                  block
                  :loading="isPaying"
                  @click="handlePayInvoice"
                />
              </div>
              <div v-else-if="invoice.status === 'paid'" class="pt-4">
                <UAlert
                  color="success"
                  variant="subtle"
                  icon="i-heroicons-check-circle"
                  title="Facture payée"
                  description="Cette facture a été payée avec succès"
                />
              </div>
            </div>
          </UCard>
        </div>
      </div>
    </div>

    <div v-else class="p-8 text-center text-gray-500">
      <p class="text-lg font-semibold">Facture introuvable.</p>
      <UButton class="mt-4" color="primary" @click="loadInvoice"
        >Réessayer</UButton
      >
      <UButton
        class="mt-4 ml-2"
        color="neutral"
        variant="ghost"
        @click="router.push('/client/invoices')"
        >Retour à la liste</UButton
      >
    </div>
  </UDashboardPanel>
</template>

