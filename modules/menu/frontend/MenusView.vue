<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@framework/api/client'

const rows = ref<any[]>([])
const meta = ref<any>({})
const status = ref('')
const menuType = ref('')
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (status.value) params.status = status.value
    if (menuType.value) params.menu_type = menuType.value
    const [list, m] = await Promise.all([
      api.get('/menu-mgmt/menus', { params }),
      api.get('/menu-mgmt/menus/meta'),
    ])
    rows.value = list.data
    meta.value = m.data
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Menus</h1>
        <p class="text-muted text-sm">Lunch, market, catering, events — products stay shared</p>
      </div>
      <RouterLink to="/menu/menus/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ Create Menu</RouterLink>
    </div>

    <div class="flex gap-2 mb-4 flex-wrap">
      <select v-model="status" class="border rounded-lg px-3 py-2 text-sm" @change="load">
        <option value="">All statuses</option>
        <option v-for="s in meta.statuses || []" :key="s" :value="s">{{ s }}</option>
      </select>
      <select v-model="menuType" class="border rounded-lg px-3 py-2 text-sm" @change="load">
        <option value="">All types</option>
        <option v-for="(label, key) in meta.menu_types || {}" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>
    <div v-else class="space-y-3">
      <RouterLink
        v-for="m in rows"
        :key="m.id"
        :to="`/menu/menus/${m.id}`"
        class="block bg-white rounded-xl p-4 shadow-sm border border-transparent hover:border-brand"
      >
        <div class="flex justify-between gap-3">
          <div>
            <div class="font-semibold">{{ m.name }}</div>
            <div class="text-xs text-muted mt-1">
              {{ meta.menu_types?.[m.menu_type] || m.menu_type }}
              <span v-if="m.available_days?.length"> · days {{ m.available_days.join(',') }}</span>
              <span v-if="m.available_time_start"> · {{ String(m.available_time_start).slice(0,5) }}–{{ String(m.available_time_end || '').slice(0,5) }}</span>
            </div>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full h-fit bg-stone-100">{{ m.status }}</span>
        </div>
      </RouterLink>
      <div v-if="!rows.length" class="bg-white rounded-xl p-8 text-center text-muted">No menus yet.</div>
    </div>
  </div>
</template>
