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
  whatsapp_phone?: string | null
  confirmation_sent_at?: string | null
  customer?: { name?: string; phone?: string; email?: string } | null
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
}

const route = useRoute()
const eventId = computed(() => route.params.id as string)
const event = ref<EventDetail | null>(null)
const loading = ref(false)
const error = ref('')
const statuses = ref<Record<string, string>>({})
const importing = ref(false)
const importMsg = ref('')
const sending = ref(false)
const sendMsg = ref('')
const editingTemplate = ref(false)
const templateDraft = ref('')
const savingTemplate = ref(false)
const downloadingList = ref(false)

const statusClass: Record<string, string> = {
  new: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-stone-200 text-stone-700',
  cancelled: 'bg-red-100 text-red-700',
}

const importMsgClass = computed(() =>
  /fail|error/i.test(importMsg.value) ? 'text-red-700' : 'text-emerald-700',
)

function isConfirmed(order: OrderRow) {
  return order.status === 'confirmed' || order.status === 'completed'
}

function startEditTemplate() {
  if (!event.value) return
  templateDraft.value = event.value.confirmation_template || ''
  editingTemplate.value = true
}

function cancelEditTemplate() {
  editingTemplate.value = false
  templateDraft.value = ''
}

async function saveTemplate() {
  if (!event.value) return
  savingTemplate.value = true
  try {
    const { data } = await api.put(`/google-form-order-events/${event.value.id}`, {
      name: event.value.name,
      location: event.value.location,
      event_date: String(event.value.event_date).slice(0, 10),
      start_time: String(event.value.start_time).slice(0, 5),
      end_time: String(event.value.end_time).slice(0, 5),
      confirmation_template: templateDraft.value,
      notes: event.value.notes ?? null,
    })
    event.value = { ...event.value, ...data, orders: event.value.orders }
    editingTemplate.value = false
  } catch (e: any) {
    sendMsg.value = e?.response?.data?.message ?? 'Could not save template.'
  } finally {
    savingTemplate.value = false
  }
}

async function applyOrderUpdate(orderId: number, data: OrderRow) {
  if (!event.value) return
  event.value.orders = event.value.orders.map((o) => (o.id === orderId ? { ...o, ...data } : o))
}

async function toggleConfirmed(order: OrderRow, checked: boolean) {
  if (order.status === 'cancelled') return
  const status = checked ? 'confirmed' : 'new'
  try {
    const { data } = await api.patch(`/google-form-orders/${order.id}/status`, { status })
    await applyOrderUpdate(order.id, data)
  } catch (e: any) {
    sendMsg.value = e?.response?.data?.message ?? 'Could not update confirmed status.'
  }
}

async function toggleWhatsappSent(order: OrderRow, sent: boolean) {
  if (order.status === 'cancelled') return
  try {
    const { data } = await api.patch(`/google-form-orders/${order.id}/whatsapp-sent`, { sent })
    await applyOrderUpdate(order.id, data)
  } catch (e: any) {
    sendMsg.value = e?.response?.data?.message ?? 'Could not update WhatsApp sent status.'
  }
}

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

function orderPhone(order: OrderRow) {
  return order.whatsapp_phone || order.customer?.phone || ''
}

async function savePhone(order: OrderRow, phone: string) {
  try {
    const { data } = await api.put(`/google-form-orders/${order.id}`, {
      event_id: event.value?.id,
      submitted_on: String(order.submitted_on).slice(0, 10),
      service_type: (order as any).service_type || undefined,
      items: order.items,
      status: order.status,
      customer_name: order.customer?.name || null,
      customer_email: order.customer?.email || null,
      customer_phone: phone || null,
      whatsapp_phone: phone || null,
      guest_count: order.guest_count ?? null,
      location: order.location ?? null,
      form_response_id: (order as any).form_response_id || undefined,
    })
    await applyOrderUpdate(order.id, data)
  } catch (e: any) {
    sendMsg.value = e?.response?.data?.message ?? 'Could not save WhatsApp number.'
  }
}

