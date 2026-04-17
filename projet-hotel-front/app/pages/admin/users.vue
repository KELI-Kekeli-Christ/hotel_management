<script setup lang="ts">
import { ref, computed, toRaw, onMounted, h } from "vue";
import { useUserStore, type User } from "~/stores/userStore";
import { useAuthStore } from "~/stores/auth";
import { storeToRefs } from "pinia";
import type { ColumnDef } from "@tanstack/vue-table";

import AdminUsersFormModal from "~/components/admin/users/FormModal.vue";
import ConfirmModal from "~/components/admin/chambres/ConfirmModal.vue";

const authStore = useAuthStore();
const userStore = useUserStore();
const { users, isLoading } = storeToRefs(userStore);

onMounted(() => {
  userStore.fetchAll();
});

// --- rows : plain array with role string added ---
const rows = computed(() => {
  const src = users.value;
  if (!src) return [];
  const arr = Array.isArray(src) ? src : (src as any).data ?? [];
  return arr.map((u: any) => ({
    ...(toRaw(u) || u),
    role: u.roles?.[0]?.name ?? "",
  }));
});

// --- actions & modals state ---
const isFormModalOpen = ref(false);
const isConfirmModalOpen = ref(false);
const selectedUser = ref<User | null>(null);
const userToDeleteId = ref<number | null>(null);

function openCreateModal() {
  selectedUser.value = null;
  isFormModalOpen.value = true;
}
function openEditModal(user: User) {
  selectedUser.value = user;
  isFormModalOpen.value = true;
}
function openDeleteConfirm(user: User) {
  userToDeleteId.value = user.id;
  isConfirmModalOpen.value = true;
}
async function handleDeleteConfirmed() {
  if (userToDeleteId.value === null) return;
  try {
    await userStore.remove(userToDeleteId.value);
    useToast().add({ title: "Utilisateur supprimé", color: "success" });
  } catch (error: any) {
    useToast().add({
      title: "Erreur",
      description: error.response?.data?.message,
      color: "error",
    });
  } finally {
    isConfirmModalOpen.value = false;
    userToDeleteId.value = null;
  }
}

// --- columns with a custom renderer for actions ---
const columns = ref<ColumnDef<User>[]>([
  { accessorKey: "name", header: "Nom" },
  { accessorKey: "email", header: "Email" },
  { accessorKey: "role", header: "Rôle" }, // now a plain string thanks to rows computed
  {
    id: "actions",
    header: "Actions",
    // cell renderer : returns VNodes (vanilla buttons here)
    cell: ({ row }) =>
      h(
        "div",
        { class: "flex items-center gap-2" },
        [
          authStore.can("user-update")
            ? h(
                "button",
                {
                  class:
                    "px-2 py-1 rounded border text-sm bg-white hover:bg-gray-50",
                  onClick: () => openEditModal(row.original),
                },
                "Modifier"
              )
            : null,
          authStore.can("user-delete")
            ? h(
                "button",
                {
                  class:
                    "px-2 py-1 rounded border text-sm text-red-600 bg-white hover:bg-red-50",
                  onClick: () => openDeleteConfirm(row.original),
                },
                "Supprimer"
              )
            : null,
        ].filter(Boolean)
      ),
  },
]);
</script>

<template>
  <UDashboardPanel grow>
    <UDashboardNavbar title="Gestion des Utilisateurs">
      <template #right>
        <UButton
          v-if="authStore.can('user-create')"
          label="Nouvel Utilisateur"
          icon="i-heroicons-plus"
          @click="openCreateModal"
        />
      </template>
    </UDashboardNavbar>

    <UTable :columns="columns" :data="rows" :loading="isLoading">
      <!-- Colonne rôle -->
      <template #role-data="{ row }">
        <UBadge
          v-if="row.original.roles?.length > 0"
          color="primary"
          variant="subtle"
        >
          {{ row.original.roles?.[0]?.name }}
        </UBadge>
      </template>

      <!-- Colonne actions -->
      <template #actions-data="{ row }">
        <div class="flex items-center gap-2">
          <UButton
            v-if="authStore.can('user-update')"
            icon="i-heroicons-pencil"
            size="sm"
            color="neutral"
            @click="openEditModal(row.original)"
          />
          <UButton
            v-if="authStore.can('user-delete')"
            icon="i-heroicons-trash"
            size="sm"
            color="warning"
            @click="openDeleteConfirm(row.original)"
          />
        </div>
      </template>
    </UTable>

    <!-- Modales -->
    <AdminUsersFormModal
      v-if="isFormModalOpen"
      v-model="isFormModalOpen"
      :user="selectedUser"
    />
    <ConfirmModal
      v-if="isConfirmModalOpen"
      v-model="isConfirmModalOpen"
      title="Supprimer l'utilisateur ?"
      @confirm="handleDeleteConfirmed"
    />
  </UDashboardPanel>
</template>
