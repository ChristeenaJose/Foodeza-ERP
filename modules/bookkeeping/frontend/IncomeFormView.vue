<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@framework/api/client'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'
import { pickListQuery } from './listQuery'

const route = useRoute()
const router = useRouter()
const meta = ref<any>({})
const minDate = ref(DEFAULT_MIN_DATE)
const loading = ref(false)
const saving = ref(false)
const saveError = ref('')
const listQuery = computed(() => pickListQuery(route.query, ['period', 'month', 'year', 'sort', 'dir']))
const cancelTo = computed(() => ({ path: '/bookkeeping/income', query: listQuery.value }))

const editId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => Boolean(editId.value))

const form = ref({
  date: new Date().toISOString().slice(0, 10),
  customer_name: '',
  service_type: '',
  amount: '' as string | number,
  payment_method: 'bank_transfer',
  invoice_number: '',
  notes: '',
})

onMounted(async () => {
  loading.value = true
  try {
    const [metaRes, cfgRes] = await Promise.all([api.get('/incomes/meta'), api.get('/config')])
    meta.value = metaRes.data
    minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE

    if (isEdit.value) {
      const { data } = await api.get(`/incomes/${editId.value}`)
      form.value = {
        date: String(data.date).slice(0, 10),
        customer_name: data.customer?.name ?? '',
        service_type: data.service_type,
        amount: data.amount,
        payment_method: data.payment_method,
        invoice_number: data.invoice_number ?? '',
        notes: data.notes ?? '',
      }
    } else {
      form.value.service_type = metaRes.data.services?.[0] ?? ''
      form.value.invoice_number = metaRes.data.suggested_invoice_number ?? ''
    }
  } finally {
    loading.value = false
  }
})

async function submit() {
  saving.value = true
  saveError.value = ''
  try {
    const payload = { ...form.value, amount: +form.value.amount }
    if (isEdit.value) {
      await api.put(`/incomes/${editId.value}`, payload)
    } else {
      await api.post('/incomes', payload)
    }
    await router.push(cancelTo.value)
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Could not save income.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-xl">
    <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Income' : 'Record Income' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="saveError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ saveError }}</div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Date</span><input v-model="form.date" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Amount</span><input v-model="form.amount" type="number" step="0.01" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Customer</span><input v-model="form.customer_name" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Service</span>
          <select v-model="form.service_type" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="s in meta.services" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>
        <label class="block"><span class="text-sm font-medium">Payment</span>
          <select v-model="form.payment_method" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="(l, k) in meta.payment_methods" :key="k" :value="k">{{ l }}</option>
          </select>
        </label>
      </div>
      <label class="block"><span class="text-sm font-medium">Invoice #</span><input v-model="form.invoice_number" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label class="block"><span class="text-sm font-medium">Notes</span><input v-model="form.notes" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="flex gap-3 justify-end">
        <RouterLink :to="cancelTo" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="saving">
          {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Save' }}
        </button>
      </div>
    </form>
  </div>
</template>
