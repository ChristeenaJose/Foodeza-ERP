<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { formatMoney, paymentLabels } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { useListPage } from '@framework/composables/useListPage'
import { DEFAULT_MIN_DATE, minMonthFromDate, yearsFrom, startOfMonth, endOfMonth, startOfYear, endOfYear } from '@framework/utils/dates'
import { api } from '@framework/api/client'
import { buildIncomeListQuery, parseIncomeListQuery } from './listQuery'

const route = useRoute()
const router = useRouter()
const app = useAppStore()
const { rows, loading, error, load } = useListPage<any>('/incomes')
const period = ref<'month' | 'year'>('month')
const month = ref(new Date().toISOString().slice(0, 7))
const year = ref(String(new Date().getFullYear()))
const minMonth = ref(minMonthFromDate())
const years = ref(yearsFrom())
type SortKey = 'date' | 'service' | 'payment'
const sortBy = ref<SortKey>('date')
const sortDir = ref<'asc' | 'desc'>('desc')

const listQuery = computed(() => buildIncomeListQuery({
  period: period.value,
  month: month.value,
  year: year.value,
  sort: sortBy.value,
  dir: sortDir.value,
}))

function applyRouteQuery() {
  const parsed = parseIncomeListQuery(route.query)
  period.value = parsed.period
  month.value = parsed.month
  year.value = parsed.year
  sortBy.value = (['date', 'service', 'payment'].includes(parsed.sort) ? parsed.sort : 'date') as SortKey
  sortDir.value = parsed.dir
}

function syncRouteQuery() {
  router.replace({ query: listQuery.value })
}

async function loadFiltered() {
  syncRouteQuery()
  const params =
    period.value === 'year'
      ? { from: startOfYear(year.value), to: endOfYear(year.value) }
      : { from: startOfMonth(month.value), to: endOfMonth(month.value) }
  await load(params)
}

onMounted(async () => {
  applyRouteQuery()
  const cfgRes = await api.get('/config')
  minMonth.value = minMonthFromDate(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  await loadFiltered()
})

const sortedRows = computed(() => {
  const list = [...(rows.value ?? [])]
  const dir = sortDir.value === 'asc' ? 1 : -1

  list.sort((a, b) => {
    let left = ''
    let right = ''

    if (sortBy.value === 'date') {
      left = formatDate(a.date)
      right = formatDate(b.date)
    } else if (sortBy.value === 'service') {
      left = a.service_type ?? ''
      right = b.service_type ?? ''
    } else {
      left = paymentLabels[a.payment_method] ?? a.payment_method ?? ''
      right = paymentLabels[b.payment_method] ?? b.payment_method ?? ''
    }

    return left.localeCompare(right, undefined, { sensitivity: 'base' }) * dir
  })

  return list
})

function toggleSort(key: SortKey) {
  if (sortBy.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    syncRouteQuery()
    return
  }

  sortBy.value = key
  sortDir.value = key === 'date' ? 'desc' : 'asc'
  syncRouteQuery()
}

function sortIndicator(key: SortKey) {
  if (sortBy.value !== key) return ''
  return sortDir.value === 'asc' ? ' ↑' : ' ↓'
}

async function remove(id: number) {
  if (!confirm('Delete?')) return
  const { api } = await import('../api/client')
  await api.delete(`/incomes/${id}`)
  rows.value = rows.value.filter((r) => r.id !== id)
}

function formatDate(value: unknown) {
  if (!value) return '—'
  return String(value).slice(0, 10)
}
</script>

<template>
  <div>
    <div class="flex justify-between mb-6">
      <h1 class="text-2xl font-bold">Income</h1>
      <RouterLink to="/bookkeeping/income/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ Record Income</RouterLink>
    </div>

    <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
      <div class="flex rounded-lg border overflow-hidden text-sm">
        <button type="button" class="px-4 py-2" :class="period === 'month' ? 'bg-brand text-white' : 'bg-white text-stone-700'" @click="period='month'; loadFiltered()">Monthly</button>
        <button type="button" class="px-4 py-2 border-l" :class="period === 'year' ? 'bg-brand text-white' : 'bg-white text-stone-700'" @click="period='year'; loadFiltered()">Yearly</button>
      </div>
      <input v-if="period === 'month'" v-model="month" type="month" :min="minMonth" class="border rounded-lg px-3 py-2" @change="loadFiltered" />
      <select v-else v-model="year" class="border rounded-lg px-3 py-2" @change="loadFiltered">
        <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
      </select>
    </div>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm flex justify-between items-center">
      <span>{{ error }}</span>
      <button class="underline font-medium" @click="loadFiltered">Retry</button>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted uppercase text-xs">
          <tr>
            <th class="text-left p-3">
              <button type="button" class="inline-flex items-center gap-1 hover:text-stone-900" @click="toggleSort('date')">
                Date<span class="normal-case">{{ sortIndicator('date') }}</span>
              </button>
            </th>
            <th class="text-left p-3">Customer</th>
            <th class="text-left p-3">
              <button type="button" class="inline-flex items-center gap-1 hover:text-stone-900" @click="toggleSort('service')">
                Service<span class="normal-case">{{ sortIndicator('service') }}</span>
              </button>
            </th>
            <th class="text-left p-3">
              <button type="button" class="inline-flex items-center gap-1 hover:text-stone-900" @click="toggleSort('payment')">
                Payment<span class="normal-case">{{ sortIndicator('payment') }}</span>
              </button>
            </th>
            <th class="text-left p-3">Invoice #</th>
            <th class="text-right p-3">Amount</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="sortedRows.length === 0">
            <td colspan="7" class="p-6 text-center text-muted">No income records yet.</td>
          </tr>
          <tr v-for="r in sortedRows" :key="r.id" class="border-t">
            <td class="p-3">{{ formatDate(r.date) }}</td>
            <td class="p-3">{{ r.customer?.name ?? '—' }}</td>
            <td class="p-3">{{ r.service_type }}</td>
            <td class="p-3">{{ paymentLabels[r.payment_method] ?? r.payment_method }}</td>
            <td class="p-3">{{ r.invoice_number ?? '—' }}</td>
            <td class="p-3 text-right text-income font-medium">{{ formatMoney(+r.amount, app.currencySymbol) }}</td>
            <td class="p-3 whitespace-nowrap">
              <RouterLink :to="{ path: `/bookkeeping/income/${r.id}/edit`, query: listQuery }" class="text-brand text-xs mr-3">Edit</RouterLink>
              <button class="text-red-600 text-xs" @click="remove(r.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
