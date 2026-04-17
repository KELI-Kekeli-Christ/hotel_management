<script setup lang="ts">
import { ref } from 'vue'
import type { DropdownMenuItem } from '@nuxt/ui'
import type { Member } from '~/types'
import { useUserStore } from '~/stores/userStore'
import { useAuthStore } from '~/stores/auth'
import ConfirmModal from '~/components/admin/chambres/ConfirmModal.vue'

const props = defineProps<{
  members: Member[]
}>()

const emit = defineEmits<{
  (e: 'edit', member: any): void
}>()

const userStore = useUserStore()
const authStore = useAuthStore()
const toast = useToast()

const isDeleteModalOpen = ref(false)
const memberToDelete = ref<any>(null)

function openEditModal(member: any) {
  emit('edit', member)
}

function openDeleteConfirm(member: any) {
  memberToDelete.value = member
  isDeleteModalOpen.value = true
}

async function handleDeleteConfirmed() {
  if (!memberToDelete.value) return
  
  try {
    await userStore.remove(memberToDelete.value.id)
    toast.add({ 
      title: "Membre supprimé", 
      description: `${memberToDelete.value.name} a été supprimé avec succès`,
      color: "success" 
    })
  } catch (error: any) {
    toast.add({
      title: "Erreur",
      description: error.response?.data?.message || "Impossible de supprimer le membre",
      color: "error",
    })
  } finally {
    isDeleteModalOpen.value = false
    memberToDelete.value = null
  }
}

function getRoleColor(roleName: string) {
  const colors: Record<string, string> = {
    'Super-Admin': 'error',
    'Receptionist': 'warning',
    'Service-Manager': 'primary',
    'Économiste': 'info',
    'Client': 'neutral'
  }
  return colors[roleName] || 'neutral'
}

const items = (member: any) => [{
  label: 'Modifier',
  icon: 'i-lucide-edit',
  onSelect: () => openEditModal(member)
}, {
  type: 'separator' as const
}, {
  label: 'Supprimer',
  icon: 'i-lucide-trash',
  color: 'error' as const,
  onSelect: () => openDeleteConfirm(member)
}] satisfies DropdownMenuItem[]
</script>

<template>
  <div>
    <ul role="list" class="divide-y divide-default">
      <li
        v-for="(member, index) in members"
        :key="member.id || index"
        class="flex items-center justify-between gap-3 py-3 px-4 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
      >
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <UAvatar
            v-bind="member.avatar"
            size="md"
          />

          <div class="text-sm min-w-0 flex-1">
            <p class="text-highlighted font-medium truncate">
              {{ member.name }}
            </p>
            <p class="text-muted truncate">
              {{ member.email || member.username }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <UBadge
            v-for="role in (member.roles || [])"
            :key="role.name"
            :color="getRoleColor(role.name)"
            variant="subtle"
            class="capitalize"
          >
            {{ role.name }}
          </UBadge>

          <UDropdownMenu :items="items(member)" :content="{ align: 'end' }">
            <UButton
              icon="i-lucide-ellipsis-vertical"
              color="neutral"
              variant="ghost"
              :disabled="!authStore.can('user-update')"
            />
          </UDropdownMenu>
        </div>
      </li>
      
      <li v-if="members.length === 0" class="py-12 text-center">
        <UIcon name="i-lucide-users" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500 dark:text-gray-400">
          Aucun membre du personnel pour le moment
        </p>
      </li>
    </ul>
    
    <ConfirmModal
      v-model="isDeleteModalOpen"
      title="Supprimer le membre"
      :message="`Êtes-vous sûr de vouloir supprimer ${memberToDelete?.name} ? Cette action est irréversible.`"
      @confirm="handleDeleteConfirmed"
    />
  </div>
</template>
