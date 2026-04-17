<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from "vue";
import { useUserStore, type User } from "~/stores/userStore";
import * as z from "zod";

// Reka primitives — adapte le chemin si nécessaire
import {
  DialogRoot,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "reka-ui";

const props = defineProps<{ modelValue: boolean; user?: User | null }>();
const emit = defineEmits(["update:modelValue", "success"]);

const userStore = useUserStore();
const toast = useToast();

// Local open state pour v-model fiable
const localOpen = ref(!!props.modelValue);
watch(
  () => props.modelValue,
  (v) => (localOpen.value = !!v)
);
watch(localOpen, (v) => emit("update:modelValue", v));

// Mode édition
const isEditMode = computed(() => !!props.user);
const title = computed(() =>
  isEditMode.value ? "Modifier l'Utilisateur" : "Nouvel Utilisateur"
);

// Schéma de base (nom, email, role)
const baseSchema = z.object({
  name: z.string().min(2, "Le nom est trop court"),
  email: z.string().email("Email invalide"),
  role: z.string().min(1, "Un rôle est requis"),
});

// state du formulaire
const state = reactive({
  name: "",
  email: "",
  role: "Client",
  password: "",
  password_confirmation: "",
});

// Liste des rôles - charger depuis l'API
const roles = ref<string[]>([])
const isLoadingRoles = ref(false)

// Charger les rôles depuis l'API
async function loadRoles() {
  isLoadingRoles.value = true
  try {
    const nuxt = useNuxtApp()
    const api = nuxt.$api as any
    const response = await api.get('/roles')
    roles.value = response.data.map((r: any) => r.name)
  } catch (error) {
    console.error('Error loading roles:', error)
    // Fallback sur les rôles de base si l'API échoue
    roles.value = ["Super-Admin", "Receptionist", "Service-Manager", "Économiste", "Client"]
  } finally {
    isLoadingRoles.value = false
  }
}

// Charger les rôles au montage et à l'ouverture du modal
onMounted(() => {
  loadRoles()
})

watch(localOpen, (isOpen) => {
  if (isOpen && roles.value.length === 0) {
    loadRoles()
  }
})

// erreurs par champ
const errors = reactive({
  name: "" as string | null,
  email: "" as string | null,
  role: "" as string | null,
  password: "" as string | null,
  password_confirmation: "" as string | null,
});

// key pour forcer remount du form à l'ouverture (utile pour reset DOM)
const formKey = ref(0);
watch(localOpen, (v) => {
  if (v) {
    formKey.value++;
    // Charger les rôles si pas encore chargés
    if (roles.value.length === 0) {
      loadRoles()
    }
  }
});

// init / reset du formulaire à l'ouverture
watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      if (props.user) {
        state.name = props.user.name ?? "";
        state.email = props.user.email ?? "";
        state.role = (props.user.roles?.[0]?.name ?? roles.value[0]) || "Client";
        state.password = "";
        state.password_confirmation = "";
      } else {
        resetForm();
      }
      clearErrors();
    }
  }
);

function resetForm() {
  state.name = "";
  state.email = "";
  state.role = roles.value[0] || "Client"; // Utiliser le premier rôle disponible
  state.password = "";
  state.password_confirmation = "";
}

function clearErrors() {
  errors.name = "";
  errors.email = "";
  errors.role = "";
  errors.password = "";
  errors.password_confirmation = "";
}

const isLoading = ref(false);
const descriptionId = `dialog-desc-${Math.random().toString(36).slice(2, 9)}`;

function validateBeforeSubmit(): boolean {
  clearErrors();

  const base = baseSchema.safeParse({
    name: state.name,
    email: state.email,
    role: state.role,
  });

  if (!base.success) {
    const flatten = base.error.flatten().fieldErrors;
    if (flatten.name?.[0]) errors.name = flatten.name[0];
    if (flatten.email?.[0]) errors.email = flatten.email[0];
    if (flatten.role?.[0]) errors.role = flatten.role[0];
  }

  // Password rules:
  if (!isEditMode.value) {
    if (!state.password || state.password.length < 8) {
      errors.password = "Mot de passe requis (8 caractères minimum)";
    }
    if (state.password !== state.password_confirmation) {
      errors.password_confirmation = "Les mots de passe ne correspondent pas";
    }
  } else {
    if (state.password) {
      if (state.password.length < 8) {
        errors.password = "8 caractères minimum";
      }
      if (state.password !== state.password_confirmation) {
        errors.password_confirmation = "Les mots de passe ne correspondent pas";
      }
    }
  }

  return (
    !errors.name &&
    !errors.email &&
    !errors.role &&
    !errors.password &&
    !errors.password_confirmation
  );
}

