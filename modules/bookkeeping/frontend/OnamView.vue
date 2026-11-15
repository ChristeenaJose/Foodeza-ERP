<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { API_BASE } from '@framework/config'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'

const app = useAppStore()
const summary = ref<any>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const minDate = ref(DEFAULT_MIN_DATE)
const proofTypes = ref<Record<string, string>>({})
const vatRate = ref(0.21)
const tab = ref<'overview' | 'partners' | 'entries'>('overview')

const partnerForm = ref({ name: '', share_percent: '' as string | number, notes: '' })
const editingPartnerId = ref<number | null>(null)

const entryForm = ref({
  date: new Date().toISOString().slice(0, 10),
  type: 'expense' as 'income' | 'expense' | 'settlement',
  partner_id: '' as string | number,
  amount: '' as string | number,
  vat_mode: 'none' as 'including' | 'none',
  description: '',
  proof_type: 'none',
  notes: '',
})
const receipt = ref<File | null>(null)
const editingEntryId = ref<number | null>(null)

const partners = computed(() => summary.value?.partners ?? [])
const settlements = computed(() => summary.value?.settlements ?? [])
const incomeEntries = computed(() => summary.value?.income_entries ?? [])
const expenseEntries = computed(() => summary.value?.expense_entries ?? [])
const payoutEntries = computed(() => summary.value?.payout_entries ?? [])
const totals = computed(() => summary.value?.totals ?? {})
const entryFormTitle = computed(() => {
  if (editingEntryId.value) return 'Edit entry'
  if (entryForm.value.type === 'settlement') return 'Pay cash to a partner'
  return 'Add income or expense'
})
const vatPercent = computed(() => Math.round(vatRate.value * 100))
const vatPreview = computed(() => {
  const gross = Number(entryForm.value.amount) || 0
  if (entryForm.value.vat_mode !== 'including' || gross <= 0) {
    return { net: gross, vat: 0 }
  }
  const net = Math.round((gross / (1 + vatRate.value)) * 100) / 100
  const vat = Math.round((gross - net) * 100) / 100
  return { net, vat }
})
const showsVatChoice = computed(() => entryForm.value.type === 'income')
const amountLabel = computed(() => {
  if (entryForm.value.type === 'income' && entryForm.value.vat_mode === 'including') {
    return 'Amount (incl. VAT)'
  }
  return 'Amount'
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/onam/summary')
    summary.value = data
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not load Onam Köln 26 data.'
  } finally {
    loading.value = false
  }
}

function resetPartnerForm() {
  partnerForm.value = { name: '', share_percent: '', notes: '' }
  editingPartnerId.value = null
}

function editPartner(p: any) {
  editingPartnerId.value = p.id
  partnerForm.value = {
    name: p.name,
    share_percent: p.share_percent ?? '',
    notes: p.notes ?? '',
  }
  tab.value = 'partners'
}

async function savePartner() {
  saving.value = true
  try {
    const payload = {
      name: partnerForm.value.name,
      share_percent: partnerForm.value.share_percent === '' ? null : Number(partnerForm.value.share_percent),
      notes: partnerForm.value.notes || null,
      active: true,
    }
    if (editingPartnerId.value) {
      await api.put(`/onam/partners/${editingPartnerId.value}`, payload)
    } else {
      await api.post('/onam/partners', payload)
    }
    resetPartnerForm()
    await load()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not save partner.'
  } finally {
    saving.value = false
  }
}

async function removePartner(id: number) {
  if (!confirm('Remove this partner?')) return
  await api.delete(`/onam/partners/${id}`)
  await load()
}

function resetEntryForm() {
  entryForm.value = {
    date: new Date().toISOString().slice(0, 10),
    type: 'expense',
    partner_id: '',
    amount: '',
    vat_mode: 'none',
    description: '',
    proof_type: 'none',
    notes: '',
  }
  receipt.value = null
  editingEntryId.value = null
}

function setEntryType(type: 'income' | 'expense' | 'settlement') {
  entryForm.value.type = type
  if (type === 'income' && !editingEntryId.value) {
    entryForm.value.vat_mode = 'including'
  } else if (type !== 'income') {
    entryForm.value.vat_mode = 'none'
  }
}

