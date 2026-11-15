<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@framework/api/client'

type EventRow = {
  id: number
  name: string
  location: string
  event_date: string
  start_time: string
  end_time: string
  orders_count?: number
  active_orders_count?: number
}

const rows = ref<EventRow[]>([])
const loading = ref(false)
const error = ref('')
const formUrl = ref('')

function formatDate(value?: string | null) {
  if (!value) return '—'
  return String(value).slice(0, 10)
}

function formatTime(value?: string | null) {
  if (!value) return ''
  return String(value).slice(0, 5)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [eventsRes, metaRes] = await Promise.all([
      api.get('/google-form-order-events'),
      api.get('/google-form-orders/meta'),
    ])
    rows.value = eventsRes.data
    formUrl.value = metaRes.data.form_url ?? ''
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not load events.'
  } finally {
    loading.value = false
  }
}

async function remove(id: number) {
  if (!confirm('Delete this event and all its orders?')) return
  await api.delete(`/google-form-order-events/${id}`)
  rows.value = rows.value.filter((r) => r.id !== id)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex justify-between mb-6 gap-3 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Google Form orders</h1>
        <p class="text-sm text-muted mt-1">Plan an event, upload form exports, and send WhatsApp confirmations.</p>
      </div>
      <div class="flex gap-2">
        <a v-if="formUrl" :href="formUrl" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg border">Open form</a>
        <RouterLink to="/orders/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ New event</RouterLink>
      </div>
    </div>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm flex justify-between items-center">
      <span>{{ error }}</span>
      <button class="underline font-medium" @click="load">Retry</button>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted uppercase text-xs">
          <tr>
            <th class="text-left p-3">Event</th>
            <th class="text-left p-3">When</th>
            <th class="text-left p-3">Location</th>
            <th class="text-left p-3">Orders</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="rows.length === 0">
            <td colspan="5" class="p-6 text-center text-muted">No events yet. Create one, then upload a Google Form CSV/XLSX.</td>
          </tr>
          <tr v-for="r in rows" :key="r.id" class="border-t">
            <td class="p-3">
              <RouterLink :to="`/orders/${r.id}`" class="text-brand font-medium">{{ r.name }}</RouterLink>
            </td>
            <td class="p-3 whitespace-nowrap">
              {{ formatDate(r.event_date) }}
              <div class="text-xs text-muted">{{ formatTime(r.start_time) }}–{{ formatTime(r.end_time) }}</div>
            </td>
            <td class="p-3">{{ r.location }}</td>
            <td class="p-3">{{ r.active_orders_count ?? r.orders_count ?? 0 }}</td>
            <td class="p-3 whitespace-nowrap text-right">
              <RouterLink :to="`/orders/${r.id}`" class="text-brand text-xs mr-3">Open</RouterLink>
              <RouterLink :to="`/orders/${r.id}/edit`" class="text-brand text-xs mr-3">Edit</RouterLink>
              <button class="text-red-600 text-xs" @click="remove(r.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
