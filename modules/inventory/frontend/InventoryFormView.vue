<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@framework/api/client'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'
import { formatQty, movementClass, movementLabels } from '@framework/utils/catalog'

const route = useRoute()
const router = useRouter()
const meta = ref<{ categories?: string[]; units?: string[]; movement_types?: Record<string, string> }>({})
const loading = ref(false)
const saving = ref(false)
const adjusting = ref(false)
const saveError = ref('')
const adjustError = ref('')

const editId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => Boolean(editId.value))
const quantity = ref(0)
const unit = computed(() => form.value.unit)
const movements = ref<any[]>([])

const form = ref({
  name: '',
  sku: '',
  category: '',
  unit: 'kg',
  quantity: '' as string | number,
  reorder_level: '' as string | number,
  unit_cost: '' as string | number,
  supplier_name: '',
  notes: '',
  active: true,
})

const adjust = ref({
  date: new Date().toISOString().slice(0, 10),
  type: 'in',
  quantity: '' as string | number,
  reason: '',
})

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get('/inventory/meta')
    meta.value = data
    form.value.category = data.categories?.[0] ?? 'Ingredients'
    form.value.unit = data.units?.[0] ?? 'kg'

    if (isEdit.value) {
      const { data: item } = await api.get(`/inventory/${editId.value}`)
      form.value = {
        name: item.name,
        sku: item.sku ?? '',
        category: item.category,
        unit: item.unit,
        quantity: item.quantity,
        reorder_level: item.reorder_level ?? '',
        unit_cost: item.unit_cost ?? '',
        supplier_name: item.supplier_name ?? '',
        notes: item.notes ?? '',
        active: item.active,
      }
      quantity.value = Number(item.quantity)
      movements.value = item.movements ?? []
    }
  } finally {
    loading.value = false
  }
})

function emptyToNull(value: string | number) {
  if (value === '' || value === null || value === undefined) return null
  return value
}

async function submit() {
  saving.value = true
  saveError.value = ''
  try {
    const payload = {
      ...form.value,
      sku: form.value.sku || null,
      quantity: isEdit.value ? undefined : emptyToNull(form.value.quantity) ?? 0,
      reorder_level: emptyToNull(form.value.reorder_level) ?? 0,
      unit_cost: emptyToNull(form.value.unit_cost),
    }
    if (isEdit.value) {
      await api.put(`/inventory/${editId.value}`, payload)
    } else {
      await api.post('/inventory', payload)
    }
    await router.push('/inventory')
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Could not save item.'
  } finally {
    saving.value = false
  }
}

async function submitAdjust() {
  adjusting.value = true
  adjustError.value = ''
  try {
    const { data } = await api.post(`/inventory/${editId.value}/adjust`, {
      ...adjust.value,
      quantity: +adjust.value.quantity,
    })
    quantity.value = Number(data.item.quantity)
    form.value.quantity = data.item.quantity
    movements.value = [data.movement, ...movements.value].slice(0, 20)
    adjust.value.quantity = ''
    adjust.value.reason = ''
  } catch (e: any) {
    adjustError.value = e?.response?.data?.message ?? 'Could not update stock.'
  } finally {
    adjusting.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit stock item' : 'New stock item' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="saveError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ saveError }}</div>
      <label class="block"><span class="text-sm font-medium">Name</span><input v-model="form.name" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">SKU</span><input v-model="form.sku" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Supplier</span><input v-model="form.supplier_name" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Category</span>
          <select v-model="form.category" required class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="c in meta.categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </label>
        <label class="block"><span class="text-sm font-medium">Unit</span>
          <select v-model="form.unit" required class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="u in meta.units" :key="u" :value="u">{{ u }}</option>
          </select>
        </label>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <label v-if="!isEdit" class="block"><span class="text-sm font-medium">Opening stock</span><input v-model="form.quantity" type="number" step="0.001" min="0" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <div v-else class="block">
          <span class="text-sm font-medium">On hand</span>
          <div class="mt-1 text-lg font-semibold">{{ formatQty(quantity, unit) }}</div>
        </div>
        <label class="block"><span class="text-sm font-medium">Reorder at</span><input v-model="form.reorder_level" type="number" step="0.001" min="0" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Unit cost</span><input v-model="form.unit_cost" type="number" step="0.01" min="0" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label class="block"><span class="text-sm font-medium">Notes</span><input v-model="form.notes" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <label v-if="isEdit" class="text-sm flex items-center gap-2">
        <input v-model="form.active" type="checkbox" />
        Active
      </label>
      <div class="flex gap-3 justify-end">
        <RouterLink to="/inventory" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="saving">
          {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Save item' }}
        </button>
      </div>
    </form>

    <form v-if="isEdit && !loading" class="bg-white rounded-xl p-6 shadow-sm space-y-4 mt-6" @submit.prevent="submitAdjust">
      <h2 class="font-semibold">Adjust stock</h2>
      <div v-if="adjustError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ adjustError }}</div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Date</span><input v-model="adjust.date" type="date" :min="DEFAULT_MIN_DATE" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Type</span>
          <select v-model="adjust.type" class="mt-1 w-full border rounded-lg px-3 py-2">
            <option v-for="(label, key) in (meta.movement_types || movementLabels)" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">{{ adjust.type === 'adjust' ? 'New quantity' : 'Quantity' }}</span><input v-model="adjust.quantity" type="number" step="0.001" min="0.001" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Reason</span><input v-model="adjust.reason" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="adjusting">
          {{ adjusting ? 'Saving…' : 'Record movement' }}
        </button>
      </div>
      <div v-if="movements.length" class="pt-2">
        <h3 class="text-sm font-medium mb-2">Recent movements</h3>
        <ul class="text-sm space-y-1">
          <li v-for="m in movements" :key="m.id" class="flex justify-between gap-3">
            <span>{{ String(m.date).slice(0, 10) }} · <span :class="movementClass[m.type]">{{ movementLabels[m.type] }}</span> {{ formatQty(m.quantity, unit) }}</span>
            <span class="text-muted truncate">{{ m.reason || '' }}</span>
          </li>
        </ul>
      </div>
    </form>
  </div>
</template>
