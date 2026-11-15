<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@framework/api/client'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'

const route = useRoute()
const router = useRouter()
const meta = ref<{ services?: string[]; statuses?: Record<string, string> }>({})
const minDate = ref(DEFAULT_MIN_DATE)
const loading = ref(false)
const saving = ref(false)
const saveError = ref('')
const eventId = ref<number | null>(null)

const editId = computed(() => route.params.id as string | undefined)

const form = ref({
  submitted_on: new Date().toISOString().slice(0, 10),
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  whatsapp_phone: '',
  service_type: '',
  items: '',
  guest_count: '' as string | number,
  location: '',
  status: 'new',
  confirmation_sent: false,
  form_response_id: '',
  notes: '',
})

onMounted(async () => {
  loading.value = true
  try {
    const [metaRes, cfgRes, orderRes] = await Promise.all([
      api.get('/google-form-orders/meta'),
      api.get('/config'),
      api.get(`/google-form-orders/${editId.value}`),
    ])
    meta.value = metaRes.data
    minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE
    const data = orderRes.data
    eventId.value = data.event_id ?? data.event?.id ?? null
    form.value = {
      submitted_on: String(data.submitted_on).slice(0, 10),
      customer_name: data.customer?.name ?? '',
      customer_email: data.customer?.email ?? '',
      customer_phone: data.customer?.phone ?? '',
      whatsapp_phone: data.whatsapp_phone ?? data.customer?.phone ?? '',
      service_type: data.service_type ?? metaRes.data.services?.[0] ?? '',
      items: data.items ?? '',
      guest_count: data.guest_count ?? '',
      location: data.location ?? '',
      status: data.status,
      confirmation_sent: !!data.confirmation_sent_at,
      form_response_id: data.form_response_id ?? '',
      notes: data.notes ?? '',
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
      event_id: eventId.value,
      guest_count: emptyToNull(form.value.guest_count),
      form_response_id: form.value.form_response_id || null,
      whatsapp_phone: form.value.whatsapp_phone || form.value.customer_phone || null,
      customer_phone: form.value.whatsapp_phone || form.value.customer_phone || null,
    }
    await api.put(`/google-form-orders/${editId.value}`, payload)
    await router.push(eventId.value ? `/orders/${eventId.value}` : '/orders')
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Could not save order.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Edit order</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="saveError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ saveError }}</div>
      <label class="block"><span class="text-sm font-medium">Submitted</span><input v-model="form.submitted_on" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Service</span>
          <select v-model="form.service_type" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="s in meta.services" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>
        <label class="block"><span class="text-sm font-medium">Status</span>
          <select v-model="form.status" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="(label, key) in meta.statuses" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
      </div>
      <label class="flex items-start gap-3 rounded-lg border border-stone-200 bg-stone-50 px-3 py-3 cursor-pointer">
        <input v-model="form.confirmation_sent" type="checkbox" class="h-4 w-4 mt-0.5 accent-brand" />
        <span>
          <span class="text-sm font-medium block">WhatsApp message sent</span>
          <span class="text-xs text-muted block mt-0.5">
            Checked after Send confirmation (shows as Send again). If the message was not sent, uncheck here and save — then you can send again from the event list.
          </span>
        </span>
      </label>
      <label class="block"><span class="text-sm font-medium">Customer</span><input v-model="form.customer_name" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Email</span><input v-model="form.customer_email" type="email" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block">
          <span class="text-sm font-medium">WhatsApp number</span>
          <input v-model="form.whatsapp_phone" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="015237221698" />
          <span class="text-xs text-muted mt-1 block">This exact number is used when you click Send.</span>
        </label>
      </div>
      <label class="block"><span class="text-sm font-medium">Order details</span><textarea v-model="form.items" rows="4" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Guests</span><input v-model="form.guest_count" type="number" min="0" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Location</span><input v-model="form.location" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Form response id</span><input v-model="form.form_response_id" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label class="block"><span class="text-sm font-medium">Notes</span><textarea v-model="form.notes" rows="3" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="flex gap-3 justify-end">
        <RouterLink :to="eventId ? `/orders/${eventId}` : '/orders'" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="saving">
          {{ saving ? 'Saving…' : 'Update order' }}
        </button>
      </div>
    </form>
  </div>
</template>
