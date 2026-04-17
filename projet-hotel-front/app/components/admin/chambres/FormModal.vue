<script setup lang="ts">
import { ref, reactive, computed, watch, onBeforeUnmount } from "vue";
import { useChambreStore, type Chambre } from "~/stores/chambre";
import * as z from "zod";

import {
  DialogRoot,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "reka-ui";

const props = defineProps<{ modelValue: boolean; chambre?: Chambre | null }>();
const emit = defineEmits(["update:modelValue", "success"]);

const chambreStore = useChambreStore();
const toast = useToast();

const previewUrl = ref<string | null>(null);
const fileName = ref<string | null>(null);
let currentObjectUrl: string | null = null;

const localOpen = ref(!!props.modelValue);
watch(
  () => props.modelValue,
  (v) => (localOpen.value = !!v)
);
watch(localOpen, (v) => emit("update:modelValue", v));

const isEditMode = computed(() => !!props.chambre);
const title = computed(() =>
  isEditMode.value ? "Modifier la Chambre" : "Nouvelle Chambre"
);

const schema = z.object({
  numero_chambre: z.string().min(1, "Requis"),
  type: z.string().min(1, "Requis"),
  prix_nuite: z.number().gte(0, "Doit être positif ou nul"),
  disponibilite: z.boolean(),
  description: z.string().optional(),
});

const state = reactive({
  numero_chambre: "",
  type: "",
  prix_nuite: 0,
  disponibilite: true,
  description: "",
  photo: undefined as File | undefined,
});

watch(
  () => props.chambre,
  (newChambre) => {
    if (newChambre) {
      state.numero_chambre = newChambre.numero_chambre ?? "";
      state.type = newChambre.type ?? "";
      state.prix_nuite = Number(newChambre.prix_nuite ?? 0);
      state.disponibilite = !!newChambre.disponibilite;
      state.description = newChambre.description ?? "";
      state.photo = undefined;
    } else {
      resetForm();
    }
  },
  { immediate: true }
);

function resetForm() {
  state.numero_chambre = "";
  state.type = "";
  state.prix_nuite = 0;
  state.disponibilite = true;
  state.description = "";
  state.photo = undefined;
  if (currentObjectUrl) {
    URL.revokeObjectURL(currentObjectUrl);
    currentObjectUrl = null;
  }
  previewUrl.value = null;
  fileName.value = null;
}

const formKey = ref(0);
watch(localOpen, (v) => {
  if (v) formKey.value++;
});

const isLoading = ref(false);
const descriptionId = `dialog-desc-${Math.random().toString(36).slice(2, 9)}`;

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];

  if (file) {
    state.photo = file;

    if (currentObjectUrl) {
      URL.revokeObjectURL(currentObjectUrl);
      currentObjectUrl = null;
    }

    currentObjectUrl = URL.createObjectURL(file);
    previewUrl.value = currentObjectUrl;
    fileName.value = file.name;
  } else {
    state.photo = undefined;
    if (currentObjectUrl) {
      URL.revokeObjectURL(currentObjectUrl);
      currentObjectUrl = null;
    }
    previewUrl.value = null;
    fileName.value = null;
  }
}

