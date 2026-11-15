<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, grossToNet, expenseGross } from '@framework/api/client'
import { API_BASE } from '@framework/config'
import { DEFAULT_MIN_DATE } from '@framework/utils/dates'
import { pickListQuery } from './listQuery'

type ExpenseGroup = 'operating' | 'fixed_asset' | 'startup'
type ProofType = 'uploaded' | 'paper_pdf' | 'bank_statement' | 'credit_card' | 'business_account' | 'none'

const proofTypes = ref<Record<string, string>>({})

const route = useRoute()
const router = useRouter()
const groups = ref<Array<{ key: ExpenseGroup; label: string; description?: string }>>([])
const categories = ref<Array<{ id: number; name: string; group: ExpenseGroup }>>([])
const minDate = ref(DEFAULT_MIN_DATE)
const vatRate = ref(0.21)
const loading = ref(false)
const saving = ref(false)
const saveError = ref('')
const existingReceipt = ref<{ id: number; original_name: string } | null>(null)
const expenseGroup = ref<ExpenseGroup>('operating')
const listQuery = computed(() => pickListQuery(route.query, ['period', 'month', 'year', 'category', 'sort', 'dir']))
const cancelTo = computed(() => ({ path: '/bookkeeping/expenses', query: listQuery.value }))

const editId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => Boolean(editId.value))

const form = ref({
  date: new Date().toISOString().slice(0, 10),
  supplier_name: '',
  category_id: '' as string | number,
  amount: '' as string | number,
  description: '',
  proof_type: 'none' as ProofType,
})
const receipt = ref<File | null>(null)

const operatingCategories = computed(() => categories.value.filter((c) => c.group === 'operating'))
const manualProofOptions = computed(() => Object.entries(proofTypes.value).filter(([key]) => key !== 'uploaded'))
const hasUploadedProof = computed(() => Boolean(receipt.value || existingReceipt.value))
const fixedAssetCategory = computed(() => categories.value.find((c) => c.group === 'fixed_asset'))
const startupCategory = computed(() => categories.value.find((c) => c.group === 'startup'))
const selectedGroupMeta = computed(() => groups.value.find((g) => g.key === expenseGroup.value))

function setGroup(group: ExpenseGroup) {
  expenseGroup.value = group
  if (group === 'fixed_asset' && fixedAssetCategory.value) {
    form.value.category_id = fixedAssetCategory.value.id
  } else if (group === 'startup' && startupCategory.value) {
    form.value.category_id = startupCategory.value.id
  } else if (group === 'operating') {
    const current = categories.value.find((c) => c.id === Number(form.value.category_id))
    if (!current || current.group !== 'operating') {
      form.value.category_id = operatingCategories.value[0]?.id ?? ''
    }
  }
}

function syncGroupFromCategory(categoryId: string | number) {
  const cat = categories.value.find((c) => c.id === Number(categoryId))
  if (cat) expenseGroup.value = cat.group
}

watch(() => form.value.category_id, (id) => {
  if (expenseGroup.value === 'operating' && id) syncGroupFromCategory(id)
})

onMounted(async () => {
  loading.value = true
  try {
    const [catRes, cfgRes] = await Promise.all([api.get('/expenses/categories'), api.get('/config')])
    groups.value = catRes.data.groups ?? []
    categories.value = catRes.data.categories ?? []
    proofTypes.value = cfgRes.data.expense_proof_types ?? {}
    minDate.value = cfgRes.data.records_start_date ?? DEFAULT_MIN_DATE
    vatRate.value = Number(cfgRes.data.vat_rate ?? 0.21)

    if (isEdit.value) {
      const { data } = await api.get(`/expenses/${editId.value}`)
      form.value = {
        date: String(data.date).slice(0, 10),
        supplier_name: data.supplier?.name ?? '',
        category_id: data.category_id,
        amount: expenseGross(data.amount, data.vat_amount, vatRate.value),
        description: data.description ?? '',
        proof_type: (data.receipt_attachment ? 'uploaded' : (data.proof_type ?? 'none')) as ProofType,
      }
      syncGroupFromCategory(data.category_id)
      if (data.receipt_attachment) {
        existingReceipt.value = data.receipt_attachment
      }
    } else {
      setGroup('operating')
    }
  } finally {
    loading.value = false
  }
})

