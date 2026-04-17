<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6"
      aria-modal="true"
      role="dialog"
      :aria-labelledby="titleId"
      @keydown.esc="onCancel"
    >
      <!-- overlay -->
      <div
        class="fixed inset-0 bg-black/40 backdrop-blur-sm"
        @click="onBackdrop && onCancel"
      ></div>

      <!-- modal -->
      <div
        ref="dialog"
        class="relative z-10 w-full max-w-lg bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden"
        @click.stop
      >
        <header class="px-6 py-4 border-b dark:border-gray-800">
          <h3
            :id="titleId"
            class="text-lg font-semibold text-gray-900 dark:text-white"
          >
            {{ title }}
          </h3>
          <p
            v-if="description"
            class="text-sm text-gray-600 dark:text-gray-400 mt-1"
          >
            {{ description }}
          </p>
        </header>

        <section class="px-6 py-4">
          <slot>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              Êtes-vous sûr de vouloir continuer ?
            </p>
          </slot>
        </section>

        <footer
          class="px-6 py-4 border-t flex justify-end gap-3 dark:border-gray-800"
        >
          <button
            type="button"
            class="px-4 py-2 rounded-md bg-transparent text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
            @click="onCancel"
            :disabled="loading"
          >
            {{ cancelLabel }}
          </button>

          <button
            type="button"
            class="px-4 py-2 rounded-md text-sm text-white bg-red-600 hover:bg-red-700 focus:ring-2 focus:ring-red-400"
            @click="onConfirm"
            :disabled="loading"
          >
            <span v-if="!loading">{{ confirmLabel }}</span>
            <span v-else>Suppression…</span>
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, nextTick } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, default: "Confirmer l'action" },
  description: { type: String, default: "" },
  confirmLabel: { type: String, default: "Confirmer" },
  cancelLabel: { type: String, default: "Annuler" },
  // si true, cliquer sur l'overlay ferme la modal
  onBackdrop: { type: Boolean, default: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits<{
  (e: "update:modelValue", v: boolean): void;
  (e: "confirm"): void;
  (e: "cancel"): void;
}>();

const dialog = ref<HTMLElement | null>(null);
const titleId = `confirm-modal-title-${Math.random().toString(36).slice(2, 9)}`;

function onCancel() {
  emit("cancel");
  emit("update:modelValue", false);
}

function onConfirm() {
  emit("confirm");
  // on laisse le parent fermer la modal après l'opération (souvent utile pour loader)
}

watch(
  () => props.modelValue,
  async (v) => {
    if (v) {
      // focus management : focus sur le dialog for accessibility
      await nextTick();
      dialog.value?.focus();
    }
  }
);

onMounted(() => {
  // nothing extra for now
});
</script>

<style scoped>
/* s'assurer que le dialog peut recevoir le focus */
div[role="dialog"] > div[ref="dialog"] {
  outline: none;
}
</style>
