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

const editId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => Boolean(editId.value))

const form = ref({
  submitted_on: new Date().toISOString().slice(0, 10),
  event_date: new Date().toISOString().slice(0, 10),
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  service_type: '',
  items: '',
  guest_count: '' as string | number,
  location: '',
  status: 'new',
  form_response_id: '',
  notes: '',
})

onMounted(async () => {
  loading.value = true
  try {
    const [metaRes, cfgRes] = await Promise.all([api.get('/google-form-orders/meta'), api.get('/config')])
    meta.value = metaRes.data
    minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE

    if (isEdit.value) {
      const { data } = await api.get(`/google-form-orders/${editId.value}`)
      form.value = {
        submitted_on: String(data.submitted_on).slice(0, 10),
        event_date: String(data.event_date).slice(0, 10),
        customer_name: data.customer?.name ?? '',
        customer_email: data.customer?.email ?? '',
        customer_phone: data.customer?.phone ?? '',
        service_type: data.service_type,
        items: data.items ?? '',
        guest_count: data.guest_count ?? '',
        location: data.location ?? '',
        status: data.status,
        form_response_id: data.form_response_id ?? '',
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
      guest_count: emptyToNull(form.value.guest_count),
      form_response_id: form.value.form_response_id || null,
    }
    if (isEdit.value) {
      await api.put(`/google-form-orders/${editId.value}`, payload)
    } else {
      await api.post('/google-form-orders', payload)
    }
    await router.push('/orders')
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Could not save order.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Google Form order' : 'New Google Form order' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="saveError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ saveError }}</div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Submitted</span><input v-model="form.submitted_on" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Event date</span><input v-model="form.event_date" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Service</span>
          <select v-model="form.service_type" required class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="s in meta.services" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>
        <label class="block"><span class="text-sm font-medium">Status</span>
          <select v-model="form.status" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="(label, key) in meta.statuses" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
      </div>
      <label class="block"><span class="text-sm font-medium">Customer</span><input v-model="form.customer_name" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Email</span><input v-model="form.customer_email" type="email" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Phone</span><input v-model="form.customer_phone" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Order from the form</span><textarea v-model="form.items" rows="4" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="What they ordered" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Guests</span><input v-model="form.guest_count" type="number" min="0" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Location</span><input v-model="form.location" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Form response id</span><input v-model="form.form_response_id" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="Optional, from the Google Form response" /></label>
      <label class="block"><span class="text-sm font-medium">Notes</span><textarea v-model="form.notes" rows="3" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="flex gap-3 justify-end">
        <RouterLink to="/orders" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="saving">
          {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Save order' }}
        </button>
      </div>
    </form>
  </div>
</template>
