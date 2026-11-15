<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '@framework/api/client'

type OrderRow = {
  id: number
  reference: string
  submitted_on: string
  items: string
  status: string
  guest_count?: number | null
  location?: string | null
  confirmation_sent_at?: string | null
  customer?: { name?: string; phone?: string; email?: string } | null
}

type UpdateRow = {
  id: number
  title?: string | null
  message: string
  last_sent_at?: string | null
  created_at?: string
}

type EventDetail = {
  id: number
  name: string
  location: string
  event_date: string
  start_time: string
  end_time: string
  confirmation_template: string
  notes?: string | null
  orders: OrderRow[]
  updates: UpdateRow[]
}

const route = useRoute()
const eventId = computed(() => route.params.id as string)
const event = ref<EventDetail | null>(null)
const loading = ref(false)
const error = ref('')
const statuses = ref<Record<string, string>>({})
const importing = ref(false)
const importMsg = ref('')
const updateTitle = ref('')
const updateMessage = ref('')
const savingUpdate = ref(false)
const sending = ref(false)
const sendMsg = ref('')

const statusClass: Record<string, string> = {
  new: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-stone-200 text-stone-700',
  cancelled: 'bg-red-100 text-red-700',
}

const activeOrders = computed(() => (event.value?.orders ?? []).filter((o) => o.status !== 'cancelled'))

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
    const [eventRes, metaRes] = await Promise.all([
      api.get(`/google-form-order-events/${eventId.value}`),
      api.get('/google-form-orders/meta'),
    ])
    event.value = eventRes.data
    statuses.value = metaRes.data.statuses ?? {}
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not load event.'
  } finally {
    loading.value = false
  }
}

async function onUpload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !event.value) return
  importing.value = true
  importMsg.value = ''
  try {
    const fd = new FormData()
    fd.append('file', file)
    const { data } = await api.post(`/google-form-order-events/${event.value.id}/import`, fd)
    event.value = {
      ...event.value,
      orders: data.event?.orders ?? event.value.orders,
    }
    importMsg.value = `Imported: ${data.created} new, ${data.updated} updated, ${data.skipped} skipped.`
  } catch (err: any) {
    importMsg.value = err?.response?.data?.message ?? 'Import failed.'
  } finally {
    importing.value = false
    ;(e.target as HTMLInputElement).value = ''
  }
}

async function cancelOrder(order: OrderRow) {
  if (!confirm(`Cancel order ${order.reference}?`)) return
  const { data } = await api.post(`/google-form-orders/${order.id}/cancel`)
  if (!event.value) return
  event.value.orders = event.value.orders.map((o) => (o.id === order.id ? { ...o, ...data } : o))
}

async function openWhatsApp(order: OrderRow) {
  const { data } = await api.get(`/google-form-orders/${order.id}/whatsapp`)
  window.open(data.url, '_blank')
  await api.post(`/google-form-order-events/${eventId.value}/send-confirmations`, {
    order_ids: [order.id],
    mark_sent: true,
  })
  await load()
}

function openLinksSequentially(links: { url: string }[]) {
  for (const link of links) {
    window.open(link.url, '_blank')
  }
}

async function sendConfirmationsAll() {
  if (!event.value) return
  if (!confirm(`Open WhatsApp confirmation for ${activeOrders.value.length} order(s)?`)) return
  sending.value = true
  sendMsg.value = ''
  try {
    const { data } = await api.post(`/google-form-order-events/${event.value.id}/send-confirmations`, {
      mark_sent: true,
    })
    openLinksSequentially(data.links ?? [])
    const missing = data.missing_phone?.length ?? 0
    sendMsg.value = `Opened ${data.count} WhatsApp chat(s).${missing ? ` ${missing} without phone.` : ''}`
    await load()
  } catch (e: any) {
    sendMsg.value = e?.response?.data?.message ?? 'Could not prepare messages.'
  } finally {
    sending.value = false
  }
}

async function createUpdate() {
  if (!event.value || !updateMessage.value.trim()) return
  savingUpdate.value = true
  try {
    const { data } = await api.post(`/google-form-order-events/${event.value.id}/updates`, {
      title: updateTitle.value || null,
      message: updateMessage.value,
    })
    event.value.updates = [data, ...(event.value.updates ?? [])]
    updateTitle.value = ''
    updateMessage.value = ''
  } finally {
    savingUpdate.value = false
  }
}

