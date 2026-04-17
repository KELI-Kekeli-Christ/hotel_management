<script setup lang="ts">
import { format } from 'date-fns'
import type { Mail } from '~/types'

defineProps<{
  mail: Mail
}>()

const emits = defineEmits(['close'])

const dropdownItems = [[{
  label: 'Mark as unread',
  icon: 'i-lucide-check-circle'
}, {
  label: 'Mark as important',
  icon: 'i-lucide-triangle-alert'
}], [{
  label: 'Star thread',
  icon: 'i-lucide-star'
}, {
  label: 'Mute thread',
  icon: 'i-lucide-circle-pause'
}]]

const toast = useToast()

const reply = ref('')
const loading = ref(false)

function onSubmit() {
  loading.value = true

  setTimeout(() => {
    reply.value = ''

    toast.add({
      title: 'Email sent',
      description: 'Your email has been sent successfully',
      icon: 'i-lucide-check-circle',
      color: 'success'
    })

    loading.value = false
  }, 1000)
}
</script>

<template>
  <UDashboardPanel id="inbox-2">
    <UDashboardNavbar :title="mail.subject" :toggle="false">
      <template #leading>
        <UButton
          icon="i-lucide-x"
          color="neutral"
          variant="ghost"
          class="-ms-1.5"
          @click="emits('close')"
        />
      </template>

      <template #right>
        <UTooltip text="Archive">
          <UButton
            icon="i-lucide-inbox"
            color="neutral"
            variant="ghost"
          />
        </UTooltip>

        <UTooltip v-if="mail.type !== 'order'" text="Reply">
          <UButton icon="i-lucide-reply" color="neutral" variant="ghost" />
        </UTooltip>
        <UTooltip v-if="mail.type === 'order' && mail.data?.order_id" text="Voir la commande">
          <UButton 
            :to="`/admin/services?tab=orders&order=${mail.data.order_id}`"
            icon="i-lucide-external-link" 
            color="primary" 
            variant="ghost" 
          />
        </UTooltip>

        <UDropdownMenu :items="dropdownItems">
          <UButton
            icon="i-lucide-ellipsis-vertical"
            color="neutral"
            variant="ghost"
          />
        </UDropdownMenu>
      </template>
    </UDashboardNavbar>

    <div class="flex flex-col sm:flex-row justify-between gap-1 p-4 sm:px-6 border-b border-default">
      <div class="flex items-start gap-4 sm:my-1.5">
        <UAvatar
          v-bind="mail.from.avatar"
          :alt="mail.from.name"
          size="3xl"
        />

        <div class="min-w-0">
          <p class="font-semibold text-highlighted">
            {{ mail.from.name }}
          </p>
          <p class="text-muted">
            {{ mail.from.email }}
          </p>
        </div>
      </div>

      <p class="max-sm:pl-16 text-muted text-sm sm:mt-2">
        {{ format(new Date(mail.date), 'dd MMM HH:mm') }}
      </p>
    </div>

    <div class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-4">
      <!-- Badge pour les commandes -->
      <div v-if="mail.type === 'order' && mail.data" class="mb-4">
        <UBadge
          :color="mail.data.status === 'pending' ? 'warning' : mail.data.status === 'completed' ? 'success' : 'neutral'"
          variant="subtle"
          size="lg"
        >
          {{ mail.data.status === 'pending' ? 'En attente' : mail.data.status === 'completed' ? 'Terminée' : mail.data.status }}
        </UBadge>
        <UBadge
          v-if="mail.data.order_type"
          color="primary"
          variant="subtle"
          size="lg"
          class="ml-2"
        >
          {{ mail.data.order_type === 'room_service' ? 'Service en chambre' : 'Restaurant' }}
        </UBadge>
      </div>

      <!-- Détails de la commande -->
      <div v-if="mail.type === 'order' && mail.data" class="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-4 space-y-2">
        <h3 class="font-semibold text-lg mb-3">Détails de la commande</h3>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div>
            <span class="text-muted">Numéro:</span>
            <span class="font-medium ml-2">#{{ mail.data.order_id }}</span>
          </div>
          <div>
            <span class="text-muted">Localisation:</span>
            <span class="font-medium ml-2">{{ mail.data.location }}</span>
          </div>
          <div class="col-span-2">
            <span class="text-muted">Montant total:</span>
            <span class="font-bold text-lg text-primary-600 dark:text-primary-400 ml-2">
              {{ new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(mail.data.total || 0) }}
            </span>
          </div>
        </div>
        <div v-if="mail.data.order_id" class="mt-4">
          <UButton
            :to="`/admin/services?tab=orders&order=${mail.data.order_id}`"
            color="primary"
            variant="outline"
            label="Voir la commande"
            icon="i-lucide-external-link"
          />
        </div>
      </div>

      <!-- Corps du message -->
      <div class="whitespace-pre-wrap text-sm leading-relaxed">
        {{ mail.body }}
      </div>
    </div>

    <div v-if="mail.type !== 'order'" class="pb-4 px-4 sm:px-6 shrink-0">
      <UCard variant="subtle" class="mt-auto" :ui="{ header: 'flex items-center gap-1.5 text-dimmed' }">
        <template #header>
          <UIcon name="i-lucide-reply" class="size-5" />

          <span class="text-sm truncate">
            Répondre à {{ mail.from.name }} ({{ mail.from.email }})
          </span>
        </template>

        <form @submit.prevent="onSubmit">
          <UTextarea
            v-model="reply"
            color="neutral"
            variant="none"
            required
            autoresize
            placeholder="Écrire votre réponse..."
            :rows="4"
            :disabled="loading"
            class="w-full"
            :ui="{ base: 'p-0 resize-none' }"
          />

          <div class="flex items-center justify-between">
            <UTooltip text="Joindre un fichier">
              <UButton
                color="neutral"
                variant="ghost"
                icon="i-lucide-paperclip"
              />
            </UTooltip>

            <div class="flex items-center justify-end gap-2">
              <UButton
                color="neutral"
                variant="ghost"
                label="Enregistrer brouillon"
              />
              <UButton
                type="submit"
                color="neutral"
                :loading="loading"
                label="Envoyer"
                icon="i-lucide-send"
              />
            </div>
          </div>
        </form>
      </UCard>
    </div>
    
    <div v-else-if="mail.type === 'order' && mail.data?.order_id" class="pb-4 px-4 sm:px-6 shrink-0 border-t border-default pt-4">
      <UButton
        :to="`/admin/services?tab=orders&order=${mail.data.order_id}`"
        color="primary"
        size="lg"
        block
        icon="i-lucide-shopping-cart"
        label="Gérer la commande"
      />
    </div>
  </UDashboardPanel>
</template>
