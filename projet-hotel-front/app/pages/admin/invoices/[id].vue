<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useInvoiceStore, type Invoice } from "~/stores/invoiceStore";
import { useAuthStore } from "~/stores/auth";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
  permission: "invoice-list",
});

const route = useRoute();
const router = useRouter();
const invoiceStore = useInvoiceStore();
const authStore = useAuthStore();
const toast = useToast();

const invoice = ref<Invoice | null>(null);
const isLoading = ref(true);
const errorMessage = ref<string | null>(null);
const isUpdatingInvoice = ref(false);

// Debug: vérifier les permissions
onMounted(() => {
  console.log("🔍 User data:", authStore.user);
  console.log("🔍 Roles:", authStore.user?.roles);
  console.log("🔍 Permissions:", authStore.user?.permissions_list);
  console.log("🔍 hasRole Super-Admin:", authStore.hasRole("Super-Admin"));
  console.log("🔍 hasRole Receptionist:", authStore.hasRole("Receptionist"));
  console.log("🔍 can invoice-update-status:", authStore.can("invoice-update-status"));
});

const invoiceId = computed(() => {
  const id = route.params.id;
  const numId = typeof id === 'string' ? Number(id) : Number(id);
  return isNaN(numId) ? null : numId;
});

// Fonction pour charger la facture
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
    
    console.log("🔵 Loading invoice ID:", invoiceId.value);
    const response = await api.get(`/invoices/${invoiceId.value}`);
    console.log("✅ Response received:", response.data);
    
    invoice.value = response.data;
    
    if (!invoice.value || !invoice.value.id) {
      throw new Error("Données de facture invalides");
    }
    
    console.log("✅ Invoice loaded:", invoice.value.invoice_number);
    
  } catch (error: any) {
    console.error("❌ Error loading invoice:", error);
    errorMessage.value = error.response?.data?.message || error.message || "Impossible de charger la facture.";
    
    toast.add({
      title: "Erreur",
      description: errorMessage.value || "Erreur",
      color: "error",
    });
  } finally {
    isLoading.value = false;
    console.log("🏁 Loading finished, invoice exists:", !!invoice.value);
  }
};

// Charger au montage
loadInvoice();

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
  } as any);

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

const pdfViewerUrl = ref<string | null>(null);
const showPdfViewer = ref(false);

async function handleViewPdf() {
  try {
    const { blob, loadingToast } = await fetchPdfBlob();
    
    // Fermer le toast de chargement
    if (loadingToast) {
      toast.remove(loadingToast.id);
    }

    // Créer une URL pour le blob
    const urlObject = window.URL.createObjectURL(blob);
    pdfViewerUrl.value = urlObject;
    showPdfViewer.value = true;

    toast.add({
      title: "PDF chargé",
      description: "La facture est maintenant affichée ci-dessous",
      color: "success",
    } as any);
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
    } as any);
  }
}

function closePdfViewer() {
  if (pdfViewerUrl.value) {
    window.URL.revokeObjectURL(pdfViewerUrl.value);
    pdfViewerUrl.value = null;
  }
  showPdfViewer.value = false;
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
    a.download = `facture-${invoice.value?.invoice_number || 'facture'}.pdf`;
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
    }
    
    toast.add({
      title: "Erreur",
      description: errorMessage,
      color: "error",
    } as any);
  }
}

async function handleUpdateInvoice() {
  if (!invoice.value) {
    toast.add({
      title: "Erreur",
      description: "Aucune facture sélectionnée",
      color: "error",
    });
    return;
  }

  isUpdatingInvoice.value = true;
  try {
    const nuxt = useNuxtApp();
    const api = nuxt.$api as any;

    const loadingToast = toast.add({
      title: "Mise à jour",
      description: "Mise à jour de la facture en cours...",
      color: "primary",
    } as any);

    const response = await api.post(`/invoices/${invoice.value.id}/update-from-orders`);

    toast.remove(loadingToast.id);

    // Recharger la facture
    await loadInvoice();

    toast.add({
      title: "Succès",
      description: response.data.message || "La facture a été mise à jour avec succès",
      color: "success",
    });
  } catch (err: any) {
    console.error("Error updating invoice:", err);
    toast.add({
      title: "Erreur",
      description: err?.response?.data?.message || "Impossible de mettre à jour la facture",
      color: "error",
    });
  } finally {
    isUpdatingInvoice.value = false;
  }
}

