<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { useListPage } from '@framework/composables/useListPage'
import { type BookingRow, formatDate, formatTime, statusClass, statusLabels } from './bookings'

const app = useAppStore()
const { rows, loading, error, load } = useListPage<BookingRow>('/bookings')
const month = ref(new Date().toISOString().slice(0, 7))
const today = new Date().toISOString().slice(0, 10)

const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

const monthLabel = computed(() => {
  const [y, m] = month.value.split('-').map(Number)
  return new Date(y, m - 1, 1).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
})

function startOfMonth(ym: string) {
  return `${ym}-01`
}

function endOfMonth(ym: string) {
  const [y, m] = ym.split('-').map(Number)
  const last = new Date(y, m, 0).getDate()
  return `${ym}-${String(last).padStart(2, '0')}`
}

const cells = computed(() => {
  const [y, m] = month.value.split('-').map(Number)
  const first = new Date(y, m - 1, 1)
  const daysInMonth = new Date(y, m, 0).getDate()
  const mondayIndex = (first.getDay() + 6) % 7
  const result: { date: string | null; day: number | null }[] = []

  for (let i = 0; i < mondayIndex; i++) {
    result.push({ date: null, day: null })
  }
  for (let d = 1; d <= daysInMonth; d++) {
    result.push({ date: `${month.value}-${String(d).padStart(2, '0')}`, day: d })
  }
  while (result.length % 7 !== 0) {
    result.push({ date: null, day: null })
  }
  return result
})

const byDate = computed(() => {
  const map: Record<string, BookingRow[]> = {}
  for (const row of rows.value) {
    const key = formatDate(row.event_date)
    if (!map[key]) map[key] = []
    map[key].push(row)
  }
  return map
})

const counts = computed(() => {
  const active = rows.value.filter((r) => r.status !== 'cancelled')
  return {
    total: rows.value.length,
    inquiry: rows.value.filter((r) => r.status === 'inquiry').length,
    confirmed: rows.value.filter((r) => r.status === 'confirmed').length,
    upcoming: active.filter((r) => formatDate(r.event_date) >= today).length,
  }
})

const upcoming = computed(() =>
  [...rows.value]
    .filter((r) => r.status !== 'cancelled' && formatDate(r.event_date) >= today)
    .sort((a, b) => `${formatDate(a.event_date)}${formatTime(a.start_time)}`.localeCompare(`${formatDate(b.event_date)}${formatTime(b.start_time)}`))
    .slice(0, 8)
)

async function loadMonth() {
  await load({ from: startOfMonth(month.value), to: endOfMonth(month.value) })
}

function shiftMonth(delta: number) {
  const [y, m] = month.value.split('-').map(Number)
  const next = new Date(y, m - 1 + delta, 1)
  month.value = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`
  loadMonth()
}

onMounted(loadMonth)
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Booking management</h1>
        <p class="text-muted text-sm">Catering · Food Truck · Corporate Lunch · Meal Box Delivery</p>
      </div>
      <RouterLink to="/bookings/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ New booking</RouterLink>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="text-xs text-muted uppercase">This month</div>
        <div class="text-2xl font-semibold mt-1">{{ counts.total }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="text-xs text-muted uppercase">Upcoming</div>
        <div class="text-2xl font-semibold mt-1">{{ counts.upcoming }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="text-xs text-muted uppercase">Inquiries</div>
        <div class="text-2xl font-semibold mt-1 text-amber-700">{{ counts.inquiry }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="text-xs text-muted uppercase">Confirmed</div>
        <div class="text-2xl font-semibold mt-1 text-emerald-700">{{ counts.confirmed }}</div>
      </div>
    </div>

    <div class="flex items-center gap-3 mb-4">
      <button type="button" class="px-3 py-2 rounded-lg border bg-white" @click="shiftMonth(-1)">←</button>
      <h2 class="text-lg font-semibold min-w-48 text-center">{{ monthLabel }}</h2>
      <button type="button" class="px-3 py-2 rounded-lg border bg-white" @click="shiftMonth(1)">→</button>
      <input v-model="month" type="month" class="border rounded-lg px-3 py-2 ml-auto" @change="loadMonth" />
    </div>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm flex justify-between items-center">
      <span>{{ error }}</span>
      <button class="underline font-medium" @click="loadMonth">Retry</button>
    </div>

    <div v-if="loading" class="text-muted text-sm mb-4">Loading…</div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
      <div class="grid grid-cols-7 bg-stone-50 text-xs uppercase text-muted">
        <div v-for="day in weekdayLabels" :key="day" class="p-2 text-center font-medium">{{ day }}</div>
      </div>
      <div class="grid grid-cols-7">
        <div
          v-for="(cell, i) in cells"
          :key="i"
          class="min-h-28 border-t border-l p-1.5 align-top"
          :class="cell.date === today ? 'bg-red-50/60' : cell.date ? 'bg-white' : 'bg-stone-50'"
        >
          <template v-if="cell.date">
            <div class="flex justify-between items-center mb-1">
              <span class="text-xs font-medium" :class="cell.date === today ? 'text-brand' : 'text-stone-600'">{{ cell.day }}</span>
              <RouterLink :to="{ path: '/bookings/new', query: { date: cell.date } }" class="text-[10px] text-brand hover:underline">+</RouterLink>
            </div>
            <RouterLink
              v-for="row in byDate[cell.date] || []"
              :key="row.id"
              :to="`/bookings/${row.id}/edit`"
              class="block rounded px-1.5 py-1 mb-1 text-[11px] leading-tight truncate"
              :class="statusClass[row.status] || 'bg-stone-100'"
            >
              <span v-if="row.start_time">{{ formatTime(row.start_time) }} </span>{{ row.title }}
            </RouterLink>
          </template>
        </div>
      </div>
    </div>

    <h2 class="text-lg font-semibold mb-3">Upcoming</h2>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted uppercase text-xs">
          <tr>
            <th class="text-left p-3">Date</th>
            <th class="text-left p-3">Booking</th>
            <th class="text-left p-3">Customer</th>
            <th class="text-left p-3">Service</th>
            <th class="text-left p-3">Status</th>
            <th class="text-right p-3">Quote</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="upcoming.length === 0">
            <td colspan="6" class="p-6 text-center text-muted">No upcoming bookings this month.</td>
          </tr>
          <tr v-for="row in upcoming" :key="row.id" class="border-t">
            <td class="p-3 whitespace-nowrap">
              {{ formatDate(row.event_date) }}
              <span v-if="row.start_time" class="text-muted"> {{ formatTime(row.start_time) }}</span>
            </td>
            <td class="p-3">
              <RouterLink :to="`/bookings/${row.id}/edit`" class="font-medium hover:text-brand">{{ row.title }}</RouterLink>
              <div class="text-xs text-muted">{{ row.reference }}</div>
            </td>
            <td class="p-3">{{ row.customer?.name ?? '—' }}</td>
            <td class="p-3">{{ row.service_type }}</td>
            <td class="p-3">
              <span class="px-2 py-0.5 rounded-full text-xs" :class="statusClass[row.status]">{{ statusLabels[row.status] }}</span>
            </td>
            <td class="p-3 text-right">{{ row.quoted_amount ? formatMoney(+row.quoted_amount, app.currencySymbol) : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
