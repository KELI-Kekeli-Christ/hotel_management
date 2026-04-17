<script setup lang="ts">
import {
  ref,
  reactive,
  computed,
  watch,
  onMounted,
  onBeforeUnmount,
} from "vue";
import { useReservationStore, type Reservation } from "~/stores/reservation";
import { useUserStore, type User } from "~/stores/userStore";
import { useChambreStore, type Chambre } from "~/stores/chambre";
import {
  DialogRoot,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "reka-ui";

const props = defineProps<{
  modelValue: boolean;
  reservation?: Reservation | null;
}>();
const emit = defineEmits(["update:modelValue", "success"]);

const reservationStore = useReservationStore();
const userStore = useUserStore();
const chambreStore = useChambreStore();
const toast = useToast();

// local open state + sync with parent
const localOpen = ref(!!props.modelValue);
watch(
  () => props.modelValue,
  (v) => (localOpen.value = !!v)
);
watch(localOpen, (v) => emit("update:modelValue", v));

// formKey to force remount (cleans dynamic lists on reopen)
const formKey = ref(0);
watch(localOpen, (open) => {
  if (open) formKey.value++;
});

// mode & title
const isEditMode = computed(() => !!props.reservation);
const title = computed(() =>
  isEditMode.value ? "Modifier la Réservation" : "Nouvelle Réservation"
);

// form state
const state = reactive({
  user_id: null as number | null,
  chambre_id: null as number | null,
  date_arrive: "",
  date_depart: "",
});

// user search
const selectedUser = ref<User | null>(null);
const userSearchQuery = ref("");
const userSearchResults = ref<User[]>([]);

// load lists on mount (safe guards)
onMounted(() => {
  if (!userStore.users || userStore.users.length === 0)
    userStore.fetchAll().catch(() => {});
  if (!chambreStore.chambres || chambreStore.chambres.length === 0)
    chambreStore.fetchAll().catch(() => {});
});

// available chambres (keeps current one if editing)
const availableChambres = computed(() =>
  (chambreStore.chambres ?? []).filter(
    (c) =>
      c.disponibilite ||
      (isEditMode.value && c.id === props.reservation?.chambre_id)
  )
);

// populate form when reservation changes (and on open)
watch(
  () => props.reservation,
  (res) => {
    if (res) {
      state.user_id = res.user_id ?? null;
      state.chambre_id = res.chambre_id ?? null;

      // guard in case dates are missing or not in ISO
      try {
        state.date_arrive = (res.date_arrive ?? "").split("T")[0] || "";
      } catch {
        state.date_arrive = "";
      }
      try {
        state.date_depart = (res.date_depart ?? "").split("T")[0] || "";
      } catch {
        state.date_depart = "";
      }

      selectedUser.value = res.user ?? null;
      userSearchQuery.value = (res.user?.name ?? "") as string;
    }
  },
  { immediate: true }
);

// also watch modelValue for open -> set defaults when opening for creation
watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen && !props.reservation) resetForm();
  }
);

function resetForm() {
  state.user_id = null;
  state.chambre_id = null;
  state.date_arrive = "";
  state.date_depart = "";
  selectedUser.value = null;
  userSearchQuery.value = "";
  userSearchResults.value = [];
}

// user search behaviour
watch(userSearchQuery, (q) => {
  if (!q || q.length < 2) {
    userSearchResults.value = [];
    return;
  }
  const list = userStore.users ?? [];
  userSearchResults.value = list.filter(
    (u) =>
      u.name.toLowerCase().includes(q.toLowerCase()) ||
      u.email.toLowerCase().includes(q.toLowerCase())
  );
});

function selectUser(user: User) {
  selectedUser.value = user;
  state.user_id = user.id;
  userSearchQuery.value = user.name;
  userSearchResults.value = [];
}

// submit
const isLoading = ref(false);

async function handleSubmit() {
  if (
    !state.user_id ||
    !state.chambre_id ||
    !state.date_arrive ||
    !state.date_depart
  ) {
    toast.add({
      title: "Erreur",
      description: "Veuillez remplir tous les champs.",
      color: "error",
    });
    return;
  }

  isLoading.value = true;
  try {
    const payload = {
      user_id: state.user_id,
      chambre_id: state.chambre_id,
      date_arrive: state.date_arrive,
      date_depart: state.date_depart,
    };

    if (isEditMode.value && props.reservation) {
      const response = await reservationStore.update(props.reservation.id, payload);
      
      // Si la réponse contient un redirect_url (FedaPay), rediriger vers la page de paiement
      if (response?.redirect_url) {
        toast.add({
          title: "Redirection vers le paiement",
          description: "Vous allez être redirigé vers la page de paiement pour valider la modification",
          color: "success",
        });
        // Rediriger vers FedaPay
        window.location.href = response.redirect_url;
        return;
      }
      
      toast.add({ title: "Réservation mise à jour", color: "success" });
    } else {
      const response = await reservationStore.create(payload);
      
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
      
      toast.add({ title: "Réservation créée", color: "success" });
    }

    emit("success");
    localOpen.value = false;
  } catch (error: any) {
    toast.add({
      title: "Erreur",
      description: error?.response?.data?.message ?? "Une erreur est survenue.",
      color: "error",
    });
  } finally {
    isLoading.value = false;
  }
}

// description id for aria
const descriptionId = `dialog-desc-${Math.random().toString(36).slice(2, 9)}`;

// cleanup (none for now)
onBeforeUnmount(() => {
  // nothing heavy to cleanup here
});
</script>

<template>
  <div v-if="localOpen">
    <DialogRoot v-model:open="localOpen">
      <DialogOverlay />

      <!-- aria-describedby pour la description -->
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
            Gérez les détails de la réservation.
          </DialogDescription>

          <form
            :key="formKey"
            @submit.prevent="handleSubmit"
            class="space-y-4 mt-4"
          >
            <!-- Recherche Client -->
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                >Client</label
              >
              <div class="relative">
                <input
                  v-model="userSearchQuery"
                  type="text"
                  placeholder="Rechercher par nom ou email..."
                  class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white"
                  aria-autocomplete="list"
                  aria-controls="user-search-list"
                />
                <ul
                  v-if="userSearchResults.length > 0"
                  id="user-search-list"
                  class="absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md mt-1 max-h-48 overflow-y-auto"
                >
                  <li
                    v-for="user in userSearchResults"
                    :key="user.id"
                    @click="selectUser(user)"
                    class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                  >
                    {{ user.name }} ({{ user.email }})
                  </li>
                </ul>
              </div>
            </div>

            <!-- Chambre -->
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                >Chambre</label
              >
              <select
                v-model="state.chambre_id"
                class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white"
              >
                <option :value="null" disabled>Sélectionner une chambre</option>
                <option
                  v-for="chambre in availableChambres"
                  :key="chambre.id"
                  :value="chambre.id"
                >
                  Ch. {{ chambre.numero_chambre }} - {{ chambre.type }} ({{
                    chambre.prix_nuite.toLocaleString()
                  }}
                  XOF)
                </option>
              </select>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label
                  class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                  >Date d'arrivée</label
                >
                <input
                  v-model="state.date_arrive"
                  type="date"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white"
                />
              </div>
              <div>
                <label
                  class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                  >Date de départ</label
                >
                <input
                  v-model="state.date_depart"
                  type="date"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm border-gray-300 placeholder-gray-400 text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white"
                />
              </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-4">
              <button
                type="button"
                class="px-3 py-2 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700"
                @click="localOpen = false"
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
/* tout le styling est fait par Tailwind classes dans le template */
</style>
