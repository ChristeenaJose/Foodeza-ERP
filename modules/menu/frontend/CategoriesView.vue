<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@framework/api/client'

const rows = ref<any[]>([])
const name = ref('')
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/menu-mgmt/categories')
    rows.value = data
  } finally {
    loading.value = false
  }
}

async function add() {
  if (!name.value.trim()) return
  await api.post('/menu-mgmt/categories', { name: name.value.trim() })
  name.value = ''
  await load()
}

async function toggle(row: any) {
  await api.put(`/menu-mgmt/categories/${row.id}`, { name: row.name, active: !row.active })
  await load()
}

async function archive(row: any) {
  if (!confirm(`Archive category “${row.name}”?`)) return
  await api.delete(`/menu-mgmt/categories/${row.id}`)
  await load()
}

async function move(index: number, dir: -1 | 1) {
  const next = index + dir
  if (next < 0 || next >= rows.value.length) return
  const order = rows.value.map((r) => r.id)
  ;[order[index], order[next]] = [order[next], order[index]]
  await api.post('/menu-mgmt/categories/reorder', { order })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-2">Categories</h1>
    <p class="text-muted text-sm mb-6">Configurable product/menu categories — not hard-coded</p>

    <form class="flex gap-2 mb-4" @submit.prevent="add">
      <input v-model="name" placeholder="New category name" class="border rounded-lg px-3 py-2 flex-1" />
      <button class="bg-brand text-white px-4 py-2 rounded-lg">Add</button>
    </form>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>
    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="text-xs text-muted uppercase bg-stone-50">
          <tr>
            <th class="text-left p-3">Category</th>
            <th class="text-left p-3">Status</th>
            <th class="p-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in rows" :key="r.id" class="border-t">
            <td class="p-3 font-medium">{{ r.name }}</td>
            <td class="p-3">
              <span class="text-xs px-2 py-0.5 rounded-full" :class="r.active && !r.archived_at ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100'">
                {{ r.archived_at ? 'archived' : r.active ? 'active' : 'disabled' }}
              </span>
            </td>
            <td class="p-3 text-right whitespace-nowrap space-x-2">
              <button class="text-xs text-muted" @click="move(i, -1)">↑</button>
              <button class="text-xs text-muted" @click="move(i, 1)">↓</button>
              <button class="text-xs text-brand" @click="toggle(r)">{{ r.active ? 'Disable' : 'Enable' }}</button>
              <button class="text-xs text-red-600" @click="archive(r)">Archive</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
