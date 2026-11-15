<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { useListPage } from '@framework/composables/useListPage'
import { DEFAULT_MIN_DATE, minMonthFromDate, yearsFrom, startOfMonth, endOfMonth, startOfYear, endOfYear } from '@framework/utils/dates'
import { type BookingRow, formatDate, formatTime, statusClass, statusLabels } from './bookings'

const app = useAppStore()
const { rows, loading, error, load } = useListPage<BookingRow>('/bookings')
const period = ref<'month' | 'year'>('month')
const month = ref(new Date().toISOString().slice(0, 7))
const year = ref(String(new Date().getFullYear()))
const minMonth = ref(minMonthFromDate())
const years = ref(yearsFrom())
const status = ref('')
const service = ref('')
const services = ref<string[]>([])

const filtered = computed(() => rows.value)

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
  const [cfgRes, metaRes] = await Promise.all([api.get('/config'), api.get('/bookings/meta')])
  minMonth.value = minMonthFromDate(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  services.value = metaRes.data.services ?? []
  await loadFiltered()
})

async function remove(id: number) {
  if (!confirm('Delete this booking?')) return
  await api.delete(`/bookings/${id}`)
  rows.value = rows.value.filter((r) => r.id !== id)
}
</script>

<template>
  <div>
    <div class="flex justify-between mb-6">
      <h1 class="text-2xl font-bold">All bookings</h1>
      <RouterLink to="/bookings/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ New booking</RouterLink>
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
        <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
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
            <th class="text-left p-3">Date</th>
            <th class="text-left p-3">Ref</th>
            <th class="text-left p-3">Booking</th>
            <th class="text-left p-3">Customer</th>
            <th class="text-left p-3">Service</th>
            <th class="text-left p-3">Status</th>
            <th class="text-right p-3">Quote</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="8" class="p-6 text-center text-muted">No bookings in this period.</td>
          </tr>
          <tr v-for="r in filtered" :key="r.id" class="border-t">
            <td class="p-3 whitespace-nowrap">
              {{ formatDate(r.event_date) }}
              <div v-if="r.start_time" class="text-xs text-muted">{{ formatTime(r.start_time) }}<span v-if="r.end_time">–{{ formatTime(r.end_time) }}</span></div>
            </td>
            <td class="p-3">{{ r.reference }}</td>
            <td class="p-3">
              {{ r.title }}
              <div v-if="r.location" class="text-xs text-muted">{{ r.location }}</div>
            </td>
            <td class="p-3">{{ r.customer?.name ?? '—' }}</td>
            <td class="p-3">{{ r.service_type }}</td>
            <td class="p-3">
              <span class="px-2 py-0.5 rounded-full text-xs" :class="statusClass[r.status]">{{ statusLabels[r.status] }}</span>
            </td>
            <td class="p-3 text-right">{{ r.quoted_amount ? formatMoney(+r.quoted_amount, app.currencySymbol) : '—' }}</td>
            <td class="p-3 whitespace-nowrap">
              <RouterLink :to="`/bookings/${r.id}/edit`" class="text-brand text-xs mr-3">Edit</RouterLink>
              <button class="text-red-600 text-xs" @click="remove(r.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
