<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'

const app = useAppStore()
const report = ref<any>(null)
const from = ref('2025-01-01')
const to = ref(new Date().toISOString().slice(0, 10))
const minDate = ref(DEFAULT_MIN_DATE)

async function load() {
  const { data } = await api.get('/reports/vat', { params: { from: from.value, to: to.value } })
  report.value = data
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE
  from.value = minDate.value
  await load()
})
</script>

<template>
  <div>
    <div class="flex justify-between items-end mb-6">
      <h1 class="text-2xl font-bold">VAT / Tax Report</h1>
      <div class="flex gap-3 items-end">
        <label class="text-sm block">
          From
          <input v-model="from" type="date" :min="minDate" class="mt-1 block border rounded-lg px-3 py-2" @change="load" />
        </label>
        <label class="text-sm block">
          To
          <input v-model="to" type="date" :min="minDate" class="mt-1 block border rounded-lg px-3 py-2" @change="load" />
        </label>
      </div>
    </div>
    <div v-if="report" class="grid grid-cols-4 gap-4">
      <div class="bg-white rounded-xl p-5 shadow-sm"><div class="text-xs text-muted uppercase">Revenue</div><div class="text-xl font-bold">{{ formatMoney(report.revenue, app.currencySymbol) }}</div></div>
      <div class="bg-white rounded-xl p-5 shadow-sm"><div class="text-xs text-muted uppercase">VAT Collected</div><div class="text-xl font-bold">{{ formatMoney(report.vat_collected, app.currencySymbol) }}</div></div>
      <div class="bg-white rounded-xl p-5 shadow-sm"><div class="text-xs text-muted uppercase">VAT Paid</div><div class="text-xl font-bold">{{ formatMoney(report.vat_paid, app.currencySymbol) }}</div></div>
      <div class="bg-white rounded-xl p-5 shadow-sm"><div class="text-xs text-muted uppercase">VAT Due</div><div class="text-xl font-bold">{{ formatMoney(report.vat_due, app.currencySymbol) }}</div></div>
    </div>
  </div>
</template>
