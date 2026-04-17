<script setup lang="ts">
import { ref, reactive, computed, watch, onBeforeUnmount } from "vue";
import { useMenuItemStore, type MenuItem } from "~/stores/menuItemStore";
import {
  DialogRoot,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "reka-ui";

const props = defineProps<{ modelValue: boolean; item?: MenuItem | null }>();
const emit = defineEmits(["update:modelValue", "success"]);

const menuItemStore = useMenuItemStore();
const toast = useToast();

// v-model local
const localOpen = ref(!!props.modelValue);
watch(
  () => props.modelValue,
  (v) => (localOpen.value = !!v)
);
watch(localOpen, (v) => emit("update:modelValue", v));

// formKey pour remount propre
const formKey = ref(0);
watch(localOpen, (o) => {
  if (o) formKey.value++;
});

// mode / titre
const isEditMode = computed(() => !!props.item);
const title = computed(() =>
  isEditMode.value ? "Modifier l'Article" : "Nouvel Article"
);

// state formulaire
const state = reactive({
  name: "",
  category: "Plats",
  price: 0,
  available: true,
  description: "",
  photo: undefined as File | undefined, // selected file
});

// catégories (local)
const categories = ["Boissons", "Plats", "Desserts", "Cocktails"];

// erreurs simples
const errors = reactive({ name: "", category: "", price: "" });

// preview variables (même logique que la modale chambres)
const previewUrl = ref<string | null>(null);
const fileName = ref<string | null>(null);
let currentObjectUrl: string | null = null;

function setPhoto(file?: File) {
  state.photo = file ?? undefined;
  if (currentObjectUrl) {
    URL.revokeObjectURL(currentObjectUrl);
    currentObjectUrl = null;
  }
  if (file) {
    currentObjectUrl = URL.createObjectURL(file);
    previewUrl.value = currentObjectUrl;
    fileName.value = file.name;
  } else {
    previewUrl.value = null;
    fileName.value = null;
  }
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  const f = input.files?.[0];
  setPhoto(f);
}

function removeFile() {
  setPhoto(undefined);
  const input = document.getElementById(
    "menuitem-photo"
  ) as HTMLInputElement | null;
  if (input) input.value = "";
}

// revoke URL à la destruction du composant
onBeforeUnmount(() => {
  if (currentObjectUrl) URL.revokeObjectURL(currentObjectUrl);
});

// reset / clear
function resetForm() {
  state.name = "";
  state.category = "Plats";
  state.price = 0;
  state.available = true;
  state.description = "";
  setPhoto(undefined);
  clearErrors();
}

function clearErrors() {
  errors.name = "";
  errors.category = "";
  errors.price = "";
}

// populate si edition
watch(
  () => props.item,
  (it) => {
    if (it) {
      state.name = it.name ?? "";
      state.category = it.category ?? "Plats";
      state.price = Number(it.price ?? 0);
      state.available = !!it.available;
      state.description = it.description ?? "";
      // on ne pré-remplit pas l'image (l'utilisateur la remplace s'il veut)
      setPhoto(undefined);
      clearErrors();
    } else {
      resetForm();
    }
  },
  { immediate: true }
);

// validation simple
function validate(): boolean {
  clearErrors();
  if (!state.name || state.name.trim().length === 0)
    errors.name = "Le nom est requis.";
  if (!state.category) errors.category = "La catégorie est requise.";
  if (isNaN(Number(state.price)) || Number(state.price) < 0)
    errors.price = "Le prix doit être >= 0.";
  return !errors.name && !errors.category && !errors.price;
}

const isLoading = ref(false);

async function handleSubmit() {
  if (!validate()) return;
  isLoading.value = true;

  const formData = new FormData();
  formData.append("name", state.name);
  formData.append("category", state.category);
  formData.append("price", String(state.price));
  formData.append("available", state.available ? "1" : "0");
  if (state.description) formData.append("description", state.description);
  if (state.photo) formData.append("image", state.photo); // backend expects 'image'

  try {
    if (isEditMode.value && props.item) {
      await menuItemStore.update(props.item.id, formData);
      toast.add({ title: "Article mis à jour", color: "success" });
    } else {
      await menuItemStore.create(formData);
      toast.add({ title: "Article créé", color: "success" });
    }
    emit("success");
    localOpen.value = false;
  } catch (err: any) {
    toast.add({
      title: "Erreur",
      description: err?.response?.data?.message ?? "Une erreur est survenue.",
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
}

// a11y description id
const descriptionId = `dialog-desc-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
  <div v-if="localOpen">
    <DialogRoot v-model:open="localOpen">
      <DialogOverlay />

      <DialogContent
        :aria-describedby="descriptionId"
        class="max-w-2xl mx-auto"
      >
        <div class="p-6">
          <DialogTitle>
            <h2 class="text-xl font-semibold">{{ title }}</h2>
          </DialogTitle>

          <DialogDescription
            :id="descriptionId"
            class="text-sm text-gray-600 mt-1"
          >
            Gérez les informations de l'article du menu.
          </DialogDescription>

          <form
            :key="formKey"
            @submit.prevent="handleSubmit"
            class="space-y-4 mt-4"
          >
            <!-- Nom -->
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                >Nom de l'article</label
              >
              <input
                v-model="state.name"
                type="text"
                :class="[
                  'block w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500',
                  errors.name ? 'border-red-500' : 'border-gray-300',
                  'dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white',
                ]"
              />
              <p v-if="errors.name" class="text-xs text-red-600 mt-1">
                {{ errors.name }}
              </p>
            </div>

            <!-- Catégorie / Prix -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label
                  class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                  >Catégorie</label
                >
                <select
                  v-model="state.category"
                  :class="[
                    'block w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500',
                    errors.category ? 'border-red-500' : 'border-gray-300',
                    'dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white',
                  ]"
                >
                  <option v-for="cat in categories" :key="cat" :value="cat">
                    {{ cat }}
                  </option>
                </select>
                <p v-if="errors.category" class="text-xs text-red-600 mt-1">
                  {{ errors.category }}
                </p>
              </div>

              <div>
                <label
                  class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                  >Prix</label
                >
                <input
                  v-model.number="state.price"
                  type="number"
                  :class="[
                    'block w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500',
                    errors.price ? 'border-red-500' : 'border-gray-300',
                    'dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white',
                  ]"
                />
                <p v-if="errors.price" class="text-xs text-red-600 mt-1">
                  {{ errors.price }}
                </p>
              </div>
            </div>

            <!-- Description -->
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                >Description</label
              >
              <textarea
                v-model="state.description"
                rows="3"
                class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white"
              ></textarea>
            </div>

            <!-- Photo + preview (identique à la modale chambres) -->
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
                >Photo</label
              >

              <div class="flex items-center gap-4">
                <label
                  for="menuitem-photo"
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
                  id="menuitem-photo"
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
                  alt="Aperçu"
                  class="w-28 h-20 object-cover rounded-md border border-gray-200 dark:border-gray-600 shadow-sm"
                />
                <div class="text-sm text-gray-700 dark:text-gray-300">
                  <div class="font-medium">{{ fileName }}</div>
                  <button
                    type="button"
                    class="mt-2 inline-flex items-center px-2 py-1 text-xs border rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
                    @click="removeFile"
                  >
                    Supprimer
                  </button>
                </div>
              </div>
            </div>

            <!-- Disponible -->
            <div class="flex items-center">
              <input
                type="checkbox"
                v-model="state.available"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                id="available-check"
              />
              <label
                for="available-check"
                class="ml-2 block text-sm text-gray-700 dark:text-gray-200"
                >Disponible à la vente</label
              >
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-4">
              <button
                type="button"
                class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-semibold hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700"
                @click="localOpen = false"
                :disabled="isLoading"
              >
                Annuler
              </button>
              <button
                type="submit"
                class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60"
                :disabled="isLoading"
              >
                <span v-if="isLoading">Enregistrement...</span>
                <span v-else>Enregistrer</span>
              </button>
            </div>
          </form>
        </div>
      </DialogContent>
    </DialogRoot>
  </div>
</template>

<style scoped>
/* tout est fait avec classes Tailwind dans template */
</style>