async function handleSubmit() {
  const payload = {
    numero_chambre: String(state.numero_chambre),
    type: String(state.type),
    prix_nuite: Number(state.prix_nuite),
    disponibilite: Boolean(state.disponibilite),
    description: state.description ? String(state.description) : undefined,
  };
  const result = schema.safeParse(payload);
  if (!result.success) {
    const firstError =
      Object.values(result.error.flatten().fieldErrors)[0]?.[0] ||
      "Données invalides";
    toast.add({
      title: "Erreur de validation",
      description: firstError,
      color: "error",
    });
    return;
  }

  isLoading.value = true;
  const formData = new FormData();
  formData.append("numero_chambre", payload.numero_chambre);
  formData.append("type", payload.type);
  formData.append("prix_nuite", String(payload.prix_nuite));
  formData.append("disponibilite", payload.disponibilite ? "1" : "0");
  if (payload.description) formData.append("description", payload.description);
  if (state.photo) formData.append("photo", state.photo);

  try {
    if (isEditMode.value && props.chambre) {
      await chambreStore.update(props.chambre.id, formData);
      toast.add({ title: "Chambre modifiée", color: "success" });
    } else {
      await chambreStore.create(formData);
      toast.add({ title: "Chambre créée", color: "success" });
    }
    emit("success");
    localOpen.value = false;
  } catch (err: any) {
    toast.add({
      title: "Erreur",
      description: err?.response?.data?.message ?? "Erreur",
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
}

function onCancel() {
  localOpen.value = false;
}

function removeFile() {
  state.photo = undefined;
  fileName.value = null;
  if (currentObjectUrl) {
    URL.revokeObjectURL(currentObjectUrl);
    currentObjectUrl = null;
  }
  previewUrl.value = null;

  const input = document.getElementById(
    "photo-file"
  ) as HTMLInputElement | null;
  if (input) input.value = "";
}

onBeforeUnmount(() => {
  if (currentObjectUrl) URL.revokeObjectURL(currentObjectUrl);
});
</script>

<template>
  <div v-if="localOpen">
    <DialogRoot v-model:open="localOpen">
      <DialogOverlay class="fixed inset-0 bg-black/50 z-50" />

      <DialogContent
        :aria-describedby="descriptionId"
        class="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-50 w-full max-w-3xl max-h-[90vh] bg-white dark:bg-gray-800 rounded-lg shadow-xl flex flex-col"
      >
        <!-- Header fixe -->
        <div
          class="p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
        >
          <DialogTitle>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
              {{ title }}
            </h2>
          </DialogTitle>

          <DialogDescription
            :id="descriptionId"
            class="text-sm text-gray-600 dark:text-gray-400 mt-1"
          >
            Remplissez les informations de la chambre.
          </DialogDescription>
        </div>

        <!-- Contenu scrollable -->
        <div class="flex-1 overflow-y-auto p-4">
          <form :key="formKey" @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
              >
                Numéro de Chambre
              </label>
              <input
                v-model="state.numero_chambre"
                class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
              />
            </div>

            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
              >
                Type
              </label>
              <select
                v-model="state.type"
                class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
              >
                <option value="">Sélectionner</option>
                <option>Simple</option>
                <option>Double</option>
                <option>Suite</option>
              </select>
            </div>

            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
              >
                Prix par Nuit
              </label>
              <input
                v-model.number="state.prix_nuite"
                type="number"
                class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
              />
            </div>

            <div>
              <label class="inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  v-model="state.disponibilite"
                  class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-200"
                  >Disponible</span
                >
              </label>
            </div>

            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
              >
                Description
              </label>
              <textarea
                v-model="state.description"
                rows="3"
                class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white resize-none"
              ></textarea>
            </div>

            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
              >
                Photo
              </label>

              <div class="flex items-center gap-4">
                <label
                  for="photo-file"
                  class="inline-flex items-center gap-2 px-3 py-2 border rounded-md cursor-pointer border-gray-300 bg-white text-sm shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600"
                >
                  <svg
                    class="w-4 h-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                  >
                    <path
                      d="M12 5v14"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                    <path
                      d="M5 12h14"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                  <span>Choisir une image</span>
                </label>

                <input
                  id="photo-file"
                  type="file"
                  accept="image/*"
                  class="sr-only"
                  @change="onFileChange"
                />

                <div
                  v-if="fileName"
                  class="text-sm text-gray-600 dark:text-gray-300"
                >
                  {{ fileName }}
                </div>
              </div>

              <div v-if="previewUrl" class="mt-3 flex items-center gap-3">
                <img
                  :src="previewUrl"
                  alt="Aperçu image"
                  class="w-28 h-20 object-cover rounded-md border border-gray-200 dark:border-gray-600 shadow-sm"
                />
                <div class="text-sm text-gray-700 dark:text-gray-300">
                  <div class="font-medium">{{ fileName }}</div>
                  <button
                    type="button"
                    class="mt-2 inline-flex items-center px-2 py-1 text-xs border rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 dark:border-gray-600"
                    @click="removeFile"
                  >
                    Supprimer
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>

        <!-- Footer fixe avec les boutons -->
        <div
          class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2 flex-shrink-0 bg-gray-50 dark:bg-gray-900"
        >
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
            @click="onCancel"
          >
            Annuler
          </button>
          <button
            type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isLoading"
            @click="handleSubmit"
          >
            {{ isLoading ? "Enregistrement..." : "Enregistrer" }}
          </button>
        </div>
      </DialogContent>
    </DialogRoot>
  </div>
</template>
