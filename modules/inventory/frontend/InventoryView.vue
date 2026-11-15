<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { useListPage } from '@framework/composables/useListPage'
import { formatQty, type InventoryItemRow } from '@framework/utils/catalog'

const app = useAppStore()
const { rows, loading, error, load } = useListPage<InventoryItemRow>('/inventory')
const meta = ref<{ categories?: string[] }>({})
const category = ref('')
const query = ref('')
const lowOnly = ref(false)

const counts = computed(() => {
  const active = rows.value.filter((r) => r.active)
  return {
    items: active.length,
    low: active.filter((r) => r.low_stock).length,
    value: active.reduce((sum, r) => sum + Number(r.stock_value ?? 0), 0),
  }
})

async function loadFiltered() {
  const params: Record<string, string | number | boolean> = {}
  if (category.value) params.category = category.value
  if (query.value.trim()) params.q = query.value.trim()
  if (lowOnly.value) params.low_stock = 1
  await load(params)
}

onMounted(async () => {
  const { data } = await api.get('/inventory/meta')
  meta.value = data
  await loadFiltered()
})

async function remove(id: number) {
  if (!confirm('Delete this stock item?')) return
  try {
    await api.delete(`/inventory/${id}`)
    rows.value = rows.value.filter((r) => r.id !== id)
  } catch (e: any) {
    alert(e?.response?.data?.message ?? 'Could not delete item.')
  }
}
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Inventory</h1>
        <p class="text-muted text-sm">Ingredients, packaging, and other stock</p>
      </div>
      <RouterLink to="/inventory/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ New item</RouterLink>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="text-xs text-muted uppercase">Items</div>
        <div class="text-2xl font-semibold mt-1">{{ counts.items }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="text-xs text-muted uppercase">Low stock</div>
        <div class="text-2xl font-semibold mt-1" :class="counts.low ? 'text-amber-700' : ''">{{ counts.low }}</div>
      </div>
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="text-xs text-muted uppercase">Stock value</div>
        <div class="text-2xl font-semibold mt-1">{{ formatMoney(counts.value, app.currencySymbol) }}</div>
      </div>
    </div>

    <div class="flex items-center gap-3 mb-4 flex-wrap">
      <input v-model="query" type="search" placeholder="Search name or SKU" class="border rounded-lg px-3 py-2 w-56" @change="loadFiltered" />
      <select v-model="category" class="border rounded-lg px-3 py-2" @change="loadFiltered">
        <option value="">All categories</option>
        <option v-for="c in meta.categories" :key="c" :value="c">{{ c }}</option>
      </select>
      <label class="text-sm flex items-center gap-2">
        <input v-model="lowOnly" type="checkbox" @change="loadFiltered" />
        Low stock only
      </label>
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
            <th class="text-left p-3">Item</th>
            <th class="text-left p-3">Category</th>
            <th class="text-right p-3">On hand</th>
            <th class="text-right p-3">Reorder at</th>
            <th class="text-right p-3">Unit cost</th>
            <th class="text-right p-3">Value</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="rows.length === 0">
            <td colspan="7" class="p-6 text-center text-muted">No stock items yet.</td>
          </tr>
          <tr v-for="r in rows" :key="r.id" class="border-t" :class="r.low_stock ? 'bg-amber-50/60' : ''">
            <td class="p-3">
              <div class="font-medium">{{ r.name }}</div>
              <div class="text-xs text-muted">{{ r.sku || r.supplier_name || '—' }}</div>
            </td>
            <td class="p-3">{{ r.category }}</td>
            <td class="p-3 text-right font-medium" :class="r.low_stock ? 'text-amber-800' : ''">{{ formatQty(r.quantity, r.unit) }}</td>
            <td class="p-3 text-right">{{ Number(r.reorder_level) > 0 ? formatQty(r.reorder_level, r.unit) : '—' }}</td>
            <td class="p-3 text-right">{{ r.unit_cost ? formatMoney(+r.unit_cost, app.currencySymbol) : '—' }}</td>
            <td class="p-3 text-right">{{ formatMoney(+r.stock_value, app.currencySymbol) }}</td>
            <td class="p-3 whitespace-nowrap">
              <RouterLink :to="`/inventory/${r.id}/edit`" class="text-brand text-xs mr-3">Edit</RouterLink>
              <button class="text-red-600 text-xs" @click="remove(r.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
