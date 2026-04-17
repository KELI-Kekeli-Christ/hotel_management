<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import type { Member } from '~/types'
import { useUserStore } from '~/stores/userStore'
import AdminUsersFormModal from '~/components/admin/users/FormModal.vue'

const userStore = useUserStore()
const toast = useToast()

const { data: members, refresh: refreshMembers } = await useFetch<Member[]>('/api/members', { default: () => [] })

const q = ref('')
const isFormModalOpen = ref(false)
const selectedMember = ref<any>(null)

// Filtrer les membres (exclure les clients)
const filteredMembers = computed(() => {
  if (!members.value || members.value.length === 0) {
    console.log('[members.vue] No members data available')
    return []
  }
  
  const filtered = members.value.filter((member: any) => {
    // S'assurer que member.roles existe et est un tableau
    const roles = Array.isArray(member.roles) ? member.roles : []
    
    // Exclure les clients, ne garder que le personnel
    const isClient = roles.some((r: any) => {
      const roleName = typeof r === 'string' ? r : r?.name
      return roleName === 'Client'
    })
    
    if (isClient) {
      console.log(`[members.vue] Filtering out client: ${member.name}`)
      return false
    }
    
    // Appliquer le filtre de recherche
    const searchTerm = q.value.toLowerCase()
    if (searchTerm) {
      const matches = 
        member.name?.toLowerCase().includes(searchTerm) || 
        member.email?.toLowerCase().includes(searchTerm) ||
        member.username?.toLowerCase().includes(searchTerm)
      return matches
    }
    
    return true
  })
  
  console.log(`[members.vue] Filtered ${filtered.length} members from ${members.value.length} total`)
  return filtered
})

function openCreateModal() {
  selectedMember.value = null
  isFormModalOpen.value = true
}

function openEditModal(member: any) {
  // Charger les données complètes du membre depuis le store
  const fullUser = userStore.users.find(u => u.id === member.id)
  selectedMember.value = fullUser || member
  isFormModalOpen.value = true
}

async function handleSuccess() {
  await refreshMembers()
  await userStore.fetchAll()
  isFormModalOpen.value = false
}

onMounted(async () => {
  console.log('[members.vue] Component mounted, members.value:', members.value)
  console.log('[members.vue] Members count:', members.value?.length || 0)
  
  // Rafraîchir les membres au montage
  await refreshMembers()
  
  if (userStore.users.length === 0) {
    await userStore.fetchAll()
  }
})
</script>

<template>
  <div>
    <UPageCard
      title="Membres du Personnel"
      description="Gérez les membres de l'équipe et leurs rôles."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <UButton
        label="Ajouter un membre"
        icon="i-lucide-user-plus"
        color="primary"
        class="w-fit lg:ms-auto"
        @click="openCreateModal"
      />
    </UPageCard>
    
    <AdminUsersFormModal 
      v-model="isFormModalOpen"
      :user="selectedMember"
      @success="handleSuccess"
    />

    <UPageCard variant="subtle" :ui="{ container: 'p-0 sm:p-0 gap-y-0', wrapper: 'items-stretch', header: 'p-4 mb-0 border-b border-default' }">
      <template #header>
        <UInput
          v-model="q"
          icon="i-lucide-search"
          placeholder="Search members"
          autofocus
          class="w-full"
        />
      </template>

      <SettingsMembersList :members="filteredMembers" @edit="openEditModal" />
    </UPageCard>
  </div>
</template>
