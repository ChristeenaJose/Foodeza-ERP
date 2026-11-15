<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@framework/api/client'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'

const route = useRoute()
const router = useRouter()
const minDate = ref(DEFAULT_MIN_DATE)
const loading = ref(false)
const saving = ref(false)
const saveError = ref('')
const placeholders = ref<string[]>([])

const editId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => Boolean(editId.value) && route.name === 'google-form-orders-event-edit')

const form = ref({
  name: '',
  location: '',
  event_date: new Date().toISOString().slice(0, 10),
  start_time: '11:00',
  end_time: '14:00',
  confirmation_template: '',
  notes: '',
})

onMounted(async () => {
  loading.value = true
  try {
    const [metaRes, cfgRes] = await Promise.all([api.get('/google-form-orders/meta'), api.get('/config')])
    minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE
    placeholders.value = metaRes.data.template_placeholders ?? []
    if (!isEdit.value) {
      form.value.confirmation_template = metaRes.data.default_confirmation_template ?? ''
    }

    if (isEdit.value) {
      const { data } = await api.get(`/google-form-order-events/${editId.value}`)
      form.value = {
        name: data.name ?? '',
        location: data.location ?? '',
        event_date: String(data.event_date).slice(0, 10),
        start_time: String(data.start_time).slice(0, 5),
        end_time: String(data.end_time).slice(0, 5),
        confirmation_template: data.confirmation_template ?? '',
        notes: data.notes ?? '',
      }
    }
  } finally {
    loading.value = false
  }
})

async function submit() {
  saving.value = true
  saveError.value = ''
  try {
    if (isEdit.value) {
      await api.put(`/google-form-order-events/${editId.value}`, form.value)
      await router.push(`/orders/${editId.value}`)
    } else {
      const { data } = await api.post('/google-form-order-events', form.value)
      await router.push(`/orders/${data.id}`)
    }
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Could not save event.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit event' : 'New event' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="saveError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ saveError }}</div>
      <label class="block"><span class="text-sm font-medium">Event name</span><input v-model="form.name" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="e.g. Onam lunch boxes" /></label>
      <label class="block"><span class="text-sm font-medium">Location</span><input v-model="form.location" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="Pickup / event place" /></label>
      <div class="grid grid-cols-3 gap-4">
        <label class="block"><span class="text-sm font-medium">Date</span><input v-model="form.event_date" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Start</span><input v-model="form.start_time" type="time" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">End</span><input v-model="form.end_time" type="time" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block">
        <span class="text-sm font-medium">WhatsApp confirmation template</span>
        <textarea v-model="form.confirmation_template" rows="3" required class="mt-1 w-full border rounded-lg px-3 py-2 font-mono text-sm" />
        <span class="text-xs text-muted mt-1 block">Placeholders: {{ placeholders.join(', ') }}</span>
      </label>
      <label class="block"><span class="text-sm font-medium">Notes</span><textarea v-model="form.notes" rows="2" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="flex gap-3 justify-end">
        <RouterLink :to="isEdit ? `/orders/${editId}` : '/orders'" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="saving">
          {{ saving ? 'Saving…' : isEdit ? 'Update event' : 'Save event' }}
        </button>
      </div>
    </form>
  </div>
</template>
