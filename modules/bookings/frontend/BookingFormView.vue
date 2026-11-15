<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@framework/api/client'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'
import { formatTime, statusLabels } from './bookings'

const route = useRoute()
const router = useRouter()
const meta = ref<{ services?: string[]; statuses?: Record<string, string> }>({})
const minDate = ref(DEFAULT_MIN_DATE)
const loading = ref(false)
const saving = ref(false)
const saveError = ref('')

const editId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => Boolean(editId.value))
const cancelTo = computed(() => (isEdit.value ? '/bookings/list' : '/bookings'))

const form = ref({
  event_date: (route.query.date as string) || new Date().toISOString().slice(0, 10),
  start_time: '',
  end_time: '',
  title: '',
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  service_type: '',
  status: 'inquiry',
  location: '',
  guest_count: '' as string | number,
  quoted_amount: '' as string | number,
  deposit_amount: '' as string | number,
  notes: '',
})

onMounted(async () => {
  loading.value = true
  try {
    const [metaRes, cfgRes] = await Promise.all([api.get('/bookings/meta'), api.get('/config')])
    meta.value = metaRes.data
    minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE

    if (isEdit.value) {
      const { data } = await api.get(`/bookings/${editId.value}`)
      form.value = {
        event_date: String(data.event_date).slice(0, 10),
        start_time: formatTime(data.start_time),
        end_time: formatTime(data.end_time),
        title: data.title ?? '',
        customer_name: data.customer?.name ?? '',
        customer_email: data.customer?.email ?? '',
        customer_phone: data.customer?.phone ?? '',
        service_type: data.service_type,
        status: data.status,
        location: data.location ?? '',
        guest_count: data.guest_count ?? '',
        quoted_amount: data.quoted_amount ?? '',
        deposit_amount: data.deposit_amount ?? '',
        notes: data.notes ?? '',
      }
    } else {
      form.value.service_type = metaRes.data.services?.[0] ?? ''
    }
  } finally {
    loading.value = false
  }
})

function emptyToNull(value: string | number) {
  if (value === '' || value === null || value === undefined) return null
  return value
}

async function submit() {
  saving.value = true
  saveError.value = ''
  try {
    const payload = {
      ...form.value,
      start_time: form.value.start_time || null,
      end_time: form.value.end_time || null,
      guest_count: emptyToNull(form.value.guest_count),
      quoted_amount: emptyToNull(form.value.quoted_amount),
      deposit_amount: emptyToNull(form.value.deposit_amount),
    }
    if (isEdit.value) {
      await api.put(`/bookings/${editId.value}`, payload)
    } else {
      await api.post('/bookings', payload)
    }
    await router.push(cancelTo.value)
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Could not save booking.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit booking' : 'New booking' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="saveError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ saveError }}</div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Event date</span><input v-model="form.event_date" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Status</span>
          <select v-model="form.status" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="(label, key) in (meta.statuses || statusLabels)" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Start</span><input v-model="form.start_time" type="time" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">End</span><input v-model="form.end_time" type="time" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Title</span><input v-model="form.title" placeholder="Optional — defaults to service and customer" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Service</span>
          <select v-model="form.service_type" required class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="s in meta.services" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>
        <label class="block"><span class="text-sm font-medium">Guests</span><input v-model="form.guest_count" type="number" min="0" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Customer</span><input v-model="form.customer_name" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Email</span><input v-model="form.customer_email" type="email" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Phone</span><input v-model="form.customer_phone" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Location</span><input v-model="form.location" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Quoted amount</span><input v-model="form.quoted_amount" type="number" step="0.01" min="0" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Deposit</span><input v-model="form.deposit_amount" type="number" step="0.01" min="0" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Notes</span><textarea v-model="form.notes" rows="3" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="flex gap-3 justify-end">
        <RouterLink :to="cancelTo" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="saving">
          {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Save booking' }}
        </button>
      </div>
    </form>
  </div>
</template>