async function sendUpdate(update: UpdateRow) {
  if (!event.value) return
  if (!confirm(`Send this update to ${activeOrders.value.length} active order(s) via WhatsApp?`)) return
  sending.value = true
  sendMsg.value = ''
  try {
    const { data } = await api.post(
      `/google-form-order-events/${event.value.id}/updates/${update.id}/send`,
      { mark_sent: true },
    )
    openLinksSequentially(data.links ?? [])
    const missing = data.missing_phone?.length ?? 0
    sendMsg.value = `Opened ${data.count} update chat(s).${missing ? ` ${missing} without phone.` : ''}`
    await load()
  } catch (e: any) {
    sendMsg.value = e?.response?.data?.message ?? 'Could not send update.'
  } finally {
    sending.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="loading" class="text-muted text-sm">Loading…</div>
    <div v-else-if="error" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ error }}</div>
    <template v-else-if="event">
      <div class="flex justify-between mb-6 gap-3 flex-wrap">
        <div>
          <div class="text-sm text-muted mb-1">
            <RouterLink to="/orders" class="text-brand">Events</RouterLink>
            <span class="mx-1">/</span>
            {{ event.name }}
          </div>
          <h1 class="text-2xl font-bold">{{ event.name }}</h1>
          <p class="text-sm text-muted mt-1">
            {{ formatDate(event.event_date) }} · {{ formatTime(event.start_time) }}–{{ formatTime(event.end_time) }} · {{ event.location }}
          </p>
        </div>
        <div class="flex gap-2 items-start">
          <RouterLink :to="`/orders/${event.id}/edit`" class="px-4 py-2 rounded-lg border">Edit event</RouterLink>
          <button
            type="button"
            class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60"
            :disabled="sending || activeOrders.length === 0"
            @click="sendConfirmationsAll"
          >
            Send confirmations to all
          </button>
        </div>
      </div>

      <div v-if="sendMsg" class="mb-4 rounded-lg bg-stone-100 text-stone-800 px-4 py-3 text-sm">{{ sendMsg }}</div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl p-5 shadow-sm lg:col-span-2">
          <h2 class="font-semibold mb-2">Upload Google Form export</h2>
          <p class="text-sm text-muted mb-3">CSV or XLSX. Upload again anytime — existing responses are updated, not duplicated.</p>
          <input type="file" accept=".csv,.xlsx,.txt" :disabled="importing" @change="onUpload" />
          <p v-if="importMsg" class="text-sm mt-3" :class="/fail|error/i.test(importMsg) ? 'text-red-700' : 'text-emerald-700'">{{ importMsg }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm">
          <h2 class="font-semibold mb-2">Confirmation template</h2>
          <p class="text-sm text-stone-700 whitespace-pre-wrap">{{ event.confirmation_template }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl p-5 shadow-sm mb-6">
        <h2 class="font-semibold mb-3">Event update (delay, menu change, …)</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
          <input v-model="updateTitle" class="border rounded-lg px-3 py-2 md:col-span-1" placeholder="Title (optional)" />
          <input v-model="updateMessage" class="border rounded-lg px-3 py-2 md:col-span-2" placeholder="Hi {name}, pickup delayed to 13:00…" />
          <button type="button" class="bg-stone-800 text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="savingUpdate || !updateMessage.trim()" @click="createUpdate">
            Save update
          </button>
        </div>
        <div v-if="(event.updates ?? []).length === 0" class="text-sm text-muted">No updates yet.</div>
        <ul v-else class="space-y-2">
          <li v-for="u in event.updates" :key="u.id" class="flex flex-wrap items-center justify-between gap-2 border rounded-lg px-3 py-2">
            <div>
              <div class="text-sm font-medium">{{ u.title || 'Update' }}</div>
              <div class="text-sm text-stone-700">{{ u.message }}</div>
              <div v-if="u.last_sent_at" class="text-xs text-muted">Last sent {{ formatDate(u.last_sent_at) }}</div>
            </div>
            <button type="button" class="text-brand text-sm" :disabled="sending || activeOrders.length === 0" @click="sendUpdate(u)">Send to all</button>
          </li>
        </ul>
      </div>

      <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b flex justify-between items-center">
          <h2 class="font-semibold">Orders ({{ event.orders?.length ?? 0 }})</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-stone-50 text-muted uppercase text-xs">
            <tr>
              <th class="text-left p-3">Ref</th>
              <th class="text-left p-3">Customer</th>
              <th class="text-left p-3">Order</th>
              <th class="text-left p-3">Status</th>
              <th class="text-left p-3">WhatsApp</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!event.orders?.length">
              <td colspan="6" class="p-6 text-center text-muted">No orders yet. Upload a Google Form export.</td>
            </tr>
            <tr v-for="o in event.orders" :key="o.id" class="border-t" :class="o.status === 'cancelled' ? 'opacity-60' : ''">
              <td class="p-3 whitespace-nowrap">
                {{ o.reference }}
                <div class="text-xs text-muted">{{ formatDate(o.submitted_on) }}</div>
              </td>
              <td class="p-3">
                {{ o.customer?.name ?? '—' }}
                <div class="text-xs text-muted">{{ o.customer?.phone || 'No phone' }}</div>
              </td>
              <td class="p-3">
                <div class="whitespace-pre-wrap">{{ o.items }}</div>
                <div v-if="o.guest_count" class="text-xs text-muted">{{ o.guest_count }} guests</div>
              </td>
              <td class="p-3">
                <span class="px-2 py-0.5 rounded-full text-xs" :class="statusClass[o.status] || 'bg-stone-100'">{{ statuses[o.status] || o.status }}</span>
              </td>
              <td class="p-3 whitespace-nowrap">
                <button
                  v-if="o.status !== 'cancelled' && o.customer?.phone"
                  type="button"
                  class="text-brand text-xs"
                  @click="openWhatsApp(o)"
                >
                  {{ o.confirmation_sent_at ? 'Send again' : 'Send confirmation' }}
                </button>
                <span v-else class="text-xs text-muted">—</span>
              </td>
              <td class="p-3 whitespace-nowrap text-right">
                <RouterLink :to="`/orders/entries/${o.id}/edit`" class="text-brand text-xs mr-3">Edit</RouterLink>
                <button v-if="o.status !== 'cancelled'" class="text-red-600 text-xs" @click="cancelOrder(o)">Cancel</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
