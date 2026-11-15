<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { api, formatMoney, downloadTaxReportPdf } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { DEFAULT_MIN_DATE, yearsFrom } from '@framework/utils/dates'

const app = useAppStore()
const report = ref<any>(null)
const year = ref('2025')
const years = ref(yearsFrom())
const loading = ref(false)
const downloading = ref(false)
const error = ref('')
const proofLabels = ref<Record<string, string>>({})

const incomeRows = computed(() => {
  const annual = report.value?.income_summary?.annual ?? {}
  return Object.entries(annual)
    .filter(([, row]: any) => row.count > 0)
    .map(([label, row]: any) => ({ label, ...row }))
})

const expenseRows = computed(() => {
  const annual = report.value?.operating_expenses?.annual ?? {}
  return Object.entries(annual)
    .filter(([, row]: any) => row.count > 0)
    .map(([label, row]: any) => ({ label, ...row }))
})

const incomeItems = computed(() => report.value?.income_summary?.items ?? [])
const expenseItems = computed(() => report.value?.operating_expenses?.items ?? [])
const assetItems = computed(() => report.value?.fixed_assets?.items ?? [])
const startupItems = computed(() => report.value?.startup_costs?.items ?? [])

const sales = computed(() =>
  report.value?.executive_summary?.total_sales_received
  ?? report.value?.executive_summary?.total_revenue_gross
  ?? 0
)

const operatingPaid = computed(() =>
  report.value?.executive_summary?.total_operating_paid
  ?? report.value?.operating_expenses?.total_gross
  ?? 0
)

function proofLabel(item: any) {
  const key = item.proof_type ?? 'none'
  const base = proofLabels.value[key] ?? key
  return item.has_receipt ? `${base} ✓` : base
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/reports/tax', { params: { year: year.value } })
    report.value = data
  } catch (e: any) {
    report.value = null
    error.value = e?.response?.data?.message ?? 'Could not load report.'
  } finally {
    loading.value = false
  }
}

