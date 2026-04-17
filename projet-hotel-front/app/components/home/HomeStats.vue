<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Period, Range, Stat } from '~/types'

const props = defineProps<{
  period: Period
  range: Range
}>()

function formatCurrency(value: number | string): string {
  const numValue = typeof value === 'string' ? parseFloat(value.replace(/[^0-9.-]/g, '')) || 0 : value
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0
  }).format(numValue)
}

const stats = ref<Stat[]>([])

const fetchStats = async () => {
  const nuxt = useNuxtApp()
  const api = nuxt.$api as any

  try {
    // S'assurer que les dates sont des objets Date valides
    const startDate = props.range.start instanceof Date 
      ? props.range.start.toISOString().split('T')[0]
      : new Date(props.range.start).toISOString().split('T')[0]
    const endDate = props.range.end instanceof Date
      ? props.range.end.toISOString().split('T')[0]
      : new Date(props.range.end).toISOString().split('T')[0]
    
    // Le backend attend 'day', 'week', ou 'month'
    const period = props.period === 'daily' ? 'day' : props.period === 'weekly' ? 'week' : props.period === 'monthly' ? 'month' : 'day'

    console.log('🔵 Fetching dashboard stats:', { startDate, endDate, period })

    const response = await api.get('/dashboard/stats', {
      params: {
        start_date: startDate,
        end_date: endDate,
        period
      }
    })
    
    console.log('✅ Dashboard stats response:', response.data)

    const statsData = response.data?.stats

    if (!statsData) {
      stats.value = []
      return
    }

    stats.value = [
      {
        title: 'Clients',
        icon: 'i-lucide-users',
        value: statsData.customers?.value ?? 0,
        variation: statsData.customers?.variation ?? 0,
        to: '/customers'
      },
      {
        title: 'Réservations',
        icon: 'i-lucide-calendar-check',
        value: statsData.reservations?.value ?? 0,
        variation: statsData.reservations?.variation ?? 0,
        to: '/admin/reservations'
      },
      {
        title: 'Commandes',
        icon: 'i-lucide-shopping-cart',
        value: statsData.orders?.value ?? 0,
        variation: statsData.orders?.variation ?? 0,
        to: '/admin/services?tab=orders'
      }
    ]
  } catch (error) {
    console.error('Error fetching dashboard stats:', error)
    stats.value = []
  }
}

watch([() => props.period, () => props.range], fetchStats, { immediate: true })
</script>

<template>
  <UPageGrid class="lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-px">
    <UPageCard
      v-for="(stat, index) in stats"
      :key="index"
      :icon="stat.icon"
      :title="stat.title"
      :to="stat.to"
      variant="subtle"
      :ui="{
        container: 'gap-y-1.5',
        wrapper: 'items-start',
        leading: 'p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col',
        title: 'font-normal text-muted text-xs uppercase'
      }"
      class="lg:rounded-none first:rounded-l-lg last:rounded-r-lg hover:z-1 cursor-pointer transition-all hover:bg-primary/5"
      @click="stat.to && navigateTo(stat.to)"
    >
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-2xl font-semibold text-highlighted">
          {{ stat.value }}
        </span>

        <UBadge
          v-if="stat.variation !== undefined && stat.variation !== null"
          :color="stat.variation > 0 ? 'success' : stat.variation < 0 ? 'error' : 'neutral'"
          variant="subtle"
          class="text-xs"
        >
          {{ stat.variation > 0 ? '+' : '' }}{{ typeof stat.variation === 'number' ? stat.variation.toFixed(1) : stat.variation }}%
        </UBadge>
      </div>
    </UPageCard>
  </UPageGrid>
</template>
