<script setup lang="ts">
import { sub } from 'date-fns'
import type { DropdownMenuItem } from '@nuxt/ui'
import type { Period, Range } from '~/types'
import { useStatsStore } from '~/stores/statsStore'
import { useAuthStore } from '~/stores/auth'
import { onMounted } from 'vue'

const { isNotificationsSlideoverOpen } = useDashboard()
const statsStore = useStatsStore()
const authStore = useAuthStore()

const items = [[{
  label: 'New mail',
  icon: 'i-lucide-send',
  to: '/inbox'
}, {
  label: 'New customer',
  icon: 'i-lucide-user-plus',
  to: '/customers'
}]] satisfies DropdownMenuItem[][]

const range = shallowRef<Range>({
  start: sub(new Date(), { days: 14 }),
  end: new Date()
})
const period = ref<Period>('daily')

// Charger les statistiques du bar/restaurant si l'utilisateur a la permission
onMounted(async () => {
  if (authStore.can('service-stats-view')) {
    await statsStore.fetchStats()
  }
})

const formatPrice = (value: number) =>
  new Intl.NumberFormat("fr-FR").format(value ?? 0) + " XOF"

const formatDate = (date: string) =>
  new Date(date).toLocaleTimeString("fr-FR", {
    hour: "2-digit",
    minute: "2-digit",
  })
</script>

<template>
  <UDashboardPanel id="home">
    <template #header>
      <UDashboardNavbar title="Home" :ui="{ right: 'gap-3' }">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>

        <template #right>
          <UTooltip text="Notifications" :shortcuts="['N']">
            <UButton
              color="neutral"
              variant="ghost"
              square
              @click="isNotificationsSlideoverOpen = true"
            >
              <UChip color="error" inset>
                <UIcon name="i-lucide-bell" class="size-5 shrink-0" />
              </UChip>
            </UButton>
          </UTooltip>

          <UDropdownMenu :items="items">
            <UButton icon="i-lucide-plus" size="md" class="rounded-full" />
          </UDropdownMenu>
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <template #left>
          <!-- NOTE: The `-ms-1` class is used to align with the `DashboardSidebarCollapse` button here. -->
          <HomeDateRangePicker v-model="range" class="-ms-1" />

          <HomePeriodSelect v-model="period" :range="range" />
        </template>
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="space-y-6">
        <!-- Statistiques principales -->
        <HomeStats :period="period" :range="range" />
        
        <!-- Graphique de revenus -->
        <HomeChart :period="period" :range="range" />
        
        <!-- Tableau des dernières ventes -->
        <UCard>
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold">Dernières Transactions</h3>
                <p class="text-sm text-muted">Vue d'ensemble des ventes récentes</p>
              </div>
            </div>
          </template>
          <HomeSales :period="period" :range="range" />
        </UCard>
      </div>
      
      <!-- Statistiques du Bar/Restaurant -->
      <div v-if="authStore.can('service-stats-view')" class="mt-6">
        <UCard>
          <template #header>
            <h2 class="text-xl font-semibold">Statistiques des Services</h2>
          </template>
          
          <div v-if="statsStore.isLoading || !statsStore.data" class="text-center p-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
            <p class="mt-2">Chargement des statistiques...</p>
          </div>
          
          <div v-else class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <UCard>
                <template #header>Chiffre d'affaires (jour)</template>
                <p class="text-2xl font-bold">
                  {{ formatPrice(statsStore.data?.todayRevenue ?? 0) }}
                </p>
              </UCard>
              <UCard>
                <template #header>Commandes (mois)</template>
                <p class="text-2xl font-bold">
                  {{ statsStore.data?.monthlyOrders ?? 0 }}
                </p>
              </UCard>
              <UCard>
                <template #header>Article le plus populaire</template>
                <p class="text-2xl font-bold truncate">
                  {{ statsStore.data?.topItem ?? "N/A" }}
                </p>
              </UCard>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <UCard>
                <template #header>Top 5 des articles les plus vendus</template>
                <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                  <li
                    v-for="item in statsStore.data?.topItems ?? []"
                    :key="item.name"
                    class="py-2 flex justify-between items-center"
                  >
                    <span>{{ item.name }}</span>
                    <UBadge color="primary" variant="subtle">
                      {{ (item.total_quantity || item.quantity || 0) }} ventes
                    </UBadge>
                  </li>
                </ul>
              </UCard>
              <UCard>
                <template #header>5 dernières commandes</template>
                <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                  <li
                    v-for="order in statsStore.data?.recentOrders ?? []"
                    :key="order.id"
                    class="py-2"
                  >
                    <div class="flex justify-between items-center">
                      <span>#{{ order.id }} - {{ order.location }}</span>
                      <span class="font-semibold">{{
                        formatPrice(order.total)
                      }}</span>
                    </div>
                    <p class="text-xs text-gray-500">
                      {{ formatDate(order.date) }}
                    </p>
                  </li>
                </ul>
              </UCard>
            </div>
          </div>
        </UCard>
      </div>
    </template>
  </UDashboardPanel>
</template>