async function submit() {
  saving.value = true
  saveError.value = ''
  try {
    const gross = Number(form.value.amount)
    let categoryId = Number(form.value.category_id)
    if (expenseGroup.value === 'fixed_asset' && fixedAssetCategory.value) {
      categoryId = fixedAssetCategory.value.id
    } else if (expenseGroup.value === 'startup' && startupCategory.value) {
      categoryId = startupCategory.value.id
    }

    const payload = {
      ...form.value,
      category_id: categoryId,
      amount: grossToNet(gross, vatRate.value),
      proof_type: hasUploadedProof.value ? 'uploaded' : form.value.proof_type,
    }

    if (isEdit.value) {
      if (receipt.value) {
        const fd = new FormData()
        Object.entries(payload).forEach(([k, v]) => fd.append(k, String(v)))
        fd.append('receipt', receipt.value)
        fd.append('_method', 'PUT')
        await api.post(`/expenses/${editId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      } else {
        await api.put(`/expenses/${editId.value}`, payload)
      }
    } else {
      const fd = new FormData()
      Object.entries(payload).forEach(([k, v]) => fd.append(k, String(v)))
      if (receipt.value) fd.append('receipt', receipt.value)
      await api.post('/expenses', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }

    await router.push(cancelTo.value)
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? 'Could not save expense.'
  } finally {
    saving.value = false
  }
}

function onFile(e: Event) {
  receipt.value = (e.target as HTMLInputElement).files?.[0] ?? null
  if (receipt.value) form.value.proof_type = 'uploaded'
}
</script>

<template>
  <div class="max-w-xl">
    <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Expense' : 'Record Expense' }}</h1>
    <div v-if="loading" class="text-muted">Loading…</div>
    <form v-else class="bg-white rounded-xl p-6 shadow-sm space-y-4" @submit.prevent="submit">
      <div v-if="saveError" class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ saveError }}</div>

      <div>
        <span class="text-sm font-medium">Expense type (for tax report)</span>
        <div class="mt-2 flex flex-col gap-2">
          <button
            v-for="g in groups"
            :key="g.key"
            type="button"
            class="text-left border rounded-lg px-4 py-3 transition"
            :class="expenseGroup === g.key ? 'border-brand bg-red-50 ring-1 ring-brand' : 'hover:bg-stone-50'"
            @click="setGroup(g.key)"
          >
            <div class="font-medium">{{ g.label }}</div>
            <div v-if="g.description" class="text-xs text-muted mt-0.5">{{ g.description }}</div>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <label class="block"><span class="text-sm font-medium">Date</span><input v-model="form.date" type="date" :min="minDate" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
        <label class="block"><span class="text-sm font-medium">Amount paid (incl. VAT)</span><input v-model="form.amount" type="number" step="0.01" required class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      </div>
      <label class="block"><span class="text-sm font-medium">Supplier</span><input v-model="form.supplier_name" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>

      <label v-if="expenseGroup === 'operating'" class="block">
        <span class="text-sm font-medium">Operating category</span>
        <select v-model="form.category_id" required class="mt-1 w-full border rounded-lg px-3 py-2">
          <option v-for="c in operatingCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <div v-else class="rounded-lg bg-stone-50 border px-4 py-3 text-sm">
        <span class="text-muted">Category:</span>
        <strong class="ml-1">{{ selectedGroupMeta?.label }}</strong>
        <span v-if="expenseGroup === 'fixed_asset'"> — food truck, vehicles, large equipment</span>
        <span v-else-if="expenseGroup === 'startup'"> — registration, TÜV, permits</span>
      </div>

      <label class="block"><span class="text-sm font-medium">Description</span><input v-model="form.description" class="mt-1 w-full border rounded-lg px-3 py-2" /></label>
      <div class="block">
        <span class="text-sm font-medium">Proof / documentation</span>
        <p v-if="hasUploadedProof" class="mt-2 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
          Uploaded bill/receipt attached
        </p>
        <div v-else class="mt-2 space-y-2">
          <label
            v-for="[key, label] in manualProofOptions"
            :key="key"
            class="flex items-start gap-2 text-sm text-stone-700 border rounded-lg px-3 py-2 cursor-pointer"
            :class="form.proof_type === key ? 'border-brand bg-red-50' : 'hover:bg-stone-50'"
          >
            <input v-model="form.proof_type" type="radio" class="mt-0.5" :value="key" />
            <span>{{ label }}</span>
          </label>
        </div>
        <p v-if="existingReceipt" class="text-sm text-muted mt-2 mb-1">
          Current file:
          <a :href="`${API_BASE}/attachments/${existingReceipt.id}/download`" target="_blank" class="text-brand">{{ existingReceipt.original_name }}</a>
        </p>
        <label class="block mt-2">
          <span class="text-sm text-muted">Upload bill/receipt (optional)</span>
          <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full" @change="onFile" />
        </label>
      </div>
      <div class="flex gap-3 justify-end">
        <RouterLink :to="cancelTo" class="px-4 py-2">Cancel</RouterLink>
        <button type="submit" class="bg-brand text-white px-4 py-2 rounded-lg disabled:opacity-60" :disabled="saving">
          {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Save' }}
        </button>
      </div>
    </form>
  </div>
</template>