/** Open external URL (WhatsApp / browser). No Tauri import — Vite resolves modules from outside desktop/. */
function openExternalUrl(url: string) {
  const a = document.createElement('a')
  a.href = url
  a.target = '_blank'
  a.rel = 'noopener noreferrer'
  document.body.appendChild(a)
  a.click()
  a.remove()
  try {
    window.open(url, '_blank')
  } catch {
    // ignore
  }
}

async function markConfirmationsSent(orderIds: number[]) {
  if (!orderIds.length) return
  await api.post(`/google-form-order-events/${eventId.value}/send-confirmations`, {
    order_ids: orderIds,
    mark_sent: true,
  })
}

async function openWhatsApp(order: OrderRow) {
  sending.value = true
  sendMsg.value = ''
  try {
    const { data } = await api.get(`/google-form-orders/${order.id}/whatsapp`)
    const to = data.phone_display || data.phone
    openExternalUrl(data.url)
    await markConfirmationsSent([order.id])
    if (order.status === 'new') {
      try {
        await api.patch(`/google-form-orders/${order.id}/status`, { status: 'confirmed' })
      } catch {
        // optional
      }
    }
    sendMsg.value = `Opened WhatsApp for ${to}. If not sent, Edit → uncheck “WhatsApp message sent”.`
    await load()
  } catch (e: any) {
    sendMsg.value = e?.response?.data?.message ?? 'Could not open WhatsApp.'
  } finally {
    sending.value = false
  }
}

