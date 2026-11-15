<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@framework/api/client'
import { useListPage } from '@framework/composables/useListPage'
import { DEFAULT_MIN_DATE, minMonthFromDate, yearsFrom, startOfMonth, endOfMonth, startOfYear, endOfYear } from '@framework/utils/dates'

type OrderRow = {
  id: number
  reference: string
  submitted_on: string
  event_date: string
  service_type: string
  items: string
  status: string
  guest_count?: number | null
  location?: string | null
  customer?: { name?: string } | null
}

const { rows, loading, error, load } = useListPage<OrderRow>('/google-form-orders')
const period = ref<'month' | 'year'>('month')
const month = ref(new Date().toISOString().slice(0, 7))
const year = ref(String(new Date().getFullYear()))
const minMonth = ref(minMonthFromDate())
const years = ref(yearsFrom())
const status = ref('')
const service = ref('')
const services = ref<string[]>([])
const statuses = ref<Record<string, string>>({})
const formUrl = ref('')

const statusClass: Record<string, string> = {
  new: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-stone-200 text-stone-700',
  cancelled: 'bg-red-100 text-red-700',
}

const filtered = computed(() => rows.value)

function formatDate(value?: string | null) {
  if (!value) return '—'
  return String(value).slice(0, 10)
}

async function loadFiltered() {
  const params: Record<string, string> =
    period.value === 'year'
      ? { from: startOfYear(year.value), to: endOfYear(year.value) }
      : { from: startOfMonth(month.value), to: endOfMonth(month.value) }
  if (status.value) params.status = status.value
  if (service.value) params.service_type = service.value
  await load(params)
}

onMounted(async () => {
  const [cfgRes, metaRes] = await Promise.all([api.get('/config'), api.get('/google-form-orders/meta')])
  minMonth.value = minMonthFromDate(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  services.value = metaRes.data.services ?? []
  statuses.value = metaRes.data.statuses ?? {}
  formUrl.value = metaRes.data.form_url ?? ''
  await loadFiltered()
})

async function remove(id: number) {
  if (!confirm('Delete this order?')) return
  await api.delete(`/google-form-orders/${id}`)
  rows.value = rows.value.filter((r) => r.id !== id)
}
</script>

<template>
  <div>
    <div class="flex justify-between mb-6 gap-3">
      <div>
        <h1 class="text-2xl font-bold">Google Form orders</h1>
        <p class="text-sm text-muted mt-1">Orders copied in from the Google Form.</p>
      </div>
      <div class="flex gap-2">
        <a v-if="formUrl" :href="formUrl" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg border">Open form</a>
        <RouterLink to="/orders/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ New order</RouterLink>
      </div>
    </div>

    <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
      <div class="flex rounded-lg border overflow-hidden text-sm">
        <button type="button" class="px-4 py-2" :class="period === 'month' ? 'bg-brand text-white' : 'bg-white text-stone-700'" @click="period='month'; loadFiltered()">Monthly</button>
        <button type="button" class="px-4 py-2 border-l" :class="period === 'year' ? 'bg-brand text-white' : 'bg-white text-stone-700'" @click="period='year'; loadFiltered()">Yearly</button>
      </div>
      <input v-if="period === 'month'" v-model="month" type="month" :min="minMonth" class="border rounded-lg px-3 py-2" @change="loadFiltered" />
      <select v-else v-model="year" class="border rounded-lg px-3 py-2" @change="loadFiltered">
        <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
      </select>
      <select v-model="service" class="border rounded-lg px-3 py-2" @change="loadFiltered">
        <option value="">All services</option>
        <option v-for="s in services" :key="s" :value="s">{{ s }}</option>
      </select>
      <select v-model="status" class="border rounded-lg px-3 py-2" @change="loadFiltered">
        <option value="">All statuses</option>
        <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm flex justify-between items-center">
      <span>{{ error }}</span>
      <button class="underline font-medium" @click="loadFiltered">Retry</button>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted uppercase text-xs">
          <tr>
            <th class="text-left p-3">Event</th>
            <th class="text-left p-3">Submitted</th>
            <th class="text-left p-3">Ref</th>
            <th class="text-left p-3">Customer</th>
            <th class="text-left p-3">Order</th>
            <th class="text-left p-3">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="7" class="p-6 text-center text-muted">No Google Form orders in this period.</td>
          </tr>
          <tr v-for="r in filtered" :key="r.id" class="border-t">
            <td class="p-3 whitespace-nowrap">{{ formatDate(r.event_date) }}</td>
            <td class="p-3 whitespace-nowrap">{{ formatDate(r.submitted_on) }}</td>
            <td class="p-3">{{ r.reference }}</td>
            <td class="p-3">
              {{ r.customer?.name ?? '—' }}
              <div v-if="r.location" class="text-xs text-muted">{{ r.location }}</div>
            </td>
            <td class="p-3">
              {{ r.items }}
              <div class="text-xs text-muted">{{ r.service_type }}<span v-if="r.guest_count"> · {{ r.guest_count }} guests</span></div>
            </td>
            <td class="p-3">
              <span class="px-2 py-0.5 rounded-full text-xs" :class="statusClass[r.status] || 'bg-stone-100'">{{ statuses[r.status] || r.status }}</span>
            </td>
            <td class="p-3 whitespace-nowrap">
              <RouterLink :to="`/orders/${r.id}/edit`" class="text-brand text-xs mr-3">Edit</RouterLink>
              <button class="text-red-600 text-xs" @click="remove(r.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
