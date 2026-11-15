<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'

const app = useAppStore()
const rows = ref<any[]>([])
const meta = ref<any>({})
const loading = ref(true)
const q = ref('')
const categoryId = ref('')
const status = ref('')
const diet = ref('')
const selected = ref<number[]>([])

async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number | boolean> = {}
    if (q.value) params.q = q.value
    if (categoryId.value) params.category_id = Number(categoryId.value)
    if (status.value === 'active') params.active = 1
    if (status.value === 'inactive') params.active = 0
    if (diet.value === 'vegetarian') params.vegetarian = 1
    if (diet.value === 'vegan') params.vegan = 1
    if (diet.value === 'halal') params.halal = 1
    const [list, m] = await Promise.all([
      api.get('/menu-mgmt/products', { params }),
      api.get('/menu-mgmt/products/meta'),
    ])
    rows.value = list.data
    meta.value = m.data
  } finally {
    loading.value = false
  }
}

function toggle(id: number) {
  selected.value = selected.value.includes(id)
    ? selected.value.filter((x) => x !== id)
    : [...selected.value, id]
}

async function bulk(action: string, extra: Record<string, unknown> = {}) {
  if (!selected.value.length) return
  if (action === 'archive' && !confirm(`Archive ${selected.value.length} product(s)?`)) return
  await api.post('/menu-mgmt/products/bulk', { ids: selected.value, action, ...extra })
  selected.value = []
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Products</h1>
        <p class="text-muted text-sm">Product master — reuse across menus, packages, POS & online</p>
      </div>
      <RouterLink to="/menu/products/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ Add Product</RouterLink>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
      <input v-model="q" placeholder="Search products…" class="border rounded-lg px-3 py-2 text-sm min-w-[200px]" @keyup.enter="load" />
      <select v-model="categoryId" class="border rounded-lg px-3 py-2 text-sm" @change="load">
        <option value="">Category</option>
        <option v-for="c in meta.categories || []" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
      </select>
      <select v-model="status" class="border rounded-lg px-3 py-2 text-sm" @change="load">
        <option value="">Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <select v-model="diet" class="border rounded-lg px-3 py-2 text-sm" @change="load">
        <option value="">Diet</option>
        <option value="vegetarian">Vegetarian</option>
        <option value="vegan">Vegan</option>
        <option value="halal">Halal</option>
      </select>
      <button class="border rounded-lg px-3 py-2 text-sm" @click="load">Search</button>
    </div>

    <div v-if="selected.length" class="mb-3 flex flex-wrap gap-2 items-center text-sm">
      <span class="text-muted">{{ selected.length }} selected</span>
      <button class="px-2 py-1 rounded bg-emerald-50 text-emerald-800" @click="bulk('activate')">Activate</button>
      <button class="px-2 py-1 rounded bg-stone-100" @click="bulk('deactivate')">Deactivate</button>
      <button class="px-2 py-1 rounded bg-amber-50 text-amber-800" @click="bulk('set_availability', { availability_status: 'unavailable' })">Mark unavailable</button>
      <button class="px-2 py-1 rounded bg-red-50 text-red-700" @click="bulk('archive')">Archive</button>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>
    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="text-muted text-xs uppercase bg-stone-50">
          <tr>
            <th class="p-3 w-8"></th>
            <th class="text-left p-3">Product</th>
            <th class="text-left p-3">Category</th>
            <th class="text-right p-3">Price</th>
            <th class="text-left p-3">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id" class="border-t">
            <td class="p-3"><input type="checkbox" :checked="selected.includes(r.id)" @change="toggle(r.id)" /></td>
            <td class="p-3">
              <div class="font-medium">{{ r.name }}</div>
              <div class="text-xs text-muted">{{ r.sku }}</div>
            </td>
            <td class="p-3 text-muted">{{ r.category?.name ?? '—' }}</td>
            <td class="p-3 text-right font-medium">{{ formatMoney(+r.default_price, app.currencySymbol) }}</td>
            <td class="p-3">
              <span class="px-2 py-0.5 rounded-full text-xs" :class="r.active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100'">
                {{ r.active ? r.availability_status : 'inactive' }}
              </span>
            </td>
            <td class="p-3 whitespace-nowrap">
              <RouterLink :to="`/menu/products/${r.id}/edit`" class="text-brand text-xs">Edit</RouterLink>
            </td>
          </tr>
          <tr v-if="!rows.length"><td colspan="6" class="p-8 text-center text-muted">No products found.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
