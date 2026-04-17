<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useAuthStore } from "~/stores/auth";
import * as z from "zod";

definePageMeta({
  layout: "default",
  middleware: ["01-auth"] as unknown as any,
  permission: "user-list", // Seuls les Super-Admin peuvent accéder
});

const authStore = useAuthStore();
const toast = useToast();

const hotelConfigSchema = z.object({
  name: z.string().min(1, "Le nom de l'hôtel est requis"),
  address: z.string().optional(),
  phone: z.string().optional(),
  email: z.string().email("Email invalide").optional().or(z.literal("")),
  logo: z.string().optional(),
  description: z.string().optional(),
  paid_stamp: z.any().optional(),
});

type HotelConfigSchema = z.output<typeof hotelConfigSchema>;

const config = ref<Partial<HotelConfigSchema>>({
  name: "",
  address: "",
  phone: "",
  email: "",
  logo: "",
  description: "",
  paid_stamp: null,
});

const isLoading = ref(false);
const isSaving = ref(false);
const paidStampFile = ref<File | null>(null);
const paidStampPreview = ref<string | null>(null);

const runtimeConfig = useRuntimeConfig();
const configBaseUrl = runtimeConfig.public.apiBase?.replace('/api', '') || 'http://localhost:8000';

const loadConfig = async () => {
  isLoading.value = true;
  try {
    const nuxt = useNuxtApp();
    const api = nuxt.$api as any;

    const response = await api.get("/hotel-config");
    config.value = response.data;
    
    // Charger l'aperçu du tampon "PAYÉ" s'il existe
    if (response.data.paid_stamp) {
      paidStampPreview.value = `${configBaseUrl}/storage/${response.data.paid_stamp}`;
    }
  } catch (error: any) {
    console.error("Error loading hotel config:", error);
    toast.add({
      title: "Erreur",
      description: "Impossible de charger la configuration",
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
};

const handlePaidStampChange = (event: Event) => {
  const input = event.target as HTMLInputElement;
  if (input.files && input.files[0]) {
    paidStampFile.value = input.files[0];
    
    // Créer un aperçu
    const reader = new FileReader();
    reader.onload = (e) => {
      paidStampPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(paidStampFile.value);
  }
};

const saveConfig = async (event?: any) => {
  // Empêcher le comportement par défaut du formulaire
  if (event) {
    event.preventDefault?.();
  }

  // Valider que le nom est présent
  if (!config.value.name || !config.value.name.trim()) {
    toast.add({
      title: "Erreur de validation",
      description: "Le nom de l'hôtel est requis",
      color: "error",
    });
    return;
  }

  console.log("Saving config...", config.value);
  isSaving.value = true;
  
  try {
    const nuxt = useNuxtApp();
    const api = nuxt.$api as any;

    // Utiliser FormData seulement si un fichier est présent, sinon utiliser JSON
    const hasFile = paidStampFile.value !== null;
    
    if (hasFile) {
      // Créer FormData si un fichier est présent
      const formData = new FormData();
      
      // Toujours envoyer le nom (requis) - s'assurer qu'il n'est pas vide
      const hotelName = String(config.value.name || '').trim();
      if (!hotelName) {
        toast.add({
          title: "Erreur de validation",
          description: "Le nom de l'hôtel est requis",
          color: "error",
        });
        isSaving.value = false;
        return;
      }
      
      // Ajouter le nom en premier pour s'assurer qu'il est bien présent
      formData.append('name', hotelName);
      console.log("🔵 Appending name to FormData:", hotelName, "Type:", typeof hotelName);
      
      // Envoyer les autres champs seulement s'ils existent et ne sont pas vides
      if (config.value.address?.trim()) {
        formData.append('address', config.value.address.trim());
      }
      if (config.value.phone?.trim()) {
        formData.append('phone', config.value.phone.trim());
      }
      if (config.value.email?.trim()) {
        formData.append('email', config.value.email.trim());
      }
      if (config.value.logo?.trim()) {
        formData.append('logo', config.value.logo.trim());
      }
      if (config.value.description?.trim()) {
        formData.append('description', config.value.description.trim());
      }
      
      // Ajouter le fichier tampon si présent
      if (paidStampFile.value) {
        formData.append('paid_stamp', paidStampFile.value);
        console.log("File info:", {
          name: paidStampFile.value.name,
          size: paidStampFile.value.size,
          type: paidStampFile.value.type
        });
      }

      // Debug: Vérifier le contenu du FormData AVANT l'envoi
      console.log("🔵 FormData contents BEFORE sending:");
      console.log("FormData keys:", Array.from(formData.keys()));
      for (const [key, value] of formData.entries()) {
        if (value instanceof File) {
          console.log(`${key}: File(${value.name}, ${value.size} bytes, ${value.type})`);
        } else {
          console.log(`${key}: "${value}" (type: ${typeof value}, length: ${String(value).length})`);
        }
      }

      console.log("🔵 Sending FormData...");
      
      // Vérifier une dernière fois que le nom est bien dans le FormData
      const nameValue = formData.get('name');
      console.log("🔵 Name value in FormData before send:", nameValue);
      
      if (!nameValue || String(nameValue).trim() === '') {
        toast.add({
          title: "Erreur",
          description: "Le nom de l'hôtel ne peut pas être vide",
          color: "error",
        });
        isSaving.value = false;
        return;
      }
      
      // Essayer d'abord avec POST et _method=PUT
      formData.append('_method', 'PUT');
      
      // Vérifier à nouveau après avoir ajouté _method
      console.log("🔵 FormData final keys:", Array.from(formData.keys()));
      console.log("🔵 Final name value:", formData.get('name'));
      
      let response;
      try {
        // Essayer avec POST et _method=PUT
        response = await api.post("/hotel-config", formData);
      } catch (error: any) {
        // Si ça échoue, essayer directement avec PUT
        console.warn("POST avec _method=PUT a échoué, tentative avec PUT direct:", error);
        // Retirer _method pour l'essai suivant
        formData.delete('_method');
        response = await api.put("/hotel-config", formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
      }

      // Mettre à jour l'aperçu si un nouveau fichier a été uploadé
      if (response.data.config?.paid_stamp) {
        paidStampPreview.value = `${configBaseUrl}/storage/${response.data.config.paid_stamp}`;
      }
      
      paidStampFile.value = null; // Réinitialiser le fichier

      toast.add({
        title: "Succès",
        description: "Configuration mise à jour avec succès",
        color: "success",
      });
    } else {
      // Utiliser JSON si pas de fichier
      const payload = {
        name: config.value.name.trim(),
        address: config.value.address?.trim() || null,
        phone: config.value.phone?.trim() || null,
        email: config.value.email?.trim() || null,
        logo: config.value.logo?.trim() || null,
        description: config.value.description?.trim() || null,
      };
      
      console.log("Sending JSON payload...", payload);
      const response = await api.put("/hotel-config", payload);

      toast.add({
        title: "Succès",
        description: "Configuration mise à jour avec succès",
        color: "success",
      });
    }
  } catch (error: any) {
    console.error("Error saving hotel config:", error);
    console.error("Error details:", error.response?.data);
    console.error("Error errors object:", error.response?.data?.errors);
    
    // Afficher les erreurs dans la console de manière plus détaillée
    if (error.response?.data?.errors) {
      console.error("=== DÉTAILS DES ERREURS DE VALIDATION ===");
      for (const [field, messages] of Object.entries(error.response.data.errors)) {
        console.error(`Champ "${field}":`, messages);
      }
      console.error("==========================================");
    }
    
    // Afficher toutes les erreurs de validation
    let errorMessage = "Impossible de sauvegarder la configuration";
    
    if (error.response?.data?.errors) {
      // Afficher toutes les erreurs de validation
      const errors = error.response.data.errors;
      const errorMessages = [];
      
      // Itérer sur tous les champs avec erreurs
      for (const [field, messages] of Object.entries(errors)) {
        if (Array.isArray(messages)) {
          errorMessages.push(...messages.map((msg: string) => `${field}: ${msg}`));
        } else if (typeof messages === 'string') {
          errorMessages.push(`${field}: ${messages}`);
        }
      }
      
      if (errorMessages.length > 0) {
        errorMessage = errorMessages.join("\n");
      } else if (error.response?.data?.message) {
        errorMessage = error.response.data.message;
      }
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    }
    
    // Afficher chaque erreur séparément pour plus de clarté
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      for (const [field, messages] of Object.entries(errors)) {
        if (Array.isArray(messages)) {
          messages.forEach((msg: string) => {
            toast.add({
              title: `Erreur: ${field}`,
              description: msg,
              color: "error",
              timeout: 7000,
            });
          });
        }
      }
    } else {
      toast.add({
        title: "Erreur de validation",
        description: errorMessage,
        color: "error",
        timeout: 5000,
      });
    }
  } finally {
    isSaving.value = false;
  }
};

onMounted(loadConfig);
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Configuration de l'Hôtel">
      <template #leading>
        <UDashboardSidebarCollapse />
      </template>
    </UDashboardNavbar>

    <div class="p-6">
      <div v-if="isLoading" class="flex justify-center py-12">
        <div
          class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"
        ></div>
      </div>

      <UForm
        v-else
        :schema="hotelConfigSchema"
        :state="config"
        @submit.prevent="saveConfig"
        class="space-y-6 max-w-3xl"
      >
        <UPageCard
          title="Informations de l'Hôtel"
          description="Ces informations apparaîtront sur les factures et documents officiels."
          variant="naked"
          orientation="horizontal"
          class="mb-6"
        >
          <UButton
            label="Enregistrer"
            color="primary"
            :loading="isSaving"
            :disabled="isSaving"
            class="w-fit lg:ms-auto"
            @click="saveConfig"
          />
        </UPageCard>

        <UPageCard variant="subtle" class="space-y-6">
          <UFormField
            name="name"
            label="Nom de l'Hôtel"
            description="Le nom qui apparaîtra sur toutes les factures"
            required
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UInput
              v-model="config.name"
              placeholder="Ex: Hôtel Royal"
              autocomplete="off"
              class="max-w-md"
            />
          </UFormField>

          <USeparator />

          <UFormField
            name="address"
            label="Adresse"
            description="Adresse complète de l'hôtel"
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UTextarea
              v-model="config.address"
              placeholder="Ex: 123 Avenue des Champs, 75008 Paris"
              :rows="3"
              class="max-w-md"
            />
          </UFormField>

          <USeparator />

          <UFormField
            name="phone"
            label="Téléphone"
            description="Numéro de téléphone de contact"
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UInput
              v-model="config.phone"
              placeholder="Ex: +33 1 23 45 67 89"
              autocomplete="tel"
              class="max-w-md"
            />
          </UFormField>

          <USeparator />

          <UFormField
            name="email"
            label="Email"
            description="Adresse email de contact"
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UInput
              v-model="config.email"
              type="email"
              placeholder="Ex: contact@hotel.com"
              autocomplete="email"
              class="max-w-md"
            />
          </UFormField>

          <USeparator />

          <UFormField
            name="description"
            label="Description"
            description="Description de l'hôtel (optionnel)"
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UTextarea
              v-model="config.description"
              placeholder="Description de votre hôtel..."
              :rows="4"
              class="max-w-md"
            />
          </UFormField>

          <USeparator />

          <UFormField
            name="paid_stamp"
            label="Tampon 'PAYÉ'"
            description="Image du tampon qui s'affichera en bas des factures payées (PNG, JPG, max 2MB)"
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <div class="flex flex-col gap-4 max-w-md">
              <div v-if="paidStampPreview" class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                <img
                  :src="paidStampPreview"
                  alt="Aperçu du tampon"
                  class="max-w-full h-auto max-h-48 mx-auto object-contain"
                />
                <p class="text-xs text-gray-500 dark:text-gray-400 text-center mt-2">
                  Aperçu du tampon actuel
                </p>
              </div>
              <input
                type="file"
                accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
                @change="handlePaidStampChange"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-900/20 dark:file:text-primary-400"
              />
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Le tampon apparaîtra automatiquement en bas à droite des factures marquées comme payées.
              </p>
            </div>
          </UFormField>
        </UPageCard>

        <UPageCard
          variant="subtle"
          class="bg-primary-50 dark:bg-primary-900/20 border-primary-200 dark:border-primary-800"
        >
          <div class="flex items-start gap-4">
            <UIcon
              name="i-lucide-info"
              class="w-5 h-5 text-primary-600 dark:text-primary-400 flex-shrink-0 mt-0.5"
            />
            <div>
              <h3 class="font-semibold text-primary-900 dark:text-primary-100 mb-1">
                Aperçu de la facture
              </h3>
              <p class="text-sm text-primary-700 dark:text-primary-300">
                Les modifications du nom de l'hôtel et des informations de contact
                seront visibles sur toutes les nouvelles factures générées. Les
                factures déjà générées conservent les informations de l'époque.
              </p>
            </div>
          </div>
        </UPageCard>
      </UForm>
    </div>
  </UDashboardPanel>
</template>

