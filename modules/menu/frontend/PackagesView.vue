<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'

const app = useAppStore()
const route = useRoute()
const router = useRouter()
const rows = ref<any[]>([])
const products = ref<any[]>([])
const services = ref<any[]>([])
const loading = ref(true)
const editing = computed(() => route.params.id as string | undefined)
const isForm = computed(() => route.name === 'menu-packages-new' || route.name === 'menu-packages-edit')

const form = ref({
  name: '',
  description: '',
  people_count: 1 as number | string,
  price_per_person: '' as string | number,
  fixed_price: '' as string | number,
  status: 'draft',
  service_type_ids: [] as number[],
  items: [] as { product_id: number | ''; quantity: number | string }[],
})

async function loadList() {
  loading.value = true
  try {
    const [p, meta, settings] = await Promise.all([
      api.get('/menu-mgmt/packages'),
      api.get('/menu-mgmt/products', { params: { active: 1 } }),
      api.get('/menu-mgmt/settings'),
    ])
    rows.value = p.data
    products.value = meta.data
    services.value = settings.data.service_types || []
  } finally {
    loading.value = false
  }
}

async function loadForm() {
  loading.value = true
  try {
    await loadList()
    if (editing.value && route.name === 'menu-packages-edit') {
      const { data } = await api.get(`/menu-mgmt/packages/${editing.value}`)
      form.value = {
        name: data.name,
        description: data.description ?? '',
        people_count: data.people_count ?? 1,
        price_per_person: data.price_per_person ?? '',
        fixed_price: data.fixed_price ?? '',
        status: data.status,
        service_type_ids: (data.service_types || []).map((s: any) => s.id),
        items: (data.items || []).map((i: any) => ({ product_id: i.product_id, quantity: i.quantity })),
      }
    } else {
      form.value.items = [{ product_id: '', quantity: 1 }]
    }
  } finally {
    loading.value = false
  }
}

async function save() {
  const payload = {
    ...form.value,
    people_count: form.value.people_count === '' ? null : Number(form.value.people_count),
    price_per_person: form.value.price_per_person === '' ? null : Number(form.value.price_per_person),
    fixed_price: form.value.fixed_price === '' ? null : Number(form.value.fixed_price),
    items: form.value.items.filter((i) => i.product_id).map((i) => ({
      product_id: Number(i.product_id),
      quantity: Number(i.quantity),
    })),
  }
  if (route.name === 'menu-packages-edit') {
    await api.put(`/menu-mgmt/packages/${editing.value}`, payload)
  } else {
    await api.post('/menu-mgmt/packages', payload)
  }
  await router.push('/menu/packages')
}

onMounted(() => {
  if (isForm.value) loadForm()
  else loadList()
})
</script>

<template>
  <div v-if="!isForm">
    <div class="flex justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">Packages</h1>
        <p class="text-muted text-sm">Bundles of products with per-person or fixed pricing</p>
      </div>
      <RouterLink to="/menu/packages/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ Create Package</RouterLink>
    </div>
    <div v-if="loading" class="text-muted text-sm">Loading…</div>
    <div v-else class="space-y-3">
      <div v-for="p in rows" :key="p.id" class="bg-white rounded-xl p-4 shadow-sm">
        <div class="flex justify-between gap-3">
          <div>
            <div class="font-semibold">{{ p.name }}</div>
            <div class="text-xs text-muted mt-1">
              <span v-if="p.price_per_person">{{ formatMoney(+p.price_per_person, app.currencySymbol) }} / person</span>
              <span v-else-if="p.fixed_price">{{ formatMoney(+p.fixed_price, app.currencySymbol) }} fixed</span>
              · {{ (p.items || []).length }} items
            </div>
            <ul class="text-sm mt-2 text-muted list-disc pl-5">
              <li v-for="i in p.items || []" :key="i.id">{{ i.product?.name }} × {{ i.quantity }}</li>
            </ul>
          </div>
          <div class="flex flex-col items-end gap-2">
            <span class="text-xs px-2 py-0.5 rounded-full bg-stone-100">{{ p.status }}</span>
            <RouterLink :to="`/menu/packages/${p.id}/edit`" class="text-brand text-xs">Edit</RouterLink>
          </div>
        </div>
      </div>
      <div v-if="!rows.length" class="bg-white rounded-xl p-8 text-center text-muted">No packages yet.</div>
    </div>
  </div>

  <div v-else class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">{{ route.name === 'menu-packages-edit' ? 'Edit package' : 'Create package' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="save">
      <label class="block text-sm"><span class="font-medium">Name</span><input v-model="form.name" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label class="block text-sm"><span class="font-medium">Description</span><textarea v-model="form.description" rows="2" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-3 gap-3">
        <label class="block text-sm"><span class="font-medium">People</span><input v-model="form.people_count" type="number" min="1" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block text-sm"><span class="font-medium">€ / person</span><input v-model="form.price_per_person" type="number" step="0.01" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block text-sm"><span class="font-medium">Fixed €</span><input v-model="form.fixed_price" type="number" step="0.01" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block text-sm"><span class="font-medium">Status</span>
        <select v-model="form.status" class="mt-1 w-full border rounded-lg px-3 py-2">
          <option value="draft">draft</option>
          <option value="active">active</option>
          <option value="archived">archived</option>
        </select>
      </label>
      <div>
        <div class="text-sm font-medium mb-2">Items</div>
        <div v-for="(item, i) in form.items" :key="i" class="flex gap-2 mb-2">
          <select v-model="item.product_id" class="border rounded-lg px-2 py-2 flex-1">
            <option value="">Product</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <input v-model="item.quantity" type="number" step="0.01" min="0.01" class="border rounded-lg px-2 py-2 w-20" />
          <button type="button" class="text-red-600 text-xs" @click="form.items.splice(i, 1)">✕</button>
        </div>
        <button type="button" class="text-sm text-brand" @click="form.items.push({ product_id: '', quantity: 1 })">+ Add item</button>
      </div>
      <div class="flex justify-end gap-3">
        <RouterLink to="/menu/packages" class="px-4 py-2">Cancel</RouterLink>
        <button class="bg-brand text-white px-4 py-2 rounded-lg">Save</button>
      </div>
    </form>
  </div>
</template>
