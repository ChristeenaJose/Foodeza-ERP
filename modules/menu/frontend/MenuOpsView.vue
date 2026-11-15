<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'

const app = useAppStore()
const route = useRoute()
const addons = ref<any[]>([])
const prices = ref<any[]>([])
const rules = ref<any[]>([])
const settings = ref<any>({ service_types: [], locations: [], allergens: [] })
const mode = ref<'addons' | 'pricing' | 'availability' | 'settings'>('addons')
const loading = ref(true)

const newAddon = ref({ name: '', price: 0 })
const newService = ref('')
const newLocation = ref('')
const newPrice = ref({
  product_id: '' as string | number,
  price_type: 'service',
  service_type_id: '' as string | number,
  price: '' as string | number,
})
const products = ref<any[]>([])

function syncMode() {
  const m = route.meta.opsMode as typeof mode.value | undefined
  if (m) mode.value = m
}

watch(() => route.meta.opsMode, syncMode, { immediate: true })

async function load() {
  loading.value = true
  try {
    const [a, p, r, s, prod] = await Promise.all([
      api.get('/menu-mgmt/addons'),
      api.get('/menu-mgmt/pricing'),
      api.get('/menu-mgmt/availability-rules'),
      api.get('/menu-mgmt/settings'),
      api.get('/menu-mgmt/products', { params: { active: 1 } }),
    ])
    addons.value = a.data
    prices.value = p.data
    rules.value = r.data
    settings.value = s.data
    products.value = prod.data
  } finally {
    loading.value = false
  }
}

async function addAddon() {
  if (!newAddon.value.name) return
  await api.post('/menu-mgmt/addons', { name: newAddon.value.name, price: Number(newAddon.value.price) })
  newAddon.value = { name: '', price: 0 }
  await load()
}

async function addService() {
  if (!newService.value.trim()) return
  await api.post('/menu-mgmt/settings/service-types', { name: newService.value.trim() })
  newService.value = ''
  await load()
}

async function addLocation() {
  if (!newLocation.value.trim()) return
  await api.post('/menu-mgmt/settings/locations', { name: newLocation.value.trim() })
  newLocation.value = ''
  await load()
}

