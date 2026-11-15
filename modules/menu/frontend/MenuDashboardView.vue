<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@framework/api/client'

type Dash = {
  active_products: number
  unavailable_products: number
  active_menus: number
  draft_menus: number
  seasonal_menus: number
  active_packages: number
  recent_menus: { id: number; name: string; status: string; menu_type: string; updated_at: string }[]
}

const data = ref<Dash | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const { data: res } = await api.get('/menu-mgmt/dashboard')
    data.value = res
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not load dashboard'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Menu Management</h1>
        <p class="text-muted text-sm">Products · Menus · Packages — reusable across lunch, truck, catering & online</p>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
      <RouterLink to="/menu/products/new" class="bg-brand text-white px-3 py-2 rounded-lg text-sm">+ Add Product</RouterLink>
      <RouterLink to="/menu/menus/new" class="border border-stone-300 px-3 py-2 rounded-lg text-sm hover:bg-stone-50">+ Create Menu</RouterLink>
      <RouterLink to="/menu/packages/new" class="border border-stone-300 px-3 py-2 rounded-lg text-sm hover:bg-stone-50">+ Create Package</RouterLink>
      <RouterLink to="/menu/categories" class="border border-stone-300 px-3 py-2 rounded-lg text-sm hover:bg-stone-50">Manage Categories</RouterLink>
      <RouterLink to="/menu/pricing" class="border border-stone-300 px-3 py-2 rounded-lg text-sm hover:bg-stone-50">Manage Prices</RouterLink>
      <RouterLink to="/menu/availability" class="border border-stone-300 px-3 py-2 rounded-lg text-sm hover:bg-stone-50">Manage Availability</RouterLink>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>
    <div v-else-if="error" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ error }}</div>
    <template v-else-if="data">
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Active products</div>
          <div class="text-2xl font-bold mt-1">{{ data.active_products }}</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Active menus</div>
          <div class="text-2xl font-bold mt-1">{{ data.active_menus }}</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Draft menus</div>
          <div class="text-2xl font-bold mt-1">{{ data.draft_menus }}</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Seasonal menus</div>
          <div class="text-2xl font-bold mt-1">{{ data.seasonal_menus }}</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Unavailable</div>
          <div class="text-2xl font-bold mt-1">{{ data.unavailable_products }}</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <div class="text-xs text-muted uppercase">Active packages</div>
          <div class="text-2xl font-bold mt-1">{{ data.active_packages }}</div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <h2 class="px-4 py-3 font-semibold text-sm border-b">Recently modified menus</h2>
        <table class="w-full text-sm">
          <thead class="text-muted text-xs uppercase">
            <tr>
              <th class="text-left p-3">Menu</th>
              <th class="text-left p-3">Type</th>
              <th class="text-left p-3">Status</th>
              <th class="text-left p-3">Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in data.recent_menus" :key="m.id" class="border-t">
              <td class="p-3 font-medium">{{ m.name }}</td>
              <td class="p-3 text-muted">{{ m.menu_type }}</td>
              <td class="p-3"><span class="px-2 py-0.5 rounded-full text-xs bg-stone-100">{{ m.status }}</span></td>
              <td class="p-3 text-muted">{{ m.updated_at?.slice(0, 16).replace('T', ' ') }}</td>
              <td class="p-3"><RouterLink :to="`/menu/menus/${m.id}`" class="text-brand text-xs">Open</RouterLink></td>
            </tr>
            <tr v-if="!data.recent_menus.length">
              <td colspan="5" class="p-6 text-center text-muted">No menus yet — create one to get started.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
