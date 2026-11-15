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
const editId = computed(() => route.params.id as string | undefined)

const form = ref({
  sku: '',
  name: '',
  short_name: '',
  description: '',
  category_id: '' as string | number,
  subcategory: '',
  food_type: 'non_vegetarian',
  is_vegetarian: false,
  is_vegan: false,
  is_halal: false,
  active: true,
  seasonal: false,
  kitchen_station: '',
  prep_time_minutes: '' as string | number,
  default_price: '' as string | number,
  vat_rate: 0.07 as string | number,
  availability_status: 'available',
  notes: '',
  allergen_ids: [] as number[],
})

onMounted(async () => {
  try {
    const { data } = await api.get('/menu-mgmt/products/meta')
    meta.value = data
    if (editId.value) {
      const { data: p } = await api.get(`/menu-mgmt/products/${editId.value}`)
      form.value = {
        sku: p.sku ?? '',
        name: p.name,
        short_name: p.short_name ?? '',
        description: p.description ?? '',
        category_id: p.category_id ?? '',
        subcategory: p.subcategory ?? '',
        food_type: p.food_type,
        is_vegetarian: !!p.is_vegetarian,
        is_vegan: !!p.is_vegan,
        is_halal: !!p.is_halal,
        active: !!p.active,
        seasonal: !!p.seasonal,
        kitchen_station: p.kitchen_station ?? '',
        prep_time_minutes: p.prep_time_minutes ?? '',
        default_price: p.default_price,
        vat_rate: p.vat_rate ?? 0.07,
        availability_status: p.availability_status,
        notes: p.notes ?? '',
        allergen_ids: (p.allergens || []).map((a: any) => a.id),
      }
    }
  } finally {
    loading.value = false
  }
})

function toggleAllergen(id: number) {
  const set = new Set(form.value.allergen_ids)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  form.value.allergen_ids = [...set]
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      ...form.value,
      category_id: form.value.category_id ? Number(form.value.category_id) : null,
      prep_time_minutes: form.value.prep_time_minutes === '' ? null : Number(form.value.prep_time_minutes),
      default_price: Number(form.value.default_price),
      vat_rate: form.value.vat_rate === '' ? null : Number(form.value.vat_rate),
    }
    if (editId.value) {
      await api.put(`/menu-mgmt/products/${editId.value}`, payload)
    } else {
      await api.post('/menu-mgmt/products', payload)
    }
    await router.push('/menu/products')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not save product'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">{{ editId ? 'Edit product' : 'Add product' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="error" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ error }}</div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block col-span-2"><span class="text-sm font-medium">Name</span><input v-model="form.name" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">SKU</span><input v-model="form.sku" placeholder="Auto if empty" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Short name</span><input v-model="form.short_name" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Category</span>
          <select v-model="form.category_id" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option value="">—</option>
            <option v-for="c in meta.categories || []" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label class="block"><span class="text-sm font-medium">Default price</span><input v-model="form.default_price" type="number" step="0.01" min="0" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Food type</span>
          <select v-model="form.food_type" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option value="non_vegetarian">Non-vegetarian</option>
            <option value="vegetarian">Vegetarian</option>
            <option value="vegan">Vegan</option>
          </select>
        </label>
        <label class="block"><span class="text-sm font-medium">Availability</span>
          <select v-model="form.availability_status" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option value="available">Available</option>
            <option value="unavailable">Temporarily unavailable</option>
            <option value="sold_out">Sold out</option>
          </select>
        </label>
      </div>
      <label class="block"><span class="text-sm font-medium">Description</span><textarea v-model="form.description" rows="2" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="flex flex-wrap gap-4 text-sm">
        <label class="flex items-center gap-2"><input v-model="form.is_vegetarian" type="checkbox" /> Vegetarian</label>
        <label class="flex items-center gap-2"><input v-model="form.is_vegan" type="checkbox" /> Vegan</label>
        <label class="flex items-center gap-2"><input v-model="form.is_halal" type="checkbox" /> Halal</label>
        <label class="flex items-center gap-2"><input v-model="form.seasonal" type="checkbox" /> Seasonal</label>
        <label class="flex items-center gap-2"><input v-model="form.active" type="checkbox" /> Active</label>
      </div>
      <div>
        <div class="text-sm font-medium mb-2">Allergens</div>
        <div class="flex flex-wrap gap-3 text-sm">
          <label v-for="a in meta.allergens || []" :key="a.id" class="flex items-center gap-2">
            <input type="checkbox" :checked="form.allergen_ids.includes(a.id)" @change="toggleAllergen(a.id)" />
            {{ a.name }}
          </label>
        </div>
      </div>
      <label class="block"><span class="text-sm font-medium">Kitchen station</span><input v-model="form.kitchen_station" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label class="block"><span class="text-sm font-medium">Notes</span><textarea v-model="form.notes" rows="2" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="flex gap-3 justify-end">
        <RouterLink to="/menu/products" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
      </div>
      <p v-if="editId && form.default_price !== ''" class="text-xs text-muted">List price {{ formatMoney(+form.default_price || 0, app.currencySymbol) }} — add service prices under Pricing.</p>
    </form>
  </div>
</template>
