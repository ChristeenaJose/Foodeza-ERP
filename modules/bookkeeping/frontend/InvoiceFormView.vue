<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'

import { DEFAULT_MIN_DATE } from '@framework/utils/dates'

const router = useRouter()
const app = useAppStore()
const config = ref<any>({ records_start_date: DEFAULT_MIN_DATE })
const saving = ref(false)

const form = ref({
  invoice_number: '',
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  customer_address: '',
  date: new Date().toISOString().slice(0, 10),
  due_date: '',
  service_type: '',
  notes: '',
})

const items = ref([
  { description: '', quantity: 1, unit_price: '' as string | number },
])

const subtotal = computed(() =>
  items.value.reduce((sum, row) => {
    const qty = Number(row.quantity) || 0
    const price = Number(row.unit_price) || 0
    return sum + qty * price
  }, 0)
)

const vatAmount = computed(() => {
  const rate = config.value.vat_rate ?? 0.21
  return subtotal.value * rate
})

const total = computed(() => subtotal.value + vatAmount.value)
const minDate = computed(() => config.value.records_start_date ?? DEFAULT_MIN_DATE)

async function refreshInvoiceNumber() {
  const numRes = await api.get('/invoices/peek-number', { params: { date: form.value.date } })
  form.value.invoice_number = numRes.data.number
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  config.value = cfgRes.data
  form.value.service_type = cfgRes.data.services?.[0] ?? ''
  await refreshInvoiceNumber()

  const days = cfgRes.data.payment_terms_days ?? 14
  const due = new Date(form.value.date)
  due.setDate(due.getDate() + days)
  form.value.due_date = due.toISOString().slice(0, 10)
})

watch(() => form.value.date, refreshInvoiceNumber)

function addLine() {
  items.value.push({ description: '', quantity: 1, unit_price: '' })
}

function removeLine(index: number) {
  if (items.value.length > 1) items.value.splice(index, 1)
}

async function submit() {
  saving.value = true
  try {
    const payload = {
      ...form.value,
      items: items.value
        .filter((r) => r.description.trim() && Number(r.unit_price) > 0)
        .map((r) => ({
          description: r.description.trim(),
          quantity: Number(r.quantity) || 1,
          unit_price: Number(r.unit_price),
        })),
    }
    const { data } = await api.post('/invoices', payload)
    router.push(`/bookkeeping/invoices/${data.id}`)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-2xl font-bold mb-6">Create Invoice</h1>
    <form class="bg-white rounded-xl p-6 shadow-sm space-y-5" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-4">
        <label class="block">
          <span class="text-sm font-medium">Invoice #</span>
          <input v-model="form.invoice_number" class="mt-1 w-full border rounded-lg px-3 py-2" />
        </label>
        <label class="block">
          <span class="text-sm font-medium">Service type</span>
          <select v-model="form.service_type" required class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="s in config.services" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>
      </div>

      <fieldset class="border rounded-lg p-4 space-y-3">
        <legend class="text-sm font-medium px-1">Customer</legend>
        <label class="block">
          <span class="text-sm text-muted">Name</span>
          <input v-model="form.customer_name" required class="mt-1 w-full border rounded-lg px-3 py-2" />
        </label>
        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="text-sm text-muted">Email</span>
            <input v-model="form.customer_email" type="email" class="mt-1 w-full border rounded-lg px-3 py-2" />
          </label>
          <label class="block">
            <span class="text-sm text-muted">Phone</span>
            <input v-model="form.customer_phone" class="mt-1 w-full border rounded-lg px-3 py-2" />
          </label>
        </div>
        <label class="block">
          <span class="text-sm text-muted">Address</span>
          <textarea v-model="form.customer_address" rows="2" class="mt-1 w-full border rounded-lg px-3 py-2" />
        </label>
      </fieldset>

      <div class="grid grid-cols-2 gap-4">
        <label class="block">
          <span class="text-sm font-medium">Invoice date</span>
          <input v-model="form.date" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" />
        </label>
        <label class="block">
          <span class="text-sm font-medium">Due date</span>
          <input v-model="form.due_date" type="date" :min="minDate" class="mt-1 w-full border rounded-lg px-3 py-2" />
        </label>
      </div>

      <div>
        <div class="flex justify-between items-center mb-2">
          <span class="text-sm font-medium">Line items</span>
          <button type="button" class="text-sm text-brand font-medium" @click="addLine">+ Add line</button>
        </div>
        <div class="border rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-stone-50 text-muted text-xs uppercase">
              <tr>
                <th class="text-left p-2">Description</th>
                <th class="text-right p-2 w-20">Qty</th>
                <th class="text-right p-2 w-28">Unit price</th>
                <th class="p-2 w-8" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in items" :key="i" class="border-t">
                <td class="p-2">
                  <input v-model="row.description" required placeholder="e.g. Corporate lunch — 50 persons" class="w-full border rounded px-2 py-1" />
                </td>
                <td class="p-2">
                  <input v-model.number="row.quantity" type="number" min="0.01" step="0.01" class="w-full border rounded px-2 py-1 text-right" />
                </td>
                <td class="p-2">
                  <input v-model="row.unit_price" type="number" min="0.01" step="0.01" required class="w-full border rounded px-2 py-1 text-right" />
                </td>
                <td class="p-2 text-center">
                  <button v-if="items.length > 1" type="button" class="text-muted hover:text-red-600" @click="removeLine(i)">×</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="flex justify-end">
        <dl class="text-sm space-y-1 w-56">
          <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd>{{ formatMoney(subtotal, app.currencySymbol) }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">VAT (21%)</dt><dd>{{ formatMoney(vatAmount, app.currencySymbol) }}</dd></div>
          <div class="flex justify-between font-bold text-brand text-base border-t pt-2"><dt>Total</dt><dd>{{ formatMoney(total, app.currencySymbol) }}</dd></div>
        </dl>
      </div>

      <label class="block">
        <span class="text-sm font-medium">Notes (optional)</span>
        <textarea v-model="form.notes" rows="2" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="Payment terms, delivery details…" />
      </label>

      <div class="flex gap-3 justify-end pt-2">
        <RouterLink to="/bookkeeping/invoices" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" :disabled="saving" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60">
          {{ saving ? 'Creating…' : 'Create & generate PDF' }}
        </button>
      </div>
    </form>
  </div>
</template>