function payPartner(s: any) {
  resetEntryForm()
  entryForm.value.type = 'settlement'
  entryForm.value.vat_mode = 'none'
  entryForm.value.partner_id = s.partner_id
  entryForm.value.amount = s.remaining > 0 ? s.remaining : ''
  entryForm.value.description = 'Settlement payout'
  tab.value = 'entries'
}

function editEntry(e: any) {
  editingEntryId.value = e.id
  entryForm.value = {
    date: String(e.date).slice(0, 10),
    type: e.type,
    partner_id: e.partner_id ?? '',
    amount: e.amount,
    vat_mode: e.vat_mode === 'including' ? 'including' : 'none',
    description: e.description ?? '',
    proof_type: e.proof_type ?? 'none',
    notes: e.notes ?? '',
  }
  receipt.value = null
  tab.value = 'entries'
}

async function saveEntry() {
  saving.value = true
  error.value = ''
  try {
    const payload: Record<string, any> = {
      date: entryForm.value.date,
      type: entryForm.value.type,
      partner_id: (entryForm.value.type === 'expense' || entryForm.value.type === 'settlement') && entryForm.value.partner_id
        ? Number(entryForm.value.partner_id)
        : null,
      amount: Number(entryForm.value.amount),
      vat_mode: entryForm.value.type === 'income' ? entryForm.value.vat_mode : 'none',
      description: entryForm.value.description || (entryForm.value.type === 'settlement' ? 'Settlement payout' : ''),
      proof_type: entryForm.value.proof_type,
      notes: entryForm.value.notes || null,
    }

    if (receipt.value) {
      const fd = new FormData()
      Object.entries(payload).forEach(([k, v]) => fd.append(k, v == null ? '' : String(v)))
      fd.append('receipt', receipt.value)
      if (editingEntryId.value) {
        fd.append('_method', 'PUT')
        await api.post(`/onam/entries/${editingEntryId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      } else {
        await api.post('/onam/entries', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      }
    } else if (editingEntryId.value) {
      await api.put(`/onam/entries/${editingEntryId.value}`, payload)
    } else {
      await api.post('/onam/entries', payload)
    }

    resetEntryForm()
    await load()
    tab.value = 'overview'
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not save entry.'
  } finally {
    saving.value = false
  }
}

async function removeEntry(id: number) {
  if (!confirm('Delete this entry?')) return
  await api.delete(`/onam/entries/${id}`)
  await load()
}

function onFile(e: Event) {
  receipt.value = (e.target as HTMLInputElement).files?.[0] ?? null
  if (receipt.value) entryForm.value.proof_type = 'uploaded'
}

function formatDate(value: unknown) {
  if (!value) return '—'
  return String(value).slice(0, 10)
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE
  proofTypes.value = cfgRes.data.expense_proof_types ?? {}
  vatRate.value = Number(cfgRes.data.vat_rate ?? 0.21)
  await load()
})
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold">Onam Köln 26</h1>
        <p class="text-sm text-muted mt-1">Separate partner bookkeeping — expenses by person, income, and cash paid to partners</p>
      </div>
      <div class="flex rounded-lg border overflow-hidden text-sm">
        <button type="button" class="px-4 py-2" :class="tab === 'overview' ? 'bg-brand text-white' : 'bg-white'" @click="tab = 'overview'">Overview</button>
        <button type="button" class="px-4 py-2 border-l" :class="tab === 'partners' ? 'bg-brand text-white' : 'bg-white'" @click="tab = 'partners'">Partners</button>
        <button type="button" class="px-4 py-2 border-l" :class="tab === 'entries' ? 'bg-brand text-white' : 'bg-white'" @click="tab = 'entries'">Add entry</button>
      </div>
    </div>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ error }}</div>
    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <template v-else-if="summary">
      <!-- OVERVIEW -->
      <div v-if="tab === 'overview'">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="text-xs text-muted uppercase">Income</div>
            <div class="text-xl font-bold text-income mt-1">{{ formatMoney(+totals.income || 0, app.currencySymbol) }}</div>
            <div class="text-xs text-muted">{{ totals.income_count || 0 }} entries</div>
            <div class="text-xs text-muted mt-1">
              Net {{ formatMoney(+totals.income_net || 0, app.currencySymbol) }}
              · VAT {{ formatMoney(+totals.income_vat || 0, app.currencySymbol) }}
            </div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="text-xs text-muted uppercase">Expenses</div>
            <div class="text-xl font-bold text-expense mt-1">{{ formatMoney(+totals.expenses || 0, app.currencySymbol) }}</div>
            <div class="text-xs text-muted">{{ totals.expense_count || 0 }} entries</div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="text-xs text-muted uppercase">Partner profit</div>
            <div class="text-xl font-bold mt-1" :class="(+totals.profit || 0) >= 0 ? 'text-income' : 'text-expense'">
              {{ formatMoney(+totals.profit || 0, app.currencySymbol) }}
            </div>
            <div class="text-xs text-muted mt-1">Net after VAT</div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="text-xs text-muted uppercase">Partners</div>
            <div class="text-xl font-bold mt-1">{{ partners.length }}</div>
          </div>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm mb-6 overflow-x-auto">
          <h2 class="font-semibold mb-2">Partner settlement</h2>
          <p class="text-xs text-muted mb-4">
            Profit share is based on <strong>net after VAT</strong>. Paying a partner cash only reduces <strong>Remaining</strong>.
            Positive remaining = still to receive · Negative = still to pay in.
          </p>
          <div v-if="settlements.length === 0" class="text-sm text-muted">Add partners first, then record who paid each expense.</div>
          <table v-else class="w-full text-sm min-w-[760px]">
            <thead class="bg-stone-50 text-muted uppercase text-xs">
              <tr>
                <th class="text-left p-3">Partner</th>
                <th class="text-right p-3">Share %</th>
                <th class="text-right p-3">Paid</th>
                <th class="text-right p-3">Fair cost</th>
                <th class="text-right p-3">Reimburse</th>
                <th class="text-right p-3">Profit share</th>
                <th class="text-right p-3">Net due</th>
                <th class="text-right p-3">Settled</th>
                <th class="text-right p-3">Remaining</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in settlements" :key="s.partner_id" class="border-t">
                <td class="p-3 font-medium">{{ s.name }}</td>
                <td class="p-3 text-right">{{ s.share_percent }}%</td>
                <td class="p-3 text-right">{{ formatMoney(s.expenses_paid, app.currencySymbol) }}</td>
                <td class="p-3 text-right">{{ formatMoney(s.fair_cost_share, app.currencySymbol) }}</td>
                <td class="p-3 text-right">{{ formatMoney(s.reimbursement, app.currencySymbol) }}</td>
                <td class="p-3 text-right">{{ formatMoney(s.profit_share, app.currencySymbol) }}</td>
                <td class="p-3 text-right">{{ formatMoney(s.net_settlement, app.currencySymbol) }}</td>
                <td class="p-3 text-right">{{ formatMoney(s.settled, app.currencySymbol) }}</td>
                <td class="p-3 text-right font-semibold" :class="s.remaining >= 0 ? 'text-income' : 'text-expense'">
                  {{ formatMoney(s.remaining, app.currencySymbol) }}
                </td>
                <td class="p-3 text-right whitespace-nowrap">
                  <button type="button" class="text-brand text-xs" @click="payPartner(s)">Pay partner</button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="totals.unassigned_expenses > 0" class="text-xs text-amber-700 mt-3">
            {{ formatMoney(+totals.unassigned_expenses, app.currencySymbol) }} expenses have no partner assigned yet.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="bg-white rounded-xl p-5 shadow-sm">
            <h2 class="font-semibold mb-3">Income</h2>
            <div v-if="incomeEntries.length === 0" class="text-sm text-muted">No income yet.</div>
            <div v-for="e in incomeEntries" :key="e.id" class="flex justify-between py-2 border-b text-sm gap-3">
              <div>
                <div>{{ formatDate(e.date) }} · {{ e.description || 'Income' }}</div>
                <div class="text-muted text-xs">
                  <template v-if="e.vat_mode === 'including'">
                    Net {{ formatMoney(+e.net_amount, app.currencySymbol) }}
                    · VAT {{ vatPercent }}% {{ formatMoney(+e.vat_amount, app.currencySymbol) }}
                  </template>
                  <template v-else>No VAT applicable</template>
                </div>
                <button class="text-brand text-xs mr-2" @click="editEntry(e)">Edit</button>
                <button class="text-red-600 text-xs" @click="removeEntry(e.id)">Delete</button>
              </div>
              <div class="text-right">
                <div class="font-medium text-income whitespace-nowrap">{{ formatMoney(+e.amount, app.currencySymbol) }}</div>
                <div class="text-xs text-muted">{{ e.vat_mode === 'including' ? 'incl. VAT' : 'no VAT' }}</div>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl p-5 shadow-sm">
            <h2 class="font-semibold mb-3">Expenses by person</h2>
            <div v-if="expenseEntries.length === 0" class="text-sm text-muted">No expenses yet.</div>
            <div v-for="e in expenseEntries" :key="e.id" class="flex justify-between py-2 border-b text-sm gap-3">
              <div>
                <div>{{ formatDate(e.date) }} · <strong>{{ e.partner?.name || 'Unassigned' }}</strong></div>
                <div class="text-muted text-xs">{{ e.description || '—' }}</div>
                <button class="text-brand text-xs mr-2" @click="editEntry(e)">Edit</button>
                <button class="text-red-600 text-xs" @click="removeEntry(e.id)">Delete</button>
                <a v-if="e.receipt_attachment_id" :href="`${API_BASE}/attachments/${e.receipt_attachment_id}/download`" target="_blank" class="text-brand text-xs ml-2">Receipt</a>
              </div>
              <div class="font-medium text-expense whitespace-nowrap">{{ formatMoney(+e.amount, app.currencySymbol) }}</div>
            </div>
          </div>
          <div class="bg-white rounded-xl p-5 shadow-sm">
            <h2 class="font-semibold mb-3">Cash paid to partners</h2>
            <div v-if="payoutEntries.length === 0" class="text-sm text-muted">No partner payouts yet. Use Pay partner so Remaining goes down without changing profit.</div>
            <div v-for="e in payoutEntries" :key="e.id" class="flex justify-between py-2 border-b text-sm gap-3">
              <div>
                <div>{{ formatDate(e.date) }} · <strong>{{ e.partner?.name || 'Unassigned' }}</strong></div>
                <div class="text-muted text-xs">{{ e.description || 'Settlement payout' }}</div>
                <button class="text-brand text-xs mr-2" @click="editEntry(e)">Edit</button>
                <button class="text-red-600 text-xs" @click="removeEntry(e.id)">Delete</button>
              </div>
              <div class="font-medium whitespace-nowrap">{{ formatMoney(+e.amount, app.currencySymbol) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- PARTNERS -->
      <div v-else-if="tab === 'partners'" class="max-w-xl">
        <form class="bg-white rounded-xl p-5 shadow-sm space-y-3 mb-6" @submit.prevent="savePartner">
          <h2 class="font-semibold">{{ editingPartnerId ? 'Edit partner' : 'Add partner' }}</h2>
          <label class="block text-sm"><span class="font-medium">Name</span>
            <input v-model="partnerForm.name" required class="mt-1 w-full border rounded-lg px-3 py-2" />
          </label>
          <label class="block text-sm"><span class="font-medium">Share % (optional)</span>
            <input v-model="partnerForm.share_percent" type="number" step="0.01" min="0" max="100" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="Leave empty for equal split" />
          </label>
          <label class="block text-sm"><span class="font-medium">Notes</span>
            <input v-model="partnerForm.notes" class="mt-1 w-full border rounded-lg px-3 py-2" />
          </label>
          <div class="flex gap-2 justify-end">
            <button v-if="editingPartnerId" type="button" class="px-3 py-2" @click="resetPartnerForm">Cancel</button>
            <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg" :disabled="saving">{{ saving ? 'Saving…' : 'Save partner' }}</button>
          </div>
        </form>
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-stone-50 text-xs uppercase text-muted"><tr><th class="text-left p-3">Name</th><th class="text-right p-3">Share %</th><th></th></tr></thead>
            <tbody>
              <tr v-for="p in partners" :key="p.id" class="border-t">
                <td class="p-3">{{ p.name }}</td>
                <td class="p-3 text-right">{{ p.share_percent != null ? p.share_percent + '%' : 'Equal' }}</td>
                <td class="p-3 text-right whitespace-nowrap">
                  <button class="text-brand text-xs mr-2" @click="editPartner(p)">Edit</button>
                  <button class="text-red-600 text-xs" @click="removePartner(p.id)">Remove</button>
                </td>
              </tr>
              <tr v-if="partners.length === 0"><td colspan="3" class="p-6 text-center text-muted">No partners yet.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ENTRIES -->
      <div v-else class="max-w-xl">
        <form class="bg-white rounded-xl p-5 shadow-sm space-y-3" @submit.prevent="saveEntry">
          <h2 class="font-semibold">{{ entryFormTitle }}</h2>
          <div class="flex rounded-lg border overflow-hidden text-sm">
            <button type="button" class="flex-1 px-3 py-2" :class="entryForm.type === 'expense' ? 'bg-brand text-white' : ''" @click="setEntryType('expense')">Expense</button>
            <button type="button" class="flex-1 px-3 py-2 border-l" :class="entryForm.type === 'income' ? 'bg-brand text-white' : ''" @click="setEntryType('income')">Income</button>
            <button type="button" class="flex-1 px-3 py-2 border-l" :class="entryForm.type === 'settlement' ? 'bg-brand text-white' : ''" @click="setEntryType('settlement')">Pay partner</button>
          </div>
          <div v-if="showsVatChoice" class="flex rounded-lg border overflow-hidden text-sm">
            <button type="button" class="flex-1 px-3 py-2" :class="entryForm.vat_mode === 'including' ? 'bg-brand text-white' : ''" @click="entryForm.vat_mode = 'including'">Including VAT</button>
            <button type="button" class="flex-1 px-3 py-2 border-l" :class="entryForm.vat_mode === 'none' ? 'bg-brand text-white' : ''" @click="entryForm.vat_mode = 'none'">No VAT applicable</button>
          </div>
          <p v-if="entryForm.type === 'settlement'" class="text-xs text-muted">
            Cash you already gave a partner. This does not change event profit — only that partner’s Remaining.
          </p>
          <p v-else-if="showsVatChoice && entryForm.vat_mode === 'including' && Number(entryForm.amount) > 0" class="text-xs text-muted">
            Net {{ formatMoney(vatPreview.net, app.currencySymbol) }}
            · VAT {{ vatPercent }}% {{ formatMoney(vatPreview.vat, app.currencySymbol) }}
            · Gross {{ formatMoney(Number(entryForm.amount) || 0, app.currencySymbol) }}
            · partners settle on the net amount
          </p>
          <p v-else-if="showsVatChoice && entryForm.vat_mode === 'none'" class="text-xs text-muted">
            Full amount is used for partner settlement. No VAT is split out.
          </p>
          <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm"><span class="font-medium">Date</span>
              <input v-model="entryForm.date" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" />
            </label>
            <label class="block text-sm"><span class="font-medium">{{ amountLabel }}</span>
              <input v-model="entryForm.amount" type="number" step="0.01" required class="mt-1 w-full border rounded-lg px-3 py-2" />
            </label>
          </div>
          <label v-if="entryForm.type === 'expense'" class="block text-sm"><span class="font-medium">Who paid?</span>
            <select v-model="entryForm.partner_id" class="mt-1 w-full border rounded-lg px-3 py-2">
              <option value="">Unassigned</option>
              <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </label>
          <label v-if="entryForm.type === 'settlement'" class="block text-sm"><span class="font-medium">Partner you paid</span>
            <select v-model="entryForm.partner_id" required class="mt-1 w-full border rounded-lg px-3 py-2">
              <option value="" disabled>Select partner</option>
              <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </label>
          <label class="block text-sm"><span class="font-medium">Description</span>
            <input v-model="entryForm.description" class="mt-1 w-full border rounded-lg px-3 py-2" :placeholder="entryForm.type === 'settlement' ? 'Settlement payout' : ''" />
          </label>
          <label v-if="entryForm.type !== 'settlement'" class="block text-sm"><span class="font-medium">Proof</span>
            <select v-model="entryForm.proof_type" class="mt-1 w-full border rounded-lg px-3 py-2">
              <option v-for="(label, key) in proofTypes" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
          <label v-if="entryForm.type !== 'settlement'" class="block text-sm"><span class="font-medium">Receipt (optional)</span>
            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full" @change="onFile" />
          </label>
          <label class="block text-sm"><span class="font-medium">Notes</span>
            <input v-model="entryForm.notes" class="mt-1 w-full border rounded-lg px-3 py-2" />
          </label>
          <div class="flex gap-2 justify-end">
            <button v-if="editingEntryId" type="button" class="px-3 py-2" @click="resetEntryForm">Cancel</button>
            <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </template>
  </div>
</template>
