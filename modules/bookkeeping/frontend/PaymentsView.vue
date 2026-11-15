<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { formatMoney, paymentLabels } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { useListPage } from '@framework/composables/useListPage'
import { DEFAULT_MIN_DATE, minMonthFromDate, yearsFrom, startOfMonth, endOfMonth, startOfYear, endOfYear } from '@framework/utils/dates'
import { api } from '@framework/api/client'

const app = useAppStore()
const { rows: payments, loading, error, load } = useListPage<any>('/payments', (data: any) => data?.payments ?? [])
const totals = ref<any[]>([])
const period = ref<'month' | 'year'>('month')
const month = ref(new Date().toISOString().slice(0, 7))
const year = ref(String(new Date().getFullYear()))
const minMonth = ref(minMonthFromDate())
const years = ref(yearsFrom())

async function loadFiltered() {
  const params =
    period.value === 'year'
      ? { from: startOfYear(year.value), to: endOfYear(year.value) }
      : { from: startOfMonth(month.value), to: endOfMonth(month.value) }

  await load(params)
  try {
    const { data } = await api.get('/payments', { params })
    totals.value = data.totals_by_method ?? []
  } catch {
    totals.value = []
  }
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  minMonth.value = minMonthFromDate(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  await loadFiltered()
})

function formatDate(value: unknown) {
  if (!value) return '—'
  return String(value).slice(0, 10)
}
</script>

<template>
  <div>
    <div class="flex justify-between mb-6">
      <h1 class="text-2xl font-bold">Payments</h1>
      <RouterLink to="/bookkeeping/payments/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ Record Payment</RouterLink>
    </div>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm flex justify-between items-center">
      <span>{{ error }}</span>
      <button class="underline font-medium" @click="loadFiltered">Retry</button>
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

    <div class="grid grid-cols-5 gap-3 mb-6">
      <div v-for="t in totals" :key="t.payment_method" class="bg-white rounded-xl p-4 shadow-sm">
        <div class="text-xs text-muted uppercase">{{ paymentLabels[t.payment_method] ?? t.payment_method }}</div>
        <div class="font-bold">{{ formatMoney(+t.total, app.currencySymbol) }}</div>
      </div>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted uppercase text-xs">
          <tr><th class="text-left p-3">Date</th><th class="text-left p-3">Method</th><th class="text-left p-3">Invoice</th><th class="text-right p-3">Amount</th></tr>
        </thead>
        <tbody>
          <tr v-if="payments.length === 0">
            <td colspan="4" class="p-6 text-center text-muted">No payments yet.</td>
          </tr>
          <tr v-for="p in payments" :key="p.id" class="border-t">
            <td class="p-3">{{ formatDate(p.date) }}</td>
            <td class="p-3">{{ paymentLabels[p.payment_method] ?? p.payment_method }}</td>
            <td class="p-3">{{ p.invoice?.invoice_number ?? '—' }}</td>
            <td class="p-3 text-right font-medium">{{ formatMoney(+p.amount, app.currencySymbol) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
