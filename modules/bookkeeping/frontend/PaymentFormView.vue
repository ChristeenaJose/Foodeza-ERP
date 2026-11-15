<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@framework/api/client'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'

const router = useRouter()
const invoices = ref<any[]>([])
const paymentMethods = ref<Record<string, string>>({})
const minDate = ref(DEFAULT_MIN_DATE)
const form = ref({
  date: new Date().toISOString().slice(0, 10),
  amount: '',
  payment_method: 'bank_transfer',
  invoice_id: '',
  reference: '',
})

onMounted(async () => {
  const [invRes, cfgRes] = await Promise.all([api.get('/invoices/outstanding'), api.get('/config')])
  invoices.value = invRes.data
  paymentMethods.value = cfgRes.data.payment_methods ?? {}
  minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE
})

async function submit() {
  await api.post('/payments', {
    ...form.value,
    amount: +form.value.amount,
    invoice_id: form.value.invoice_id || null,
  })
  router.push('/bookkeeping/payments')
}
</script>

<template>
  <div class="max-w-xl">
    <h1 class="text-2xl font-bold mb-6">Record Payment</h1>
    <form class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Date</span><input v-model="form.date" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Amount</span><input v-model="form.amount" type="number" step="0.01" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Method</span>
        <select v-model="form.payment_method" class="mt-1 w-full border rounded-lg px-3 py-2">
          <option v-for="(label, key) in paymentMethods" :key="key" :value="key">{{ label }}</option>
        </select>
      </label>
      <label class="block"><span class="text-sm font-medium">Invoice</span>
        <select v-model="form.invoice_id" class="mt-1 w-full border rounded-lg px-3 py-2">
          <option value="">None</option>
          <option v-for="i in invoices" :key="i.id" :value="i.id">{{ i.invoice_number }} — {{ i.customer?.name }}</option>
        </select>
      </label>
      <label class="block"><span class="text-sm font-medium">Reference</span><input v-model="form.reference" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="flex gap-3 justify-end">
        <RouterLink to="/bookkeeping/payments" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg">Save</button>
      </div>
    </form>
  </div>
</template>
