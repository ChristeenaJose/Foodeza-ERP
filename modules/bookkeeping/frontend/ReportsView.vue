<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { DEFAULT_MIN_DATE, yearsFrom } from '@framework/utils/dates'

const app = useAppStore()
const data = ref<any>(null)
const year = ref(String(new Date().getFullYear()))
const years = ref(yearsFrom())

async function load() {
  const from = `${year.value}-01-01`
  const to = `${year.value}-12-31`
  const { data: res } = await api.get('/reports/profit-loss', {
    params: { month: `${year.value}-01`, from, to, report_from: from, report_to: to },
  })
  data.value = res
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  await load()
})
</script>

<template>
  <div>
    <div class="flex justify-between mb-6">
      <h1 class="text-2xl font-bold">Reports</h1>
      <div class="flex gap-3 items-center">
        <label class="text-sm">
          Year
          <select v-model="year" class="ml-2 border rounded-lg px-3 py-2" @change="load">
            <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
          </select>
        </label>
        <RouterLink to="/bookkeeping/reports/vat" class="bg-stone-200 px-4 py-2 rounded-lg">VAT Report</RouterLink>
        <RouterLink to="/bookkeeping/reports/tax" class="bg-brand text-white px-4 py-2 rounded-lg">Steuerberater-Paket</RouterLink>
      </div>
    </div>
    <div v-if="data" class="grid grid-cols-2 gap-6">
      <div class="bg-white rounded-xl p-5 shadow-sm">
        <h2 class="font-semibold mb-3">Revenue by Service ({{ year }})</h2>
        <div v-for="s in data.service_revenue" :key="s.service" class="flex justify-between py-2 border-b text-sm">
          <span>{{ s.service }}</span>
          <span class="text-income font-medium">{{ formatMoney(s.total, app.currencySymbol) }}</span>
        </div>
      </div>
      <div class="bg-white rounded-xl p-5 shadow-sm">
        <h2 class="font-semibold mb-3">Expenses by Category ({{ year }})</h2>
        <div v-for="c in data.expense_by_category" :key="c.category_name" class="flex justify-between py-2 border-b text-sm">
          <span>{{ c.category_name }}</span>
          <span class="text-expense font-medium">{{ formatMoney(+c.total, app.currencySymbol) }}</span>
        </div>
      </div>
      <div class="bg-white rounded-xl p-5 shadow-sm col-span-2">
        <h2 class="font-semibold mb-3">Profit by Month ({{ year }})</h2>
        <table class="w-full text-sm">
          <thead><tr class="text-muted text-xs uppercase"><th class="text-left py-2">Month</th><th class="text-right">Revenue</th><th class="text-right">Expenses</th><th class="text-right">Net</th></tr></thead>
          <tbody>
            <tr v-for="m in data.profit_by_month" :key="m.month" class="border-t">
              <td class="py-2">{{ m.month }}</td>
              <td class="text-right">{{ formatMoney(m.revenue, app.currencySymbol) }}</td>
              <td class="text-right">{{ formatMoney(m.expenses, app.currencySymbol) }}</td>
              <td class="text-right font-medium">{{ formatMoney(m.net_profit, app.currencySymbol) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
