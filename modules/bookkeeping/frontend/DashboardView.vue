<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { DEFAULT_MIN_DATE, minMonthFromDate, yearsFrom } from '@framework/utils/dates'
import SimpleBarChart from '@framework/components/SimpleBarChart.vue'

const app = useAppStore()
const period = ref<'month' | 'year'>('month')
const month = ref(new Date().toISOString().slice(0, 7))
const year = ref(String(new Date().getFullYear()))
const minMonth = ref(minMonthFromDate())
const years = ref(yearsFrom())
const data = ref<any>(null)

const periodLabel = computed(() => {
  if (!data.value) return ''
  return period.value === 'year' ? `Year ${data.value.year}` : data.value.month
})

const incomeBars = computed(() =>
  (data.value?.service_revenue ?? [])
    .filter((s: any) => Number(s.total) > 0)
    .map((s: any) => ({
      label: s.service,
      value: Number(s.total),
    }))
    .slice(0, 5)
)

const expenseBars = computed(() =>
  (data.value?.expense_by_category ?? [])
    .map((c: any) => ({
      label: c.category,
      value: Number(c.total),
    }))
    .slice(0, 5)
)

async function load() {
  const params =
    period.value === 'year'
      ? { period: 'year', year: year.value }
      : { period: 'month', month: month.value }

  const { data: res } = await api.get('/dashboard', { params })
  data.value = res
  app.company = res.config?.company ?? 'Foodeza'
  app.currencySymbol = res.config?.currency_symbol ?? '€'
}

function setPeriod(next: 'month' | 'year') {
  period.value = next
  load()
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  minMonth.value = minMonthFromDate(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  await load()
})
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="text-muted text-sm">Catering · Food Truck · Corporate Lunch · Meal Box Delivery</p>
        <p v-if="data" class="text-sm text-brand font-medium mt-1">{{ periodLabel }}</p>
      </div>
      <div class="flex items-center gap-3 flex-wrap">
        <div class="flex rounded-lg border overflow-hidden text-sm">
          <button
            type="button"
            class="px-4 py-2"
            :class="period === 'month' ? 'bg-brand text-white' : 'bg-white text-stone-700'"
            @click="setPeriod('month')"
          >
            Monthly
          </button>
          <button
            type="button"
            class="px-4 py-2 border-l"
            :class="period === 'year' ? 'bg-brand text-white' : 'bg-white text-stone-700'"
            @click="setPeriod('year')"
          >
            Yearly
          </button>
        </div>
        <input
          v-if="period === 'month'"
          v-model="month"
          type="month"
          :min="minMonth"
          class="border rounded-lg px-3 py-2"
          @change="load"
        />
        <select v-else v-model="year" class="border rounded-lg px-3 py-2" @change="load">
          <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
        </select>
      </div>
    </div>

    <div v-if="data" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
      <div class="bg-white rounded-xl p-5 border-l-4 border-emerald-500 shadow-sm">
        <div class="text-xs uppercase text-muted">Revenue</div>
        <div class="text-2xl font-bold">{{ formatMoney(data.profit.revenue, app.currencySymbol) }}</div>
      </div>
      <div class="bg-white rounded-xl p-5 border-l-4 border-red-500 shadow-sm">
        <div class="text-xs uppercase text-muted">Expenses</div>
        <div class="text-2xl font-bold">{{ formatMoney(data.profit.expenses, app.currencySymbol) }}</div>
      </div>
      <div class="bg-white rounded-xl p-5 border-l-4 border-amber-500 shadow-sm">
        <div class="text-xs uppercase text-muted">Gross Profit</div>
        <div class="text-2xl font-bold">{{ formatMoney(data.profit.gross_profit, app.currencySymbol) }}</div>
        <div class="text-[11px] text-muted mt-1">incl. VAT</div>
      </div>
      <div class="bg-white rounded-xl p-5 border-l-4 border-slate-400 shadow-sm">
        <div class="text-xs uppercase text-muted">Net Profit</div>
        <div class="text-2xl font-bold">{{ formatMoney(data.profit.net_profit, app.currencySymbol) }}</div>
      </div>
      <div class="bg-white rounded-xl p-5 border-l-4 border-blue-500 shadow-sm">
        <div class="text-xs uppercase text-muted">VAT Due</div>
        <div class="text-2xl font-bold">{{ formatMoney(data.profit.vat_due, app.currencySymbol) }}</div>
      </div>
    </div>

    <div v-if="data" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div v-for="s in data.service_revenue" :key="s.service" class="bg-white rounded-xl p-4 shadow-sm">
        <div class="font-medium">{{ s.service }}</div>
        <div class="text-xl font-bold text-income">{{ formatMoney(s.total, app.currencySymbol) }}</div>
        <div class="text-xs text-muted">{{ s.count }} entries<span v-if="s.days"> · {{ s.days }} days</span></div>
      </div>
    </div>

    <div v-if="data?.profit_by_month?.length" class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
      <h2 class="font-semibold p-5 pb-2">Monthly breakdown ({{ data.year }})</h2>
      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted uppercase text-xs">
          <tr>
            <th class="text-left p-3">Month</th>
            <th class="text-right p-3">Revenue</th>
            <th class="text-right p-3">Expenses</th>
            <th class="text-right p-3">Gross profit</th>
            <th class="text-right p-3">Net profit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in data.profit_by_month" :key="m.month" class="border-t">
            <td class="p-3">{{ m.month }}</td>
            <td class="p-3 text-right">{{ formatMoney(m.revenue, app.currencySymbol) }}</td>
            <td class="p-3 text-right">{{ formatMoney(m.expenses, app.currencySymbol) }}</td>
            <td class="p-3 text-right font-medium">{{ formatMoney(m.gross_profit, app.currencySymbol) }}</td>
            <td class="p-3 text-right font-medium">{{ formatMoney(m.net_profit, app.currencySymbol) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex gap-3 mb-6">
      <RouterLink to="/bookkeeping/income/new" class="bg-brand hover:bg-brand-dark text-white px-4 py-2 rounded-lg font-medium">+ Record Income</RouterLink>
      <RouterLink to="/bookkeeping/expenses/new" class="bg-stone-200 hover:bg-stone-300 px-4 py-2 rounded-lg font-medium">+ Record Expense</RouterLink>
      <RouterLink to="/bookkeeping/invoices/new" class="bg-stone-200 hover:bg-stone-300 px-4 py-2 rounded-lg font-medium">+ Create Invoice</RouterLink>
    </div>

    <div v-if="data" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <SimpleBarChart
        title="Income summary"
        :items="incomeBars"
        color="#059669"
        :currency-symbol="app.currencySymbol"
        empty-text="No income"
        compact
      />
      <SimpleBarChart
        title="Expense summary"
        :items="expenseBars"
        color="#dc2626"
        :currency-symbol="app.currencySymbol"
        empty-text="No expenses"
        compact
      />
    </div>
  </div>
</template>
