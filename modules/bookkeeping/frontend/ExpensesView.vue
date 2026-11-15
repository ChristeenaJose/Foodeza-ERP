<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { formatMoney, expenseGross, proofTypeLabel } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { API_BASE } from '@framework/config'
import { useListPage } from '@framework/composables/useListPage'
import { DEFAULT_MIN_DATE, minMonthFromDate, yearsFrom, startOfMonth, endOfMonth, startOfYear, endOfYear } from '@framework/utils/dates'
import { api } from '@framework/api/client'
import { buildExpenseListQuery, parseExpenseListQuery } from './listQuery'

const route = useRoute()
const router = useRouter()
const app = useAppStore()
const { rows, loading, error, load } = useListPage<any>('/expenses')
const period = ref<'month' | 'year'>('month')
const month = ref(new Date().toISOString().slice(0, 7))
const year = ref(String(new Date().getFullYear()))
const minMonth = ref(minMonthFromDate())
const years = ref(yearsFrom())
const categories = ref<Array<{ id: number; name: string }>>([])
const categoryId = ref<string>('')
type SortKey = 'date' | 'supplier' | 'category'
const sortBy = ref<SortKey>('date')
const sortDir = ref<'asc' | 'desc'>('desc')

type FixedExpenseRow = {
  id: number
  name: string
  amount: number | string
  section: string
  supplier_name?: string | null
  day_of_month?: number
  notes?: string | null
  active: boolean
  category?: { id: number; name: string } | null
  category_id: number
  proof_type?: string
  imported_this_month?: boolean
}

const fixedExpenses = ref<FixedExpenseRow[]>([])
const fixedOpen = ref(true)
const fixedError = ref('')
const editingFixed = ref<FixedExpenseRow | null>(null)
const editForm = ref({ name: '', amount: '', supplier_name: '', day_of_month: 1, notes: '' })
const importBusy = ref(false)
const importMsg = ref('')

const listQuery = computed(() => buildExpenseListQuery({
  period: period.value,
  month: month.value,
  year: year.value,
  category: categoryId.value,
  sort: sortBy.value,
  dir: sortDir.value,
}))

const insuranceFixed = computed(() =>
  fixedExpenses.value.filter((f) => f.section === 'insurance' && f.active)
)

const otherFixed = computed(() =>
  fixedExpenses.value.filter((f) => f.section !== 'insurance' && f.active)
)

const importMonth = computed(() =>
  period.value === 'month' ? month.value : `${year.value}-${String(new Date().getMonth() + 1).padStart(2, '0')}`
)

const pendingInsuranceIds = computed(() =>
  insuranceFixed.value.filter((f) => !f.imported_this_month).map((f) => f.id)
)

const fixedExpensesTotal = computed(() =>
  fixedExpenses.value
    .filter((f) => f.active)
    .reduce((sum, f) => sum + (Number(f.amount) || 0), 0)
)

function isImported(f: FixedExpenseRow) {
  return !!f.imported_this_month
}

function applyRouteQuery() {
  const parsed = parseExpenseListQuery(route.query)
  period.value = parsed.period
  month.value = parsed.month
  year.value = parsed.year
  categoryId.value = parsed.category
  sortBy.value = (['date', 'supplier', 'category'].includes(parsed.sort) ? parsed.sort : 'date') as SortKey
  sortDir.value = parsed.dir
}

function syncRouteQuery() {
  router.replace({ query: listQuery.value })
}

async function loadFiltered() {
  syncRouteQuery()
  const params: Record<string, any> =
    period.value === 'year'
      ? { from: startOfYear(year.value), to: endOfYear(year.value) }
      : { from: startOfMonth(month.value), to: endOfMonth(month.value) }
  if (categoryId.value) params.category_id = Number(categoryId.value)
  await load(params)
  await loadFixed()
}

async function loadFixed() {
  fixedError.value = ''
  try {
    const { data } = await api.get('/fixed-expenses', {
      params: { active: 1, month: importMonth.value },
    })
    fixedExpenses.value = Array.isArray(data) ? data : []
  } catch (e: any) {
    fixedError.value = e?.response?.data?.message ?? 'Could not load fixed expenses'
    fixedExpenses.value = []
  }
}

