<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { type InventoryItemRow } from '@framework/utils/catalog'

const route = useRoute()
const router = useRouter()
const app = useAppStore()
const meta = ref<{ categories?: string[]; services?: string[] }>({})
const stock = ref<InventoryItemRow[]>([])
const loading = ref(false)
const saving = ref(false)
const saveError = ref('')

const editId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => Boolean(editId.value))

const form = ref({
  name: '',
  description: '',
  category: '',
  menu_group: '',
  service_types: [] as string[],
  price: '' as string | number,
  available: true,
  allergens: '',
  notes: '',
})

const ingredients = ref<{ inventory_item_id: string; quantity: string }[]>([])

const recipeCost = computed(() =>
  ingredients.value.reduce((sum, line) => {
    const item = stock.value.find((s) => String(s.id) === String(line.inventory_item_id))
    return sum + (Number(line.quantity) || 0) * Number(item?.unit_cost ?? 0)
  }, 0)
)

onMounted(async () => {
  loading.value = true
  try {
    const [metaRes, stockRes] = await Promise.all([api.get('/menu/meta'), api.get('/inventory')])
    meta.value = metaRes.data
    stock.value = stockRes.data
    form.value.category = metaRes.data.categories?.[0] ?? 'Main'
    form.value.service_types = metaRes.data.services ? [metaRes.data.services[0]] : []

    if (isEdit.value) {
      const { data } = await api.get(`/menu/${editId.value}`)
      form.value = {
        name: data.name,
        description: data.description ?? '',
        category: data.category,
        menu_group: data.menu_group ?? '',
        service_types: data.service_types ?? [],
        price: data.price,
        available: data.available,
        allergens: data.allergens ?? '',
        notes: data.notes ?? '',
      }
      ingredients.value = (data.ingredients ?? []).map((line: any) => ({
        inventory_item_id: String(line.inventory_item_id),
        quantity: String(line.quantity),
      }))
    }
  } finally {
    loading.value = false
  }
})

function toggleService(service: string) {
  const current = form.value.service_types
  form.value.service_types = current.includes(service)
    ? current.filter((s) => s !== service)
    : [...current, service]
}

function addIngredient() {
  ingredients.value.push({ inventory_item_id: stock.value[0] ? String(stock.value[0].id) : '', quantity: '' })
}

function removeIngredient(index: number) {
  ingredients.value.splice(index, 1)
}

function unitFor(id: string) {
  return stock.value.find((s) => String(s.id) === String(id))?.unit ?? ''
}

async function submit() {
  saving.value = true
  saveError.value = ''
  try {
    const payload = {
      ...form.value,
      menu_group: form.value.menu_group.trim() || null,
      price: +form.value.price,
      ingredients: ingredients.value
        .filter((line) => line.inventory_item_id && line.quantity !== '')
        .map((line) => ({ inventory_item_id: +line.inventory_item_id, quantity: +line.quantity })),
    }
    if (isEdit.value) {
      await api.put(`/menu/${editId.value}`, payload)
    } else {
      await api.post('/menu', payload)
    }
    await router.push('/menu')
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Could not save menu item.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit dish' : 'New dish' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="saveError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ saveError }}</div>
      <label class="block"><span class="text-sm font-medium">Name</span><input v-model="form.name" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label class="block"><span class="text-sm font-medium">Description</span><textarea v-model="form.description" rows="2" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Category</span>
          <select v-model="form.category" required class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="c in meta.categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </label>
        <label class="block"><span class="text-sm font-medium">Price</span><input v-model="form.price" type="number" step="0.01" min="0" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block">
        <span class="text-sm font-medium">Option group</span>
        <input
          v-model="form.menu_group"
          placeholder="e.g. Appam Special, Cutlet, Kappa Special"
          class="mt-1 w-full border rounded-lg px-3 py-2"
        />
        <span class="text-xs text-muted mt-1 block">Groups options under one heading in the menu list</span>
      </label>
      <fieldset>
        <legend class="text-sm font-medium mb-2">Services</legend>
        <div class="flex flex-wrap gap-3">
          <label v-for="s in meta.services" :key="s" class="text-sm flex items-center gap-2">
            <input type="checkbox" :checked="form.service_types.includes(s)" @change="toggleService(s)" />
            {{ s }}
          </label>
        </div>
      </fieldset>
      <label class="block"><span class="text-sm font-medium">Allergens</span><input v-model="form.allergens" placeholder="e.g. gluten, nuts" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label class="block"><span class="text-sm font-medium">Notes</span><input v-model="form.notes" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label class="text-sm flex items-center gap-2">
        <input v-model="form.available" type="checkbox" />
        Available
      </label>

      <div class="border-t pt-4">
        <div class="flex justify-between items-center mb-3">
          <h2 class="font-semibold">Recipe</h2>
          <button type="button" class="text-sm text-brand" :disabled="stock.length === 0" @click="addIngredient">+ Add ingredient</button>
        </div>
        <p v-if="stock.length === 0" class="text-sm text-muted">
          Add stock items in Inventory first, then attach them here.
        </p>
        <div v-else class="space-y-2">
          <div v-for="(line, i) in ingredients" :key="i" class="grid grid-cols-12 gap-2 items-center">
            <select v-model="line.inventory_item_id" class="col-span-7 border rounded-lg px-3 py-2">
              <option value="">Choose item</option>
              <option v-for="item in stock" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
            </select>
            <input v-model="line.quantity" type="number" step="0.001" min="0.001" placeholder="Qty" class="col-span-3 border rounded-lg px-3 py-2" />
            <span class="col-span-1 text-xs text-muted">{{ unitFor(line.inventory_item_id) }}</span>
            <button type="button" class="col-span-1 text-red-600 text-xs" @click="removeIngredient(i)">✕</button>
          </div>
        </div>
        <p v-if="recipeCost > 0" class="text-sm text-muted mt-3">
          Recipe cost {{ formatMoney(recipeCost, app.currencySymbol) }}
          <span v-if="form.price !== ''"> · Price {{ formatMoney(+form.price || 0, app.currencySymbol) }}</span>
        </p>
      </div>

      <div class="flex gap-3 justify-end">
        <RouterLink to="/menu" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="saving">
          {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Save dish' }}
        </button>
      </div>
    </form>
  </div>
</template>