async function downloadPdf() {
  downloading.value = true
  try {
    await downloadTaxReportPdf(year.value)
  } finally {
    downloading.value = false
  }
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  proofLabels.value = cfgRes.data.expense_proof_types ?? {}
  await load()
})
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold">Steuerberater-Paket</h1>
        <p class="text-sm text-muted mt-1">Full EÜR journal for Kleinunternehmer — every booking listed</p>
      </div>
      <div class="flex gap-2 items-center flex-wrap">
        <label class="text-sm">
          Year
          <select v-model="year" class="ml-2 border rounded-lg px-3 py-2" @change="load">
            <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
          </select>
        </label>
        <button
          type="button"
          class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60"
          :disabled="!report || downloading"
          @click="downloadPdf"
        >
          {{ downloading ? 'Preparing…' : 'Download full PDF' }}
        </button>
        <RouterLink to="/bookkeeping/reports" class="bg-stone-100 px-4 py-2 rounded-lg">P&amp;L Reports</RouterLink>
      </div>
    </div>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ error }}</div>
    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <template v-else-if="report">
      <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-6 text-sm text-emerald-900">
        <strong>{{ report.executive_summary.business_name }}</strong>
        · Year {{ year }}
        · Einzelunternehmen
        · <strong>Kleinunternehmer (§ 19 UStG) — no VAT on sales</strong>
        <p class="mt-2 text-xs text-emerald-800">
          Full booking list: {{ incomeItems.length }} income · {{ expenseItems.length }} operating ·
          {{ assetItems.length }} assets · {{ startupItems.length }} startup
        </p>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Sales received</div>
          <div class="text-xl font-bold text-income mt-1">{{ formatMoney(sales, app.currencySymbol) }}</div>
          <div class="text-xs text-muted mt-1">{{ incomeItems.length }} entries</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Operating paid</div>
          <div class="text-xl font-bold text-expense mt-1">{{ formatMoney(operatingPaid, app.currencySymbol) }}</div>
          <div class="text-xs text-muted mt-1">{{ expenseItems.length }} entries</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Big purchases</div>
          <div class="text-xl font-bold mt-1">{{ formatMoney((report.executive_summary.total_fixed_assets_paid ?? report.fixed_assets.total_gross) + (report.executive_summary.total_startup_paid ?? report.startup_costs.total_gross), app.currencySymbol) }}</div>
          <div class="text-xs text-muted mt-1">{{ assetItems.length + startupItems.length }} entries</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Simple profit</div>
          <div
            class="text-xl font-bold mt-1"
            :class="report.executive_summary.net_profit_or_loss >= 0 ? 'text-income' : 'text-expense'"
          >
            {{ formatMoney(report.executive_summary.net_profit_or_loss, app.currencySymbol) }}
          </div>
          <div class="text-xs text-muted mt-1">Sales − operating paid</div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl p-5 shadow-sm">
          <h2 class="font-semibold mb-1">Totals by service</h2>
          <div v-for="row in incomeRows" :key="row.label" class="flex justify-between py-2 border-b text-sm">
            <span>{{ row.label }} <span class="text-muted">({{ row.count }})</span></span>
            <span class="font-medium">{{ formatMoney(row.gross, app.currencySymbol) }}</span>
          </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm">
          <h2 class="font-semibold mb-1">Totals by category</h2>
          <div v-for="row in expenseRows" :key="row.label" class="flex justify-between py-2 border-b text-sm">
            <span>{{ row.label }} <span class="text-muted">({{ row.count }})</span></span>
            <span class="font-medium">{{ formatMoney(row.gross, app.currencySymbol) }}</span>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl p-5 shadow-sm mb-6 overflow-x-auto">
        <h2 class="font-semibold mb-1">1. All income entries</h2>
        <p class="text-xs text-muted mb-3">Every payment received · no VAT</p>
        <table class="w-full text-sm min-w-[720px]">
          <thead class="text-muted text-xs uppercase">
            <tr>
              <th class="text-left py-2">Date</th>
              <th class="text-left">Customer</th>
              <th class="text-left">Service</th>
              <th class="text-left">Note</th>
              <th class="text-left">Invoice</th>
              <th class="text-left">Payment</th>
              <th class="text-right">Received</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in incomeItems" :key="'i'+item.id" class="border-t">
              <td class="py-2 whitespace-nowrap">{{ item.date }}</td>
              <td>{{ item.customer }}</td>
              <td>{{ item.service_type }}</td>
              <td class="text-muted">{{ item.description }}</td>
              <td>{{ item.invoice_number }}</td>
              <td>{{ item.payment_method }}</td>
              <td class="text-right font-medium whitespace-nowrap">{{ formatMoney(item.amount, app.currencySymbol) }}</td>
            </tr>
            <tr v-if="!incomeItems.length">
              <td colspan="7" class="py-4 text-center text-muted">No income entries</td>
            </tr>
            <tr class="border-t font-semibold">
              <td colspan="6" class="py-2">Total</td>
              <td class="text-right">{{ formatMoney(report.income_summary.total_gross, app.currencySymbol) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white rounded-xl p-5 shadow-sm mb-6 overflow-x-auto">
        <h2 class="font-semibold mb-1">2. All operating expenses</h2>
        <p class="text-xs text-muted mb-3">Amount paid (incl. VAT on purchase bills)</p>
        <table class="w-full text-sm min-w-[720px]">
          <thead class="text-muted text-xs uppercase">
            <tr>
              <th class="text-left py-2">Date</th>
              <th class="text-left">Supplier</th>
              <th class="text-left">Category</th>
              <th class="text-left">Description</th>
              <th class="text-left">Proof</th>
              <th class="text-right">Paid</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in expenseItems" :key="'e'+item.id" class="border-t">
              <td class="py-2 whitespace-nowrap">{{ item.date }}</td>
              <td>{{ item.supplier }}</td>
              <td>{{ item.category }}</td>
              <td>{{ item.description }}</td>
              <td class="text-muted text-xs">{{ proofLabel(item) }}</td>
              <td class="text-right font-medium whitespace-nowrap">{{ formatMoney(item.amount_gross, app.currencySymbol) }}</td>
            </tr>
            <tr v-if="!expenseItems.length">
              <td colspan="6" class="py-4 text-center text-muted">No operating expenses</td>
            </tr>
            <tr class="border-t font-semibold">
              <td colspan="5" class="py-2">Total</td>
              <td class="text-right">{{ formatMoney(report.operating_expenses.total_gross, app.currencySymbol) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white rounded-xl p-5 shadow-sm mb-6 overflow-x-auto">
        <h2 class="font-semibold mb-1">3. Fixed assets (separate / AfA)</h2>
        <p class="text-xs text-muted mb-3">Not deducted in simple profit</p>
        <table class="w-full text-sm min-w-[720px]">
          <thead class="text-muted text-xs uppercase">
            <tr>
              <th class="text-left py-2">Date</th>
              <th class="text-left">Supplier</th>
              <th class="text-left">Type</th>
              <th class="text-left">Description</th>
              <th class="text-left">AfA note</th>
              <th class="text-right">Paid</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in assetItems" :key="'a'+item.id" class="border-t">
              <td class="py-2 whitespace-nowrap">{{ item.date }}</td>
              <td>{{ item.supplier }}</td>
              <td>{{ item.asset_category }}</td>
              <td>{{ item.description }}</td>
              <td class="text-xs text-muted">{{ item.afa_suggestion }}</td>
              <td class="text-right font-medium whitespace-nowrap">{{ formatMoney(item.amount_gross, app.currencySymbol) }}</td>
            </tr>
            <tr v-if="!assetItems.length">
              <td colspan="6" class="py-4 text-center text-muted">None</td>
            </tr>
            <tr class="border-t font-semibold">
              <td colspan="5" class="py-2">Total</td>
              <td class="text-right">{{ formatMoney(report.fixed_assets.total_gross, app.currencySymbol) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white rounded-xl p-5 shadow-sm mb-6 overflow-x-auto">
        <h2 class="font-semibold mb-1">4. Startup / registration costs</h2>
        <table class="w-full text-sm min-w-[640px]">
          <thead class="text-muted text-xs uppercase">
            <tr>
              <th class="text-left py-2">Date</th>
              <th class="text-left">Supplier</th>
              <th class="text-left">Type</th>
              <th class="text-left">Description</th>
              <th class="text-right">Paid</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in startupItems" :key="'s'+item.id" class="border-t">
              <td class="py-2 whitespace-nowrap">{{ item.date }}</td>
              <td>{{ item.supplier }}</td>
              <td>{{ item.startup_label }}</td>
              <td>{{ item.description }}</td>
              <td class="text-right font-medium whitespace-nowrap">{{ formatMoney(item.amount_gross, app.currencySymbol) }}</td>
            </tr>
            <tr v-if="!startupItems.length">
              <td colspan="5" class="py-4 text-center text-muted">None</td>
            </tr>
            <tr class="border-t font-semibold">
              <td colspan="4" class="py-2">Total</td>
              <td class="text-right">{{ formatMoney(report.startup_costs.total_gross, app.currencySymbol) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white rounded-xl p-5 shadow-sm mb-6">
        <h2 class="font-semibold mb-3">5. Result (EÜR)</h2>
        <div class="space-y-2 text-sm max-w-md">
          <div class="flex justify-between border-b py-2">
            <span>Sales received</span>
            <span>{{ formatMoney(sales, app.currencySymbol) }}</span>
          </div>
          <div class="flex justify-between border-b py-2">
            <span>Operating costs paid</span>
            <span>− {{ formatMoney(operatingPaid, app.currencySymbol) }}</span>
          </div>
          <div class="flex justify-between py-2 font-bold text-base">
            <span>Profit / loss</span>
            <span :class="report.executive_summary.net_profit_or_loss >= 0 ? 'text-income' : 'text-expense'">
              {{ formatMoney(report.executive_summary.net_profit_or_loss, app.currencySymbol) }}
            </span>
          </div>
        </div>
      </div>

      <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm">
        <h2 class="font-semibold mb-2">Notes for your Steuerberater</h2>
        <ul class="list-disc ml-5 space-y-1 text-stone-700">
          <li v-for="(n, i) in (report.advisor_package?.notes || [])" :key="i">{{ n }}</li>
          <li>Full booking journal from Foodeza — not an official tax return.</li>
        </ul>
      </div>
    </template>
  </div>
</template>