async function handleSubmit() {
  if (!validateBeforeSubmit()) {
    const firstErr = document.querySelector(".border-red-500");
    if (firstErr) (firstErr as HTMLElement).focus?.();
    return;
  }

  isLoading.value = true;

  const payload: any = {
    name: state.name,
    email: state.email,
    role: state.role,
  };

  if (!isEditMode.value) {
    payload.password = state.password;
    payload.password_confirmation = state.password_confirmation;
  } else if (state.password) {
    payload.password = state.password;
    payload.password_confirmation = state.password_confirmation;
  }

  try {
    if (isEditMode.value && props.user) {
      await userStore.update(props.user.id, payload);
      toast.add({ title: "Utilisateur mis à jour", color: "success" });
    } else {
      await userStore.create(payload);
      toast.add({ title: "Utilisateur créé", color: "success" });
    }
    emit("success");
    localOpen.value = false;
  } catch (error: any) {
    const msg = error?.response?.data?.message ?? "Une erreur est survenue.";
    toast.add({ title: "Erreur", description: msg, color: "error" });
  } finally {
    isLoading.value = false;
  }
}

function onCancel() {
  localOpen.value = false;
  clearErrors();
}
</script>

<template>
  <!-- monte le dialog uniquement quand open pour éviter mounts invisibles qui déclenchent warnings -->
  <div v-if="localOpen">
    <DialogRoot v-model:open="localOpen">
      <DialogOverlay />

      <DialogContent
        :aria-describedby="descriptionId"
        class="max-w-2xl mx-auto"
      >
        <div class="p-6">
          <!-- Title (visible) -->
          <DialogTitle>
            <h2 class="text-xl font-semibold">{{ title }}</h2>
          </DialogTitle>

          <DialogDescription
            :id="descriptionId"
            class="text-sm text-gray-600 mt-1"
          >
            {{ isEditMode ? 'Modifiez les informations du membre et son rôle.' : 'Créez un nouveau membre du personnel avec son rôle correspondant.' }}
          </DialogDescription>

          <form
            :key="formKey"
            @submit.prevent="handleSubmit"
            class="space-y-4 mt-4"
          >
            <!-- Nom -->
            <div>
              <label class="block text-sm font-medium text-gray-700"
                >Nom complet</label
              >
              <input
                v-model="state.name"
                :class="[
                  'block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white',
                  errors.name ? 'border-red-500' : '',
                ]"
                type="text"
                autocomplete="name"
              />
              <p v-if="errors.name" class="text-xs text-red-600 mt-1">
                {{ errors.name }}
              </p>
            </div>

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-gray-700"
                >Email</label
              >
              <input
                v-model="state.email"
                :class="[
                  'block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white',
                  errors.email ? 'border-red-500' : '',
                ]"
                type="email"
                autocomplete="email"
              />
              <p v-if="errors.email" class="text-xs text-red-600 mt-1">
                {{ errors.email }}
              </p>
            </div>

            <!-- Rôle -->
            <div>
              <label class="block text-sm font-medium text-gray-700"
                >Rôle</label
              >
              <select
                v-model="state.role"
                :disabled="isLoadingRoles"
                :class="[
                  'block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed',
                  errors.role ? 'border-red-500' : '',
                ]"
              >
                <option disabled value="">{{ isLoadingRoles ? 'Chargement...' : 'Sélectionner un rôle' }}</option>
                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
              </select>
              <p class="text-xs text-gray-500 mt-1">
                Sélectionnez le rôle correspondant aux permissions de ce membre
              </p>
              <p v-if="errors.role" class="text-xs text-red-600 mt-1">
                {{ errors.role }}
              </p>
            </div>

            <!-- Password -->
            <div>
              <label class="block text-sm font-medium text-gray-700">
                {{
                  isEditMode
                    ? "Nouveau Mot de passe (laisser vide pour ne pas changer)"
                    : "Mot de passe"
                }}
              </label>
              <input
                v-model="state.password"
                :class="[
                  'block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white',
                  errors.password ? 'border-red-500' : '',
                ]"
                type="password"
                autocomplete="new-password"
              />
              <p v-if="errors.password" class="text-xs text-red-600 mt-1">
                {{ errors.password }}
              </p>
            </div>

            <!-- Password confirmation -->
            <div>
              <label class="block text-sm font-medium text-gray-700"
                >Confirmer le mot de passe</label
              >
              <input
                v-model="state.password_confirmation"
                :class="[
                  'block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white',
                  errors.password_confirmation ? 'border-red-500' : '',
                ]"
                type="password"
                autocomplete="new-password"
              />
              <p
                v-if="errors.password_confirmation"
                class="text-xs text-red-600 mt-1"
              >
                {{ errors.password_confirmation }}
              </p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-2">
              <button
                type="button"
                class="px-3 py-2 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700"
                @click="onCancel"
                :disabled="isLoading"
              >
                Annuler
              </button>

              <button
                type="submit"
                class="px-3 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 disabled:opacity-60"
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
/* rien de spécial ici — tout styling se fait avec Tailwind classes dans le template */
</style>
