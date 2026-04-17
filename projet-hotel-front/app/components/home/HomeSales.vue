<script setup lang="ts">
import { h, resolveComponent } from 'vue'
import type { TableColumn } from '@nuxt/ui'
import type { Period, Range, Sale } from '~/types'

const props = defineProps<{
  period: Period
  range: Range
}>()

const UBadge = resolveComponent('UBadge')

const data = ref<Sale[]>([])

const fetchSales = async () => {
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
    
    const period = props.period === 'daily' ? 'day' : props.period === 'weekly' ? 'week' : props.period === 'monthly' ? 'month' : 'day'

    const response = await api.get('/dashboard/stats', {
      params: {
        start_date: startDate,
        end_date: endDate,
        period
      }
    })
    
    console.log('✅ Sales data response:', response.data?.recentTransactions?.length || 0, 'transactions')

    const transactions = response.data?.recentTransactions || []

    data.value = transactions.map((transaction: any) => ({
      id: transaction.id?.toString() || transaction.ref || '',
      date: transaction.date,
      status: transaction.status || 'paid',
      email: transaction.client || transaction.email || 'N/A',
      amount: transaction.amount || 0
    })) as Sale[]
  } catch (error) {
    console.error('Error fetching sales data:', error)
    data.value = []
  }
}

watch([() => props.period, () => props.range], fetchSales, { immediate: true })

const handleRowClick = (row: any) => {
  // Naviguer vers la page de détail de la transaction/invoice si disponible
  if (row.original.id) {
    navigateTo(`/admin/invoices`)
  }
}

const columns: TableColumn<Sale>[] = [
  {
    accessorKey: 'id',
    header: 'ID',
    cell: ({ row }) => `#${row.getValue('id')}`
  },
  {
    accessorKey: 'date',
    header: 'Date',
    cell: ({ row }) => {
      return new Date(row.getValue('date')).toLocaleString('en-US', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      })
    }
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const color = {
        paid: 'success' as const,
        failed: 'error' as const,
        refunded: 'neutral' as const
      }[row.getValue('status') as string]

      return h(UBadge, { class: 'capitalize', variant: 'subtle', color }, () =>
        row.getValue('status')
      )
    }
  },
  {
    accessorKey: 'email',
    header: 'Email'
  },
  {
    accessorKey: 'amount',
    header: () => h('div', { class: 'text-right' }, 'Amount'),
    cell: ({ row }) => {
      const amount = Number.parseFloat(row.getValue('amount'))

      const formatted = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'XOF'
      }).format(amount)

      return h('div', { class: 'text-right font-medium' }, formatted)
    }
  }
]
</script>

<template>
  <div>
    <UTable
      v-if="data.value && data.value.length > 0"
      :data="data.value"
      :columns="columns"
      class="shrink-0"
      :ui="{
        base: 'table-fixed border-separate border-spacing-0',
        thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
        tbody: '[&>tr]:last:[&>td]:border-b-0 [&>tr]:cursor-pointer [&>tr]:hover:bg-primary/5',
        th: 'first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
        td: 'border-b border-default'
      }"
      @select="handleRowClick"
    >
      <template #id-data="{ row }">
        <span 
          class="text-primary-600 dark:text-primary-400 hover:underline cursor-pointer"
          @click.stop="handleRowClick(row)"
        >
          #{{ row.original.id }}
        </span>
      </template>
    </UTable>
    <div v-else class="py-12 text-center text-gray-500 dark:text-gray-400">
      <UIcon name="i-lucide-shopping-cart" class="w-12 h-12 mx-auto mb-4 opacity-50" />
      <p>Aucune transaction récente pour cette période</p>
    </div>
  </div>
</template>
