<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'

const route = useRoute()
const router = useRouter()
const app = useAppStore()
const meta = ref<any>({})
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const isNew = computed(() => route.name === 'menu-menus-new' || route.params.id === 'new')
const menuId = computed(() => (isNew.value ? null : String(route.params.id)))

const form = ref({
  name: '',
  description: '',
  menu_type: 'lunch',
  status: 'draft',
  valid_from: '',
  valid_until: '',
  available_days: [] as number[],
  available_time_start: '',
  available_time_end: '',
  seasonal: false,
  notes: '',
  service_type_ids: [] as number[],
  location_ids: [] as number[],
})

type Entry = {
  id?: number
  product_id: number | ''
  price: number | string
  featured: boolean
  available: boolean
  sort_order: number
}
type Section = { id?: number; name: string; category_id: number | ''; sort_order: number; entries: Entry[] }
const sections = ref<Section[]>([])

const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

onMounted(async () => {
  try {
    const { data: m } = await api.get('/menu-mgmt/menus/meta')
    meta.value = m
    if (menuId.value) {
      const { data } = await api.get(`/menu-mgmt/menus/${menuId.value}`)
      form.value = {
        name: data.name,
        description: data.description ?? '',
        menu_type: data.menu_type,
        status: data.status,
        valid_from: data.valid_from?.slice?.(0, 10) ?? '',
        valid_until: data.valid_until?.slice?.(0, 10) ?? '',
        available_days: data.available_days ?? [],
        available_time_start: String(data.available_time_start || '').slice(0, 5),
        available_time_end: String(data.available_time_end || '').slice(0, 5),
        seasonal: !!data.seasonal,
        notes: data.notes ?? '',
        service_type_ids: (data.service_types || []).map((s: any) => s.id),
        location_ids: (data.locations || []).map((l: any) => l.id),
      }
      sections.value = (data.sections || []).map((s: any, si: number) => ({
        id: s.id,
        name: s.name,
        category_id: s.category_id ?? '',
        sort_order: s.sort_order ?? si,
        entries: (s.entries || []).map((e: any, ei: number) => ({
          id: e.id,
          product_id: e.product_id,
          price: e.price ?? e.product?.default_price ?? 0,
          featured: !!e.featured,
          available: e.available !== false,
          sort_order: e.sort_order ?? ei,
        })),
      }))
    }
  } finally {
    loading.value = false
  }
})

function toggleDay(d: number) {
  const set = new Set(form.value.available_days)
  if (set.has(d)) set.delete(d)
  else set.add(d)
  form.value.available_days = [...set].sort()
}

function toggleId(list: number[], id: number) {
  return list.includes(id) ? list.filter((x) => x !== id) : [...list, id]
}

function addSection() {
  sections.value.push({ name: 'New section', category_id: '', sort_order: sections.value.length, entries: [] })
}

function removeSection(i: number) {
  sections.value.splice(i, 1)
}

function addProduct(si: number) {
  const first = meta.value.products?.[0]
  sections.value[si].entries.push({
    product_id: first?.id ?? '',
    price: first?.default_price ?? 0,
    featured: false,
    available: true,
    sort_order: sections.value[si].entries.length,
  })
}

function onProductChange(entry: Entry) {
  const p = (meta.value.products || []).find((x: any) => x.id === Number(entry.product_id))
  if (p && (entry.price === '' || entry.price === 0)) entry.price = p.default_price
}

function moveEntry(si: number, ei: number, dir: -1 | 1) {
  const list = sections.value[si].entries
  const ni = ei + dir
  if (ni < 0 || ni >= list.length) return
  ;[list[ei], list[ni]] = [list[ni], list[ei]]
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      ...form.value,
      valid_from: form.value.valid_from || null,
      valid_until: form.value.valid_until || null,
      available_time_start: form.value.available_time_start || null,
      available_time_end: form.value.available_time_end || null,
    }
    let id = menuId.value
    if (isNew.value) {
      const { data } = await api.post('/menu-mgmt/menus', payload)
      id = String(data.id)
    } else {
      await api.put(`/menu-mgmt/menus/${id}`, payload)
    }
    await api.put(`/menu-mgmt/menus/${id}/builder`, {
      sections: sections.value.map((s, si) => ({
        id: s.id,
        name: s.name,
        category_id: s.category_id || null,
        sort_order: si,
        entries: s.entries
          .filter((e) => e.product_id)
          .map((e, ei) => ({
            id: e.id,
            product_id: Number(e.product_id),
            price: e.price === '' ? null : Number(e.price),
            featured: e.featured,
            available: e.available,
            sort_order: ei,
          })),
      })),
    })
    await router.push(`/menu/menus/${id}`)
    if (isNew.value) await router.replace(`/menu/menus/${id}`)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not save menu'
  } finally {
    saving.value = false
  }
}