async function handleMarkAsPaid() {
  if (!invoice.value) {
    toast.add({
      title: "Erreur",
      description: "Aucune facture sélectionnée",
      color: "error",
    });
    return;
  }

  // Note: Les permissions sont vérifiées côté serveur
  // On laisse le backend gérer l'autorisation
  
  try {
    await invoiceStore.markAsPaid(invoice.value.id);
    invoice.value.status = "paid";
    invoice.value.amount_paid = invoice.value.total_amount;
    
    // Recharger la facture pour avoir les données à jour
    await loadInvoice();
    
    toast.add({ 
      title: "Succès", 
      description: "La facture a été marquée comme payée avec succès",
      color: "success" 
    });
  } catch (err: any) {
    console.error("Error marking invoice as paid:", err);
    toast.add({
      title: "Erreur",
      description: err?.response?.data?.message ?? "Impossible de marquer la facture comme payée",
      color: "error",
    });
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
          @click="router.push('/admin/invoices')"
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
        <UButton
          :label="invoice?.status === 'paid' ? 'Déjà payée' : 'Marquer comme payée'"
          :color="invoice?.status === 'paid' ? 'success' : 'success'"
          :variant="invoice?.status === 'paid' ? 'soft' : 'solid'"
          :disabled="invoice?.status === 'paid'"
          icon="i-heroicons-check-circle"
          @click="handleMarkAsPaid"
        />
      </template>
    </UDashboardNavbar>

    <!-- Loading state -->
    <div v-if="isLoading" class="p-8 text-center">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
      <p class="mt-2 font-medium">Chargement de la facture...</p>
      <p class="mt-2 text-sm text-gray-500">ID: {{ invoiceId }}</p>
    </div>

    <!-- Error state -->
    <div v-else-if="errorMessage" class="p-8 text-center">
      <div class="max-w-md mx-auto">
        <div class="text-red-500 text-5xl mb-4">⚠️</div>
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Erreur</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ errorMessage }}</p>
        <UButton
          label="Retour à la liste"
          icon="i-heroicons-arrow-left"
          color="primary"
          @click="router.push('/admin/invoices')"
        />
      </div>
    </div>

    <!-- Invoice content -->
    <div v-else-if="invoice" class="p-6 space-y-6">
      <!-- En-tête de la facture avec bouton de téléchargement proéminent -->
      <UCard class="bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900/20 dark:to-primary-800/20 border-2 border-primary-200 dark:border-primary-800">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div class="flex-1">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
              Facture {{ invoice.invoice_number }}
            </h2>
            <div class="flex items-center gap-4 mt-3">
              <UBadge
                :color="
                  invoice.status === 'paid'
                    ? 'success'
                    : invoice.status === 'void'
                    ? 'error'
                    : 'warning'
                "
                variant="solid"
                size="lg"
              >
                {{ invoice.status === 'paid' ? 'Payée' : invoice.status === 'void' ? 'Annulée' : 'En attente' }}
              </UBadge>
              <span class="text-sm text-gray-600 dark:text-gray-400">
                Émise le {{ formatDate(invoice.issued_date) }}
              </span>
            </div>
          </div>
          <div class="flex gap-2">
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
            <UButton
              v-if="authStore.hasRole('Super-Admin') || authStore.hasRole('Receptionist') || authStore.can('invoice-update-status')"
              :label="invoice?.status === 'paid' ? 'Déjà payée' : 'Marquer payée'"
              icon="i-heroicons-check-circle"
              :color="invoice?.status === 'paid' ? 'success' : 'success'"
              :variant="invoice?.status === 'paid' ? 'soft' : 'solid'"
              :disabled="invoice?.status === 'paid'"
              size="lg"
              @click="handleMarkAsPaid"
            />
          </div>
        </div>
      </UCard>

      <!-- Informations de la facture et client -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <UCard>
          <template #header>
            <h3 class="text-lg font-semibold">Informations de la facture</h3>
          </template>
          <dl class="space-y-3">
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Numéro de facture</dt>
              <dd class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ invoice.invoice_number }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date d'émission</dt>
              <dd class="text-gray-900 dark:text-white mt-1">{{ formatDate(invoice.issued_date) }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date d'échéance</dt>
              <dd class="text-gray-900 dark:text-white mt-1">{{ formatDate(invoice.due_date) }}</dd>
            </div>
          </dl>
        </UCard>
        
        <UCard>
          <template #header>
            <h3 class="text-lg font-semibold">Informations client</h3>
          </template>
          <dl class="space-y-3">
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nom</dt>
              <dd class="font-medium text-gray-900 dark:text-white mt-1">{{ invoice.reservation?.user?.name ?? "N/A" }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
              <dd class="text-gray-900 dark:text-white mt-1">{{ invoice.reservation?.user?.email ?? "N/A" }}</dd>
            </div>
            <div>
              <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Chambre</dt>
              <dd class="text-gray-900 dark:text-white mt-1">
                {{ invoice.reservation?.chambre?.numero_chambre 
                  ? `Chambre n°${invoice.reservation.chambre.numero_chambre} (${invoice.reservation.chambre.type ?? ""})`
                  : "N/A" }}
              </dd>
            </div>
          </dl>
        </UCard>
      </div>

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
            :columns="[
              { id: 'description', header: 'Description' },
              { id: 'quantity', header: 'Quantité' },
              { id: 'unit_price', header: 'Prix unitaire' },
              { id: 'line_total', header: 'Total' },
            ]"
            :data="invoice.items ?? []"
            class="min-w-full"
          >
            <template #description-data="{ row }">
              <span class="font-medium">{{ row.original.description }}</span>
            </template>
            <template #quantity-data="{ row }">
              <span class="text-center block">{{ row.original.quantity }}</span>
            </template>
            <template #unit_price-data="{ row }">
              <span class="text-right block">{{ formatPrice(row.original.unit_price) }}</span>
            </template>
            <template #line_total-data="{ row }">
              <span class="font-semibold text-right block">{{ formatPrice(row.original.line_total) }}</span>
            </template>
          </UTable>
        </div>
      </UCard>

      <!-- Totaux et Actions -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <UCard class="lg:col-span-2">
          <div class="space-y-4">
            <h3 class="text-lg font-semibold mb-4">Résumé de la facture</h3>
            <div class="space-y-3">
              <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-400">Montant total</span>
                <span class="text-xl font-bold text-gray-900 dark:text-white">{{ formatPrice(invoice.total_amount) }}</span>
              </div>
              <div v-if="invoice.amount_paid" class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-gray-600 dark:text-gray-400">Montant payé</span>
                <span class="text-lg font-semibold text-success-600 dark:text-success-400">{{ formatPrice(invoice.amount_paid) }}</span>
              </div>
              <div v-if="invoice.amount_paid" class="flex justify-between items-center py-3 bg-gray-50 dark:bg-gray-800 rounded-lg px-4">
                <span class="font-semibold text-gray-900 dark:text-white">Reste à payer</span>
                <span class="text-xl font-bold text-primary-600 dark:text-primary-400">
                  {{ formatPrice(invoice.total_amount - invoice.amount_paid) }}
                </span>
              </div>
            </div>
          </div>
        </UCard>
        
        <!-- Section Actions - Toujours visible -->
        <UCard>
          <template #header>
            <h3 class="text-lg font-semibold">Actions</h3>
          </template>
          <div class="space-y-3">
            <UButton
              label="Visualiser"
              icon="i-lucide-eye"
              color="primary"
              variant="solid"
              block
              size="lg"
              @click="handleViewPdf"
            />
            <UButton
              label="Télécharger PDF"
              icon="i-heroicons-arrow-down-tray"
              color="primary"
              variant="outline"
              block
              size="lg"
              @click="handleDownloadPdf"
            />
            <UButton
              :label="invoice?.status === 'paid' ? 'Déjà payée' : 'Marquer comme payée'"
              icon="i-heroicons-check-circle"
              color="success"
              :variant="invoice?.status === 'paid' ? 'soft' : 'solid'"
              :disabled="invoice?.status === 'paid'"
              block
              size="lg"
              @click="handleMarkAsPaid"
            />
            <UButton
              v-if="invoice?.status !== 'paid'"
              label="Mettre à jour facture"
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="outline"
              block
              size="lg"
              :loading="isUpdatingInvoice"
              @click="handleUpdateInvoice"
            />
            <UButton
              label="Retour à la liste"
              icon="i-heroicons-arrow-left"
              variant="ghost"
              block
              @click="router.push('/admin/invoices')"
            />
          </div>
        </UCard>
      </div>
    </div>

    <!-- No invoice found -->
    <div v-else class="p-8 text-center">
      <div class="max-w-md mx-auto">
        <div class="text-gray-500 text-5xl mb-4">📄</div>
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Facture introuvable</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
          La facture avec l'ID <strong>{{ invoiceId }}</strong> n'a pas pu être chargée.
        </p>
        <UButton
          label="Retour à la liste"
          icon="i-heroicons-arrow-left"
          color="primary"
          @click="router.push('/admin/invoices')"
        />
      </div>
    </div>

    <!-- PDF Viewer Modal -->
    <UModal v-model="showPdfViewer" class="max-w-6xl">
      <UCard>
        <template #header>
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Visualisation de la facture</h3>
            <UButton
              icon="i-heroicons-x-mark"
              color="neutral"
              variant="ghost"
              @click="closePdfViewer"
            />
          </div>
        </template>
        <div class="w-full h-[80vh]">
          <iframe
            v-if="pdfViewerUrl"
            :src="pdfViewerUrl"
            class="w-full h-full border-0"
            title="Facture PDF"
          ></iframe>
        </div>
        <template #footer>
          <div class="flex justify-end gap-2">
            <UButton
              label="Fermer"
              color="neutral"
              variant="ghost"
              @click="closePdfViewer"
            />
            <UButton
              label="Télécharger"
              icon="i-heroicons-arrow-down-tray"
              color="primary"
              @click="handleDownloadPdf(); closePdfViewer();"
            />
          </div>
        </template>
      </UCard>
    </UModal>
  </UDashboardPanel>
</template>
