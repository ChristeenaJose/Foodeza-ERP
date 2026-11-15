<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, formatMoney, downloadInvoicePdf } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'

import { DEFAULT_MIN_DATE } from '@framework/utils/dates'

const route = useRoute()
const router = useRouter()
const app = useAppStore()
const invoice = ref<any>(null)
const config = ref<any>({ records_start_date: DEFAULT_MIN_DATE })
const loading = ref(true)
const saving = ref(false)
const pdfLoading = ref(false)
const showPayModal = ref(false)
const payForm = ref({
  payment_method: 'bank_transfer',
  date: new Date().toISOString().slice(0, 10),
  reference: '',
})

onMounted(async () => {
  const [invoiceRes, configRes] = await Promise.all([
    api.get(`/invoices/${route.params.id}`),
    api.get('/config'),
  ])
  invoice.value = invoiceRes.data
  config.value = configRes.data
  loading.value = false
})

async function downloadPdf() {
  if (!invoice.value) return
  pdfLoading.value = true
  try {
    await downloadInvoicePdf(invoice.value.id, invoice.value.invoice_number, true)
  } catch {
    alert('Could not download PDF. Please try again.')
  } finally {
    pdfLoading.value = false
  }
}

async function viewPdf() {
  if (!invoice.value) return
  pdfLoading.value = true
  try {
    await downloadInvoicePdf(invoice.value.id, invoice.value.invoice_number, false)
  } catch {
    alert('Could not open PDF. Please try again.')
  } finally {
    pdfLoading.value = false
  }
}

async function confirmMarkPaid() {
  saving.value = true
  try {
    const { data } = await api.post(`/invoices/${route.params.id}/paid`, payForm.value)
    invoice.value = data.invoice ?? data
    showPayModal.value = false
  } catch (e: any) {
    alert(e?.response?.data?.message ?? 'Could not mark invoice as paid.')
  } finally {
    saving.value = false
  }
}

async function regeneratePdf() {
  await api.post(`/invoices/${route.params.id}/regenerate-pdf`)
  await downloadPdf()
}

async function remove() {
  if (!invoice.value) return
  const paid = invoice.value.status === 'paid'
  const msg = paid
    ? `Delete invoice ${invoice.value.invoice_number}? This will also remove the linked income and payment records.`
    : `Delete invoice ${invoice.value.invoice_number}?`
  if (!confirm(msg)) return
  await api.delete(`/invoices/${route.params.id}`)
  router.push('/bookkeeping/invoices')
}
</script>

<template>
  <div v-if="loading" class="text-muted">Loading…</div>
  <div v-else-if="invoice" class="max-w-3xl">
    <div class="flex justify-between items-start mb-6">
      <div>
        <RouterLink to="/bookkeeping/invoices" class="text-sm text-muted hover:text-brand">← Invoices</RouterLink>
        <h1 class="text-2xl font-bold mt-1">{{ invoice.invoice_number }}</h1>
        <p class="text-muted">{{ invoice.customer?.name }} · {{ invoice.service_type }}</p>
      </div>
      <div class="flex gap-2">
        <button class="border px-4 py-2 rounded-lg text-sm disabled:opacity-50" :disabled="pdfLoading" @click="viewPdf">
          {{ pdfLoading ? 'Loading…' : 'View PDF' }}
        </button>
        <button class="border px-4 py-2 rounded-lg text-sm disabled:opacity-50" :disabled="pdfLoading" @click="downloadPdf">
          Download PDF
        </button>
        <button
          v-if="invoice.status !== 'paid'"
          class="bg-brand text-white px-4 py-2 rounded-lg text-sm"
          @click="showPayModal = true"
        >
          Mark as paid
        </button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 space-y-5">
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <p class="text-xs uppercase text-muted mb-1">Bill to</p>
          <p class="font-medium">{{ invoice.customer?.name }}</p>
          <p v-if="invoice.customer?.address" class="text-muted whitespace-pre-line">{{ invoice.customer.address }}</p>
          <p v-if="invoice.customer?.email" class="text-muted">{{ invoice.customer.email }}</p>
        </div>
        <div class="text-right">
          <p><span class="text-muted">Date:</span> {{ invoice.date?.slice?.(0, 10) ?? invoice.date }}</p>
          <p v-if="invoice.due_date"><span class="text-muted">Due:</span> {{ invoice.due_date?.slice?.(0, 10) ?? invoice.due_date }}</p>
          <p>
            <span
              class="px-2 py-0.5 rounded-full text-xs capitalize"
              :class="invoice.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
            >
              {{ invoice.status }}
            </span>
          </p>
        </div>
      </div>

      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted text-xs uppercase">
          <tr>
            <th class="text-left p-3">Description</th>
            <th class="text-right p-3">Qty</th>
            <th class="text-right p-3">Unit</th>
            <th class="text-right p-3">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in invoice.items" :key="item.id" class="border-t">
            <td class="p-3">{{ item.description }}</td>
            <td class="p-3 text-right">{{ item.quantity }}</td>
            <td class="p-3 text-right">{{ formatMoney(+item.unit_price, app.currencySymbol) }}</td>
            <td class="p-3 text-right font-medium">{{ formatMoney(+item.amount, app.currencySymbol) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="flex justify-end text-sm">
        <dl class="w-56 space-y-1">
          <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd>{{ formatMoney(+invoice.subtotal, app.currencySymbol) }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">VAT</dt><dd>{{ formatMoney(+invoice.vat_amount, app.currencySymbol) }}</dd></div>
          <div class="flex justify-between font-bold text-brand text-base border-t pt-2"><dt>Total</dt><dd>{{ formatMoney(+invoice.total, app.currencySymbol) }}</dd></div>
        </dl>
      </div>

      <p v-if="invoice.notes" class="text-sm text-muted border-l-4 border-brand pl-3">{{ invoice.notes }}</p>

      <div v-if="invoice.status === 'paid'" class="text-sm text-emerald-700 bg-emerald-50 rounded-lg px-4 py-3">
        Paid — income recorded automatically in your books.
      </div>

      <div class="flex gap-3 pt-2 border-t justify-between">
        <button class="text-sm text-brand" @click="regeneratePdf">Regenerate PDF</button>
        <button class="text-sm text-red-600 hover:underline" @click="remove">Delete invoice</button>
      </div>
    </div>

    <div v-if="showPayModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showPayModal = false">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-bold">Mark invoice as paid</h2>
        <p class="text-sm text-muted">
          This will record {{ formatMoney(+invoice.total, app.currencySymbol) }} as income for
          <strong>{{ invoice.invoice_number }}</strong>.
        </p>
        <label class="block text-sm">
          <span class="font-medium">Payment method</span>
          <select v-model="payForm.payment_method" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="(label, key) in config.payment_methods" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
        <label class="block text-sm">
          <span class="font-medium">Payment date</span>
          <input v-model="payForm.date" type="date" :min="config.records_start_date ?? DEFAULT_MIN_DATE" required class="mt-1 w-full border rounded-lg px-3 py-2" />
        </label>
        <label class="block text-sm">
          <span class="font-medium">Reference (optional)</span>
          <input v-model="payForm.reference" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="Bank reference" />
        </label>
        <div class="flex gap-3 justify-end pt-2">
          <button type="button" class="px-4 py-2" @click="showPayModal = false">Cancel</button>
          <button type="button" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-50" :disabled="saving" @click="confirmMarkPaid">
            {{ saving ? 'Saving…' : 'Confirm & add to income' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