async function archive() {
  if (!menuId.value || !confirm('Archive this menu?')) return
  await api.delete(`/menu-mgmt/menus/${menuId.value}`)
  await router.push('/menu/menus')
}
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">{{ isNew ? 'Create menu' : form.name || 'Menu builder' }}</h1>
        <p class="text-muted text-sm">Add products by reference — no duplicates</p>
      </div>
      <div class="flex gap-2">
        <RouterLink to="/menu/menus" class="px-3 py-2 text-sm">Back</RouterLink>
        <button v-if="menuId" class="text-red-600 text-sm px-3 py-2" @click="archive">Archive</button>
        <button class="bg-brand text-white px-4 py-2 rounded-lg text-sm" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save Menu' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-muted">Loading…</div>
    <div v-else class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-1 space-y-4">
        <div v-if="error" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ error }}</div>
        <div class="bg-white rounded-xl p-4 shadow-sm space-y-3">
          <label class="block text-sm"><span class="font-medium">Name</span><input v-model="form.name" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
          <label class="block text-sm"><span class="font-medium">Type</span>
            <select v-model="form.menu_type" class="mt-1 w-full border rounded-lg px-3 py-2">
              <option v-for="(label, key) in meta.menu_types || {}" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
          <label class="block text-sm"><span class="font-medium">Status</span>
            <select v-model="form.status" class="mt-1 w-full border rounded-lg px-3 py-2">
              <option v-for="s in meta.statuses || []" :key="s" :value="s">{{ s }}</option>
            </select>
          </label>
          <label class="block text-sm"><span class="font-medium">Description</span><textarea v-model="form.description" rows="2" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
          <div class="grid grid-cols-2 gap-2">
            <label class="block text-sm"><span class="font-medium">From</span><input v-model="form.valid_from" type="date" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
            <label class="block text-sm"><span class="font-medium">Until</span><input v-model="form.valid_until" type="date" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <label class="block text-sm"><span class="font-medium">Start</span><input v-model="form.available_time_start" type="time" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
            <label class="block text-sm"><span class="font-medium">End</span><input v-model="form.available_time_end" type="time" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
          </div>
          <div>
            <div class="text-sm font-medium mb-1">Available days</div>
            <div class="flex flex-wrap gap-2 text-xs">
              <button
                v-for="(label, d) in dayLabels"
                :key="d"
                type="button"
                class="px-2 py-1 rounded border"
                :class="form.available_days.includes(d) ? 'bg-brand text-white border-brand' : ''"
                @click="toggleDay(d)"
              >{{ label }}</button>
            </div>
          </div>
          <div>
            <div class="text-sm font-medium mb-1">Services</div>
            <div class="flex flex-wrap gap-2 text-xs">
              <label v-for="s in meta.service_types || []" :key="s.id" class="flex items-center gap-1">
                <input
                  type="checkbox"
                  :checked="form.service_type_ids.includes(s.id)"
                  @change="form.service_type_ids = toggleId(form.service_type_ids, s.id)"
                />
                {{ s.name }}
              </label>
            </div>
          </div>
          <div>
            <div class="text-sm font-medium mb-1">Locations</div>
            <div class="flex flex-wrap gap-2 text-xs">
              <label v-for="l in meta.locations || []" :key="l.id" class="flex items-center gap-1">
                <input
                  type="checkbox"
                  :checked="form.location_ids.includes(l.id)"
                  @change="form.location_ids = toggleId(form.location_ids, l.id)"
                />
                {{ l.name }}
              </label>
            </div>
          </div>
          <label class="text-sm flex items-center gap-2"><input v-model="form.seasonal" type="checkbox" /> Seasonal</label>
        </div>
      </div>

      <div class="lg:col-span-2 space-y-4">
        <div class="flex justify-between items-center">
          <h2 class="font-semibold">Menu builder</h2>
          <button type="button" class="text-sm text-brand" @click="addSection">+ Section</button>
        </div>

        <div v-for="(section, si) in sections" :key="si" class="bg-white rounded-xl shadow-sm overflow-hidden">
          <div class="px-4 py-3 bg-stone-50 flex gap-2 items-center border-b">
            <input v-model="section.name" class="font-semibold bg-transparent border-b border-transparent focus:border-stone-300 flex-1 outline-none" />
            <button type="button" class="text-xs text-brand" @click="addProduct(si)">+ Product</button>
            <button type="button" class="text-xs text-red-600" @click="removeSection(si)">Remove</button>
          </div>
          <div v-for="(entry, ei) in section.entries" :key="ei" class="flex items-center gap-2 px-4 py-2 border-t text-sm">
            <span class="text-muted cursor-default select-none">☰</span>
            <select v-model="entry.product_id" class="border rounded-lg px-2 py-1.5 flex-1" @change="onProductChange(entry)">
              <option value="">Choose product</option>
              <option v-for="p in meta.products || []" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <input v-model="entry.price" type="number" step="0.01" class="border rounded-lg px-2 py-1.5 w-24 text-right" />
            <label class="text-xs flex items-center gap-1"><input v-model="entry.featured" type="checkbox" /> Feat.</label>
            <label class="text-xs flex items-center gap-1"><input v-model="entry.available" type="checkbox" /> On</label>
            <button type="button" class="text-xs text-muted" @click="moveEntry(si, ei, -1)">↑</button>
            <button type="button" class="text-xs text-muted" @click="moveEntry(si, ei, 1)">↓</button>
            <button type="button" class="text-xs text-red-600" @click="section.entries.splice(ei, 1)">✕</button>
          </div>
          <div v-if="!section.entries.length" class="px-4 py-6 text-sm text-muted text-center">No products — add from the product master.</div>
        </div>

        <div v-if="!sections.length" class="bg-white rounded-xl p-8 text-center text-muted">
          Add a section (e.g. Kerala Curries), then attach products.
        </div>

        <p class="text-xs text-muted">Menu-specific price overrides the product default for this menu only. Product record is unchanged.</p>
        <p v-if="sections.length" class="text-xs text-muted">
          Preview total listed:
          {{ formatMoney(sections.flatMap(s => s.entries).reduce((sum, e) => sum + (Number(e.price) || 0), 0), app.currencySymbol) }}
          (sum of line prices — not a package total)
        </p>
      </div>
    </div>
  </div>
</template>