async function downloadDeliveryList(format: 'pdf' | 'csv' = 'pdf') {
  if (!event.value) return
  downloadingList.value = true
  sendMsg.value = ''
  try {
    const { data } = await api.get(`/google-form-order-events/${event.value.id}/delivery-list`, {
      params: { format },
      responseType: 'blob',
    })
    const mime = format === 'csv' ? 'text/csv' : 'application/pdf'
    const blob = new Blob([data], { type: mime })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    const safeName = (event.value.name || 'delivery-list').replace(/[^\w.-]+/g, '-')
    a.href = url
    a.download = `${safeName}-delivery-list.${format}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
    sendMsg.value = `Downloaded delivery list (${format.toUpperCase()}).`
  } catch (e: any) {
    sendMsg.value = e?.response?.data?.message ?? 'Could not download delivery list.'
  } finally {
    downloadingList.value = false
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
        <div class="flex gap-2 items-start flex-wrap">
          <RouterLink :to="`/orders/${event.id}/edit`" class="px-4 py-2 rounded-lg border">Edit event</RouterLink>
          <button
            type="button"
            class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60"
            :disabled="downloadingList || !(event.orders?.length)"
            @click="downloadDeliveryList('pdf')"
          >
            {{ downloadingList ? 'Preparing…' : 'Get list for delivery' }}
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-lg border text-sm disabled:opacity-60"
            :disabled="downloadingList || !(event.orders?.length)"
            @click="downloadDeliveryList('csv')"
          >
            CSV
          </button>
        </div>
      </div>

      <div v-if="sendMsg" class="mb-4 rounded-lg bg-stone-100 text-stone-800 px-4 py-3 text-sm">{{ sendMsg }}</div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl p-5 shadow-sm lg:col-span-2">
          <h2 class="font-semibold mb-2">Upload Google Form export</h2>
          <p class="text-sm text-muted mb-3">CSV or XLSX. Upload again anytime — existing responses are updated, not duplicated.</p>
          <input type="file" accept=".csv,.xlsx,.txt" :disabled="importing" @change="onUpload" />
          <p v-if="importMsg" class="text-sm mt-3" :class="importMsgClass">{{ importMsg }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm">
          <div class="flex items-center justify-between gap-2 mb-2">
            <h2 class="font-semibold">Confirmation template</h2>
            <button
              v-if="!editingTemplate"
              type="button"
              class="text-brand text-xs"
              @click="startEditTemplate"
            >
              Edit
            </button>
          </div>
          <template v-if="editingTemplate">
            <textarea v-model="templateDraft" rows="5" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" />
            <div class="flex gap-2 justify-end mt-2">
              <button type="button" class="px-3 py-1 text-sm" :disabled="savingTemplate" @click="cancelEditTemplate">Cancel</button>
              <button type="button" class="bg-brand text-white px-3 py-1 rounded-lg text-sm disabled:opacity-60" :disabled="savingTemplate || !templateDraft.trim()" @click="saveTemplate">
                {{ savingTemplate ? 'Saving…' : 'Save template' }}
              </button>
            </div>
          </template>
          <p v-else class="text-sm text-stone-700 whitespace-pre-wrap">{{ event.confirmation_template }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b flex justify-between items-center">
          <h2 class="font-semibold">Orders ({{ event.orders?.length ?? 0 }})</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-stone-50 text-muted uppercase text-xs">
            <tr>
              <th class="text-center p-3">Confirmed</th>
              <th class="text-left p-3">Ref</th>
              <th class="text-left p-3">Customer</th>
              <th class="text-left p-3">Order</th>
              <th class="text-left p-3">Status</th>
              <th class="text-center p-3">WA sent</th>
              <th class="text-left p-3">WhatsApp</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!event.orders?.length">
              <td colspan="8" class="p-6 text-center text-muted">No orders yet. Upload a Google Form export.</td>
            </tr>
            <tr v-for="o in event.orders" :key="o.id" class="border-t" :class="o.status === 'cancelled' ? 'opacity-60' : ''">
              <td class="p-3 text-center">
                <input
                  type="checkbox"
                  class="h-4 w-4 accent-brand"
                  :checked="isConfirmed(o)"
                  :disabled="o.status === 'cancelled'"
                  :title="isConfirmed(o) ? 'Confirmed' : 'Mark confirmed'"
                  @change="toggleConfirmed(o, ($event.target as HTMLInputElement).checked)"
                />
              </td>
              <td class="p-3 whitespace-nowrap">
                {{ o.reference }}
                <div class="text-xs text-muted">{{ formatDate(o.submitted_on) }}</div>
              </td>
              <td class="p-3">
                {{ o.customer?.name ?? '—' }}
                <div class="mt-1 flex items-center gap-1">
                  <input
                    class="border rounded px-2 py-1 text-xs w-36"
                    :value="orderPhone(o)"
                    placeholder="WhatsApp number"
                    :disabled="o.status === 'cancelled'"
                    @change="savePhone(o, ($event.target as HTMLInputElement).value)"
                  />
                </div>
              </td>
              <td class="p-3">
                <div class="whitespace-pre-wrap">{{ o.items }}</div>
                <div v-if="o.guest_count" class="text-xs text-muted">{{ o.guest_count }} guests</div>
              </td>
              <td class="p-3">
                <span class="px-2 py-0.5 rounded-full text-xs" :class="statusClass[o.status] || 'bg-stone-100'">{{ statuses[o.status] || o.status }}</span>
              </td>
              <td class="p-3 text-center">
                <input
                  type="checkbox"
                  class="h-4 w-4 accent-brand"
                  :checked="!!o.confirmation_sent_at"
                  :disabled="o.status === 'cancelled'"
                  title="WhatsApp message sent?"
                  @change="toggleWhatsappSent(o, ($event.target as HTMLInputElement).checked)"
                />
              </td>
              <td class="p-3 whitespace-nowrap">
                <button
                  v-if="o.status !== 'cancelled' && orderPhone(o)"
                  type="button"
                  class="text-brand text-xs disabled:opacity-50"
                  :disabled="sending"
                  @click="openWhatsApp(o)"
                >
                  {{ o.confirmation_sent_at ? 'Send again' : 'Send confirmation' }}
                </button>
                <span v-else-if="o.status === 'cancelled'" class="text-xs text-muted">—</span>
                <span v-else class="text-xs text-muted">Add phone</span>
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