async function addPriceRow() {
  if (!newPrice.value.product_id || newPrice.value.price === '') return
  await api.post(`/menu-mgmt/products/${newPrice.value.product_id}/prices`, {
    price_type: newPrice.value.price_type,
    service_type_id: newPrice.value.service_type_id || null,
    price: Number(newPrice.value.price),
  })
  newPrice.value = { product_id: '', price_type: 'service', service_type_id: '', price: '' }
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-4">{{ mode === 'addons' ? 'Add-ons' : mode === 'pricing' ? 'Pricing' : mode === 'availability' ? 'Availability' : 'Settings' }}</h1>
    <div class="flex gap-2 mb-6 flex-wrap text-sm">
      <button class="px-3 py-1.5 rounded-lg border" :class="mode==='addons' && 'bg-brand text-white border-brand'" @click="mode='addons'">Add-ons</button>
      <button class="px-3 py-1.5 rounded-lg border" :class="mode==='pricing' && 'bg-brand text-white border-brand'" @click="mode='pricing'">Pricing</button>
      <button class="px-3 py-1.5 rounded-lg border" :class="mode==='availability' && 'bg-brand text-white border-brand'" @click="mode='availability'">Availability</button>
      <button class="px-3 py-1.5 rounded-lg border" :class="mode==='settings' && 'bg-brand text-white border-brand'" @click="mode='settings'">Settings</button>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <template v-else-if="mode==='addons'">
      <form class="flex gap-2 mb-4" @submit.prevent="addAddon">
        <input v-model="newAddon.name" placeholder="Add-on name" class="border rounded-lg px-3 py-2 flex-1" />
        <input v-model.number="newAddon.price" type="number" step="0.01" class="border rounded-lg px-3 py-2 w-28" />
        <button class="bg-brand text-white px-4 py-2 rounded-lg">Add</button>
      </form>
      <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <tbody>
            <tr v-for="a in addons" :key="a.id" class="border-t">
              <td class="p-3 font-medium">{{ a.name }}</td>
              <td class="p-3 text-right">{{ formatMoney(+a.price, app.currencySymbol) }}</td>
              <td class="p-3 text-muted text-xs">{{ a.active ? 'active' : 'off' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <template v-else-if="mode==='pricing'">
      <form class="flex flex-wrap gap-2 mb-4 text-sm" @submit.prevent="addPriceRow">
        <select v-model="newPrice.product_id" class="border rounded-lg px-3 py-2">
          <option value="">Product</option>
          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select v-model="newPrice.price_type" class="border rounded-lg px-3 py-2">
          <option value="standard">standard</option>
          <option value="service">service</option>
          <option value="location">location</option>
          <option value="seasonal">seasonal</option>
          <option value="promo">promo</option>
          <option value="catering_per_person">catering / person</option>
        </select>
        <select v-model="newPrice.service_type_id" class="border rounded-lg px-3 py-2">
          <option value="">Service</option>
          <option v-for="s in settings.service_types" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <input v-model="newPrice.price" type="number" step="0.01" placeholder="Price" class="border rounded-lg px-3 py-2 w-28" />
        <button class="bg-brand text-white px-4 py-2 rounded-lg">Add price</button>
      </form>
      <p class="text-xs text-muted mb-3">Prices are append-only history — older rows stay for audit.</p>
      <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead class="text-xs text-muted uppercase bg-stone-50">
            <tr>
              <th class="text-left p-3">Product</th>
              <th class="text-left p-3">Type</th>
              <th class="text-left p-3">Service</th>
              <th class="text-right p-3">Price</th>
              <th class="text-left p-3">When</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in prices" :key="row.id" class="border-t">
              <td class="p-3">{{ row.product?.name }}</td>
              <td class="p-3 text-muted">{{ row.price_type }}</td>
              <td class="p-3 text-muted">{{ row.service_type?.name || '—' }}</td>
              <td class="p-3 text-right font-medium">{{ formatMoney(+row.price, app.currencySymbol) }}</td>
              <td class="p-3 text-muted text-xs">{{ row.created_at?.slice(0, 16).replace('T', ' ') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <template v-else-if="mode==='availability'">
      <p class="text-sm text-muted mb-4">Rules by date range, day of week, time, location and service. Attach via API / product edit later — current rules:</p>
      <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <tbody>
            <tr v-for="r in rules" :key="r.id" class="border-t">
              <td class="p-3 text-xs text-muted">{{ r.subject_type?.split('\\').pop() }} #{{ r.subject_id }}</td>
              <td class="p-3">{{ (r.days_of_week || []).join(',') || 'any day' }}</td>
              <td class="p-3">{{ r.start_time || '—' }}–{{ r.end_time || '—' }}</td>
              <td class="p-3 text-muted">{{ r.service_type?.name || '—' }} / {{ r.location?.name || '—' }}</td>
            </tr>
            <tr v-if="!rules.length"><td class="p-8 text-center text-muted" colspan="4">No availability rules yet.</td></tr>
          </tbody>
        </table>
      </div>
    </template>

    <template v-else>
      <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <h2 class="font-semibold mb-3">Service types</h2>
          <form class="flex gap-2 mb-3" @submit.prevent="addService">
            <input v-model="newService" class="border rounded-lg px-3 py-2 flex-1 text-sm" placeholder="e.g. Lunch" />
            <button class="bg-brand text-white px-3 py-2 rounded-lg text-sm">Add</button>
          </form>
          <ul class="text-sm space-y-1">
            <li v-for="s in settings.service_types" :key="s.id" class="flex justify-between border-t py-2">
              <span>{{ s.name }}</span>
              <span class="text-xs text-muted">{{ s.active ? 'on' : 'off' }}</span>
            </li>
          </ul>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm">
          <h2 class="font-semibold mb-3">Locations</h2>
          <form class="flex gap-2 mb-3" @submit.prevent="addLocation">
            <input v-model="newLocation" class="border rounded-lg px-3 py-2 flex-1 text-sm" placeholder="e.g. Durlach" />
            <button class="bg-brand text-white px-3 py-2 rounded-lg text-sm">Add</button>
          </form>
          <ul class="text-sm space-y-1">
            <li v-for="l in settings.locations" :key="l.id" class="flex justify-between border-t py-2">
              <span>{{ l.name }}</span>
              <span class="text-xs text-muted">{{ l.active ? 'on' : 'off' }}</span>
            </li>
          </ul>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm md:col-span-2">
          <h2 class="font-semibold mb-3">Allergens (configurable)</h2>
          <div class="flex flex-wrap gap-2 text-sm">
            <span v-for="a in settings.allergens" :key="a.id" class="px-2 py-1 rounded-full bg-stone-100">{{ a.name }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
