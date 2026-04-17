<script setup lang="ts">
import { onMounted, computed, ref, h } from "vue";
import { useInvoiceStore, type Invoice } from "~/stores/invoiceStore";
import { useAuthStore } from "~/stores/auth";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import type { ColumnDef } from "@tanstack/vue-table";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
  permission: "invoice-list",
});

const authStore = useAuthStore();
const invoiceStore = useInvoiceStore();
const { invoices, isLoading } = storeToRefs(invoiceStore);
const router = useRouter();

onMounted(() => {
  invoiceStore.fetchAll();
  // Debug
  setTimeout(() => {
    console.log("🔍 Invoices loaded:", rows.value);
    console.log("🔍 Columns:", columns.value);
  }, 1000);
});

/**
 * Normalise la source des factures : accepte un tableau direct
 * ou un objet { data: [...] } — et renvoie toujours un tableau plat.
 */
const rows = computed(() => {
  const v = invoices.value as any;
  if (!v) return [];
  if (Array.isArray(v)) return v;
  if (v.data && Array.isArray(v.data)) return v.data;
  return [];
});

/**
 * Colonnes pour TanStack / UTable.
 * IMPORTANT : fournir `id` + `accessorKey` (ou au moins `id`) pour éviter l'erreur.
 */
const columns = ref<ColumnDef<Invoice>[]>([
  { id: "invoice_number", accessorKey: "invoice_number", header: "N° Facture" },
  { id: "client", accessorKey: "client", header: "Client" },
  { id: "total_amount", accessorKey: "total_amount", header: "Montant Total" },
  { id: "status", accessorKey: "status", header: "Statut" },
  { id: "issued_date", accessorKey: "issued_date", header: "Date d'émission" },
  {
    id: "actions",
    header: "Actions",
    cell: ({ row }) =>
      h(
        "div",
        { class: "flex items-center justify-end gap-2" },
        [
          h(
            "button",
            {
              class: "px-2 py-1 rounded text-sm text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20",
              onClick: () => handleViewPdf(row.original),
              title: "Visualiser PDF",
            },
            "👁️"
          ),
          h(
            "button",
            {
              class: "px-2 py-1 rounded text-sm text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20",
              onClick: () => handleDownloadPdf(row.original),
              title: "Télécharger PDF",
            },
            "⬇️"
          ),
          h(
            "button",
            {
              class: "px-2 py-1 rounded text-sm text-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-900/20",
              onClick: () => router.push(`/admin/invoices/${row.original.id}`),
              title: "Voir les détails",
            },
            "🔍"
          ),
          row.original.status === 'unpaid' ? h(
            "button",
            {
              class: "px-3 py-1 rounded text-sm text-success-600 border border-success-300 hover:bg-success-50 dark:hover:bg-success-900/20",
              onClick: () => handleMarkAsPaid(row.original),
              title: "Marquer comme payée",
            },
            "✓ Payée"
          ) : null,
        ].filter(Boolean)
      ),
  },
]);

// Utils
const toast = useToast();
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

async function handleMarkAsPaid(inv: Invoice) {
  // Note: Les permissions sont vérifiées côté serveur
  // On laisse le backend gérer l'autorisation
  try {
    await invoiceStore.markAsPaid(inv.id);
    toast.add({ title: "Facture marquée payée", color: "success" });
  } catch (err: any) {
    toast.add({
      title: "Erreur",
      description: err?.response?.data?.message ?? "Erreur inconnue",
      color: "error",
    });
  }
}

async function fetchPdfBlob(inv: Invoice) {
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
    const response = await api.get(`/invoices/${inv.id}/pdf`, {
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

async function handleViewPdf(inv: Invoice) {
  try {
    const { blob, loadingToast } = await fetchPdfBlob(inv);
    
    // Fermer le toast de chargement
    if (loadingToast) {
      toast.remove(loadingToast.id);
    }

    // Créer une URL pour le blob et ouvrir dans un modal
    const urlObject = window.URL.createObjectURL(blob);
    pdfViewerUrl.value = urlObject;
    showPdfViewer.value = true;

    toast.add({
      title: "PDF chargé",
      description: "La facture est maintenant affichée",
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

function closePdfViewer() {
  if (pdfViewerUrl.value) {
    window.URL.revokeObjectURL(pdfViewerUrl.value);
    pdfViewerUrl.value = null;
  }
  showPdfViewer.value = false;
}

async function handleDownloadPdf(inv: Invoice) {
  try {
    const { blob, loadingToast } = await fetchPdfBlob(inv);
    
    // Fermer le toast de chargement
    if (loadingToast) {
      toast.remove(loadingToast.id);
    }

    // Créer et déclencher le téléchargement
    const urlObject = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = urlObject;
    a.download = `facture-${inv.invoice_number}.pdf`;
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
      timeout: 5000,
    });
  }
}
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Gestion de la Facturation" />

    <div class="p-4">
      <!-- Message si pas de factures -->
      <div
        v-if="!isLoading && rows.length === 0"
        class="p-8 text-center text-gray-500"
      >
        Aucune facture pour le moment.
      </div>

      <!-- Passe un tableau plat dans la prop "data" -->
      <UTable v-else :columns="columns" :data="rows" :loading="isLoading">
        <!-- Les slots reçoivent `row` ; accéder aux données via row.original -->
        <template #client-data="{ row }">
          {{
            (row.original as unknown as Invoice).reservation?.user?.name ??
            "N/A"
          }}
        </template>

        <template #total_amount-data="{ row }">
          <span class="font-medium">{{
            formatPrice((row.original as unknown as Invoice).total_amount)
          }}</span>
        </template>

        <template #status-data="{ row }">
          <UBadge
            :color="
              String((row.original as unknown as Invoice).status ?? '').toLowerCase() === 'paid'
                ? 'success'
                : String((row.original as unknown as Invoice).status ?? '').toLowerCase() === 'void'
                ? 'error'
                : 'neutral'
            "
            variant="subtle"
          >
            {{ (row.original as unknown as Invoice).status ?? "-" }}
          </UBadge>
        </template>

        <template #issued_date-data="{ row }">
          {{ formatDate((row.original as unknown as Invoice).issued_date) }}
        </template>
      </UTable>
    </div>

    <!-- PDF Viewer Modal -->
  
  </UDashboardPanel>
</template>