const totalsByCategory = computed(() => {
  const map = new Map<string, number>()
  for (const r of rows.value ?? []) {
    const name = r?.category?.name ?? '—'
    const gross = expenseGross(r?.amount, r?.vat_amount)
    map.set(name, (map.get(name) ?? 0) + gross)
  }
  return Array.from(map.entries())
    .map(([name, total]) => ({ name, total }))
    .sort((a, b) => b.total - a.total)
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
    } else if (sortBy.value === 'supplier') {
      left = a.supplier?.name ?? ''
      right = b.supplier?.name ?? ''
    } else {
      left = a.category?.name ?? ''
      right = b.category?.name ?? ''
    }

    return left.localeCompare(right, undefined, { sensitivity: 'base' }) * dir
  })

  return list
})

function toggleSort(key: SortKey) {
  if (sortBy.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    return
  }

  sortBy.value = key
  sortDir.value = key === 'date' ? 'desc' : 'asc'
}

function sortIndicator(key: SortKey) {
  if (sortBy.value !== key) return ''
  return sortDir.value === 'asc' ? ' ↑' : ' ↓'
}

function startEdit(row: FixedExpenseRow) {
  editingFixed.value = row
  editForm.value = {
    name: row.name,
    amount: String(row.amount),
    supplier_name: row.supplier_name ?? '',
    day_of_month: row.day_of_month ?? 1,
    notes: row.notes ?? '',
  }
}

function cancelEdit() {
  editingFixed.value = null
}

async function saveEdit() {
  if (!editingFixed.value) return
  await api.put(`/fixed-expenses/${editingFixed.value.id}`, {
    name: editForm.value.name,
    amount: Number(editForm.value.amount),
    supplier_name: editForm.value.supplier_name || null,
    day_of_month: Number(editForm.value.day_of_month) || 1,
    notes: editForm.value.notes || null,
    category_id: editingFixed.value.category_id,
    section: editingFixed.value.section,
    proof_type: editingFixed.value.proof_type || 'bank_statement',
    active: true,
  })
  editingFixed.value = null
  await loadFixed()
}

async function importFixed(ids: number[]) {
  const pending = ids.filter((id) => {
    const row = fixedExpenses.value.find((f) => f.id === id)
    return row && !row.imported_this_month
  })
  if (!pending.length) {
    importMsg.value = `Already imported for ${importMonth.value}`
    return
  }
  importBusy.value = true
  importMsg.value = ''
  try {
    const { data } = await api.post('/fixed-expenses/import', {
      ids: pending,
      month: importMonth.value,
    })
    const n = data.count ?? 0
    importMsg.value = n
      ? `Imported ${n} into ${importMonth.value}`
      : `Already imported for ${importMonth.value}`
    await loadFiltered()
  } catch (e: any) {
    importMsg.value = e?.response?.data?.message ?? 'Import failed'
  } finally {
    importBusy.value = false
  }
}

