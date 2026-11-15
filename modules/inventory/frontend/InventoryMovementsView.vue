<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useListPage } from '@framework/composables/useListPage'
import { DEFAULT_MIN_DATE, minMonthFromDate, yearsFrom, startOfMonth, endOfMonth, startOfYear, endOfYear } from '@framework/utils/dates'
import { api } from '@framework/api/client'
import { formatQty, movementClass, movementLabels, type InventoryMovementRow } from '@framework/utils/catalog'

const { rows, loading, error, load } = useListPage<InventoryMovementRow>('/inventory/movements')
const period = ref<'month' | 'year'>('month')
const month = ref(new Date().toISOString().slice(0, 7))
const year = ref(String(new Date().getFullYear()))
const minMonth = ref(minMonthFromDate())
const years = ref(yearsFrom())

async function loadFiltered() {
  const params =
    period.value === 'year'
      ? { from: startOfYear(year.value), to: endOfYear(year.value) }
      : { from: startOfMonth(month.value), to: endOfMonth(month.value) }
  await load(params)
}

onMounted(async () => {
  const cfgRes = await api.get('/config')
  minMonth.value = minMonthFromDate(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  years.value = yearsFrom(cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE)
  await loadFiltered()
})
</script>

<template>
  <div>
    <div class="flex justify-between mb-6">
      <h1 class="text-2xl font-bold">Stock movements</h1>
      <RouterLink to="/inventory/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ New item</RouterLink>
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
            <th class="text-left p-3">Item</th>
            <th class="text-left p-3">Type</th>
            <th class="text-right p-3">Quantity</th>
            <th class="text-left p-3">Reason</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="rows.length === 0">
            <td colspan="5" class="p-6 text-center text-muted">No movements in this period.</td>
          </tr>
          <tr v-for="r in rows" :key="r.id" class="border-t">
            <td class="p-3 whitespace-nowrap">{{ String(r.date).slice(0, 10) }}</td>
            <td class="p-3">
              <RouterLink v-if="r.item?.id" :to="`/inventory/${r.item.id}/edit`" class="hover:text-brand">{{ r.item.name }}</RouterLink>
              <span v-else>—</span>
            </td>
            <td class="p-3" :class="movementClass[r.type]">{{ movementLabels[r.type] || r.type }}</td>
            <td class="p-3 text-right">{{ formatQty(r.quantity, r.item?.unit) }}</td>
            <td class="p-3 text-muted">{{ r.reason || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
