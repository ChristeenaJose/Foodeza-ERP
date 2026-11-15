<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { formatMoney, downloadInvoicePdf } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { useListPage } from '@framework/composables/useListPage'
import { DEFAULT_MIN_DATE, minMonthFromDate, yearsFrom, startOfMonth, endOfMonth, startOfYear, endOfYear } from '@framework/utils/dates'
import { api } from '@framework/api/client'

const app = useAppStore()
const { rows, loading, error, load } = useListPage<any>('/invoices')
const pdfLoading = ref<number | null>(null)
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
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  minMonth.value = minMonthFromDate(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  await loadFiltered()
})

async function handlePdf(e: Event, row: any) {
  e.stopPropagation()
  pdfLoading.value = row.id
  try {
    await downloadInvoicePdf(row.id, row.invoice_number, true)
  } catch {
    alert('Could not download PDF.')
  } finally {
    pdfLoading.value = null
  }
}

async function remove(e: Event, row: any) {
  e.stopPropagation()
  const paid = row.status === 'paid'
  const msg = paid
    ? `Delete ${row.invoice_number}? This will also remove linked income and payment records.`
    : `Delete ${row.invoice_number}?`
  if (!confirm(msg)) return
  const { api } = await import('../api/client')
  await api.delete(`/invoices/${row.id}`)
  rows.value = rows.value.filter((r) => r.id !== row.id)
}
</script>

<template>
  <div>
    <div class="flex justify-between mb-6">
      <h1 class="text-2xl font-bold">Invoices</h1>
      <RouterLink to="/bookkeeping/invoices/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ Create Invoice</RouterLink>
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
            <th class="text-left p-3">Invoice #</th>
            <th class="text-left p-3">Customer</th>
            <th class="text-left p-3">Service</th>
            <th class="text-left p-3">Status</th>
            <th class="text-right p-3">Total</th>
            <th class="p-3 w-28" />
          </tr>
        </thead>
        <tbody>
          <tr v-if="rows.length === 0">
            <td colspan="6" class="p-6 text-center text-muted">No invoices yet.</td>
          </tr>
          <tr v-for="r in rows" :key="r.id" class="border-t hover:bg-stone-50 cursor-pointer" @click="$router.push(`/bookkeeping/invoices/${r.id}`)">
            <td class="p-3 font-medium text-brand">{{ r.invoice_number }}</td>
            <td class="p-3">{{ r.customer?.name }}</td>
            <td class="p-3">{{ r.service_type }}</td>
            <td class="p-3">
              <span class="px-2 py-0.5 rounded-full text-xs capitalize" :class="r.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                {{ r.status }}
              </span>
            </td>
            <td class="p-3 text-right font-medium">{{ formatMoney(+r.total, app.currencySymbol) }}</td>
            <td class="p-3 text-right whitespace-nowrap">
              <button
                class="text-xs text-brand hover:underline disabled:opacity-50 mr-3"
                :disabled="pdfLoading === r.id"
                @click="handlePdf($event, r)"
              >
                {{ pdfLoading === r.id ? '…' : 'PDF' }}
              </button>
              <button class="text-xs text-red-600 hover:underline" @click="remove($event, r)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