onMounted(async () => {
  applyRouteQuery()
  const cfgRes = await api.get('/config')
  minMonth.value = minMonthFromDate(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  const catsRes = await api.get('/expenses/categories')
  categories.value = Array.isArray(catsRes.data) ? catsRes.data : (catsRes.data.categories ?? [])
  await loadFiltered()
})

async function remove(id: number) {
  if (!confirm('Delete?')) return
  await api.delete(`/expenses/${id}`)
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
      <h1 class="text-2xl font-bold">Expenses</h1>
      <RouterLink to="/bookkeeping/expenses/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ Record Expense</RouterLink>
    </div>

    <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
      <div class="flex rounded-lg border overflow-hidden text-sm">
        <button type="button" class="px-4 py-2" :class="period === 'month' ? 'bg-brand text-white' : 'bg-white text-stone-700'" @click="period='month'; loadFiltered()">Monthly</button>
        <button type="button" class="px-4 py-2 border-l" :class="period === 'year' ? 'bg-brand text-white' : 'bg-white text-stone-700'" @click="period='year'; loadFiltered()">Yearly</button>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <select v-model="categoryId" class="border rounded-lg px-3 py-2" @change="loadFiltered">
          <option value="">All categories</option>
          <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>
        <input v-if="period === 'month'" v-model="month" type="month" :min="minMonth" class="border rounded-lg px-3 py-2" @change="loadFiltered" />
        <select v-else v-model="year" class="border rounded-lg px-3 py-2" @change="loadFiltered">
          <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
        </select>
      </div>
    </div>

    <section class="bg-white rounded-xl shadow-sm overflow-hidden mb-6 border border-stone-200">
      <button
        type="button"
        class="w-full px-4 py-3 bg-stone-50 flex justify-between items-center text-left hover:bg-stone-100 gap-3"
        @click="fixedOpen = !fixedOpen"
      >
        <div class="min-w-0">
          <h2 class="font-semibold text-sm">Fixed expenses</h2>
          <p class="text-xs text-muted">Recurring costs — import into the selected month</p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <span class="font-semibold text-expense text-sm tabular-nums">
            {{ formatMoney(fixedExpensesTotal, app.currencySymbol) }}
          </span>
          <span class="text-stone-400 text-xs">{{ fixedOpen ? '▾' : '▸' }}</span>
        </div>
      </button>

      <div v-if="fixedOpen" class="p-4 space-y-4">
        <p v-if="fixedError" class="text-sm text-red-600">{{ fixedError }}</p>

        <div>
          <div class="flex justify-between items-center mb-2 gap-2 flex-wrap">
            <h3 class="text-xs uppercase tracking-wide text-muted font-semibold">Insurance</h3>
            <button
              v-if="pendingInsuranceIds.length"
              type="button"
              class="text-xs bg-brand text-white px-3 py-1.5 rounded-lg disabled:opacity-50"
              :disabled="importBusy"
              @click="importFixed(pendingInsuranceIds)"
            >
              {{ importBusy ? 'Importing…' : `Import to ${importMonth}` }}
            </button>
            <span v-else-if="insuranceFixed.length" class="text-xs text-emerald-700 bg-emerald-50 px-2 py-1 rounded-full">
              Imported for {{ importMonth }}
            </span>
          </div>

          <div v-if="!insuranceFixed.length && !fixedError" class="text-sm text-muted py-2">No insurance fixed expenses yet.</div>

          <div v-for="f in insuranceFixed" :key="f.id" class="border rounded-lg p-3 mb-2">
            <div v-if="editingFixed?.id !== f.id" class="flex justify-between items-center gap-3 flex-wrap">
              <div>
                <div class="font-medium text-sm">{{ f.name }}</div>
                <div class="text-xs text-muted">
                  {{ f.category?.name ?? 'Insurance' }}
                  <span v-if="f.supplier_name"> · {{ f.supplier_name }}</span>
                  · day {{ f.day_of_month ?? 1 }}
                </div>
              </div>
              <div class="flex items-center gap-3">
                <span class="font-semibold text-expense">{{ formatMoney(+f.amount, app.currencySymbol) }}</span>
                <button type="button" class="text-brand text-xs" @click="startEdit(f)">Edit</button>
                <button
                  v-if="!isImported(f)"
                  type="button"
                  class="text-xs border px-2 py-1 rounded-lg"
                  :disabled="importBusy"
                  @click="importFixed([f.id])"
                >Import</button>
                <span v-else class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">Imported</span>
              </div>
            </div>
            <form v-else class="grid sm:grid-cols-2 gap-2 text-sm" @submit.prevent="saveEdit">
              <label class="block">Name<input v-model="editForm.name" required class="mt-1 w-full border rounded-lg px-2 py-1.5" /></label>
              <label class="block">Amount (gross)<input v-model="editForm.amount" type="number" step="0.01" min="0.01" required class="mt-1 w-full border rounded-lg px-2 py-1.5" /></label>
              <label class="block">Supplier<input v-model="editForm.supplier_name" class="mt-1 w-full border rounded-lg px-2 py-1.5" /></label>
              <label class="block">Day of month<input v-model.number="editForm.day_of_month" type="number" min="1" max="28" class="mt-1 w-full border rounded-lg px-2 py-1.5" /></label>
              <label class="block sm:col-span-2">Notes<input v-model="editForm.notes" class="mt-1 w-full border rounded-lg px-2 py-1.5" /></label>
              <div class="sm:col-span-2 flex gap-2 justify-end">
                <button type="button" class="px-3 py-1.5 text-xs" @click="cancelEdit">Cancel</button>
                <button type="submit" class="bg-brand text-white px-3 py-1.5 rounded-lg text-xs">Save</button>
              </div>
            </form>
          </div>
        </div>

        <div v-if="otherFixed.length">
          <h3 class="text-xs uppercase tracking-wide text-muted font-semibold mb-2">Other fixed</h3>
          <div v-for="f in otherFixed" :key="f.id" class="flex justify-between items-center border rounded-lg p-3 mb-2 text-sm">
            <div>
              <div class="font-medium">{{ f.name }}</div>
              <div class="text-xs text-muted">{{ f.category?.name }}</div>
            </div>
            <div class="flex items-center gap-3">
              <span class="font-semibold text-expense">{{ formatMoney(+f.amount, app.currencySymbol) }}</span>
              <button type="button" class="text-brand text-xs" @click="startEdit(f)">Edit</button>
              <button
                v-if="!isImported(f)"
                type="button"
                class="text-xs border px-2 py-1 rounded-lg"
                :disabled="importBusy"
                @click="importFixed([f.id])"
              >Import</button>
              <span v-else class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">Imported</span>
            </div>
          </div>
        </div>

        <p v-if="importMsg" class="text-xs text-muted">{{ importMsg }}</p>
      </div>
    </section>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm flex justify-between items-center">
      <span>{{ error }}</span>
      <button class="underline font-medium" @click="loadFiltered">Retry</button>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <div
      v-if="!loading && (totalsByCategory.length > 1 || (totalsByCategory.length === 1 && !categoryId))"
      class="mb-4 flex flex-wrap gap-2"
    >
      <div
        v-for="t in totalsByCategory"
        :key="t.name"
        class="bg-stone-50 border text-stone-700 px-3 py-2 rounded-lg text-xs flex items-center gap-2"
      >
        <span class="font-medium">{{ t.name }}</span>
        <span class="text-stone-500">{{ formatMoney(t.total, app.currencySymbol) }}</span>
      </div>
    </div>

    <div v-if="!loading" class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted uppercase text-xs">
          <tr>
            <th class="text-left p-3">
              <button type="button" class="inline-flex items-center gap-1 hover:text-stone-900" @click="toggleSort('date')">
                Date<span class="normal-case">{{ sortIndicator('date') }}</span>
              </button>
            </th>
            <th class="text-left p-3">
              <button type="button" class="inline-flex items-center gap-1 hover:text-stone-900" @click="toggleSort('supplier')">
                Supplier<span class="normal-case">{{ sortIndicator('supplier') }}</span>
              </button>
            </th>
            <th class="text-left p-3">
              <button type="button" class="inline-flex items-center gap-1 hover:text-stone-900" @click="toggleSort('category')">
                Category<span class="normal-case">{{ sortIndicator('category') }}</span>
              </button>
            </th>
            <th class="text-left p-3">Proof</th>
            <th class="text-right p-3">Paid (incl. VAT)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="sortedRows.length === 0">
            <td colspan="6" class="p-6 text-center text-muted">No expense records yet.</td>
          </tr>
          <tr v-for="r in sortedRows" :key="r.id" class="border-t">
            <td class="p-3">{{ formatDate(r.date) }}</td>
            <td class="p-3">{{ r.supplier?.name ?? '—' }}</td>
            <td class="p-3"><span class="bg-red-50 text-red-700 px-2 py-0.5 rounded-full text-xs">{{ r.category?.name }}</span></td>
            <td class="p-3">
              <a v-if="r.receipt_attachment_id" :href="`${API_BASE}/attachments/${r.receipt_attachment_id}/download`" target="_blank" class="text-brand text-xs">{{ proofTypeLabel('uploaded') }}</a>
              <span
                v-else
                class="text-xs px-2 py-0.5 rounded-full"
                :class="r.proof_type === 'none' ? 'bg-stone-100 text-stone-500' : 'bg-amber-50 text-amber-800'"
              >
                {{ proofTypeLabel(r.proof_type) }}
              </span>
            </td>
            <td class="p-3 text-right text-expense font-medium">{{ formatMoney(expenseGross(+r.amount, r.vat_amount), app.currencySymbol) }}</td>
            <td class="p-3 whitespace-nowrap">
              <RouterLink :to="{ path: `/bookkeeping/expenses/${r.id}/edit`, query: listQuery }" class="text-brand text-xs mr-3">Edit</RouterLink>
              <button class="text-red-600 text-xs" @click="remove(r.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
