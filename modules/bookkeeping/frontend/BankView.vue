<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@framework/api/client'

const data = ref<any>(null)
const matchIncome = ref<Record<number, string>>({})
const matchExpense = ref<Record<number, string>>({})

onMounted(load)

async function load() {
  const { data: res } = await api.get('/bank')
  data.value = res
}

async function importCsv(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('csv', file)
  await api.post('/bank/import', fd)
  await load()
}

async function match(bankId: number, amount: number) {
  const payload: any = { bank_id: bankId }
  if (amount >= 0) payload.income_id = +matchIncome.value[bankId]
  else payload.expense_id = +matchExpense.value[bankId]
  await api.post('/bank/match', payload)
  await load()
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Bank Transactions</h1>
    <div class="bg-white rounded-xl p-5 shadow-sm mb-6">
      <h2 class="font-semibold mb-2">Import CSV</h2>
      <input type="file" accept=".csv" @change="importCsv" />
    </div>
    <div v-if="data" class="space-y-4">
      <div v-for="bt in data.unmatched" :key="bt.id" class="bg-white rounded-xl p-4 shadow-sm flex flex-wrap gap-4 items-center justify-between">
        <div>
          <strong>{{ bt.date }}</strong> {{ bt.description }}
          <span :class="bt.amount >= 0 ? 'text-income' : 'text-expense'" class="ml-2 font-medium">€{{ Math.abs(bt.amount) }}</span>
        </div>
        <div class="flex gap-2 items-center">
          <select v-if="bt.amount >= 0" v-model="matchIncome[bt.id]" class="border rounded-lg px-2 py-1 text-sm">
            <option value="">Match income…</option>
            <option v-for="i in data.recent_income" :key="i.id" :value="i.id">{{ i.date?.slice(0,10) }} — {{ i.service_type }}</option>
          </select>
          <select v-else v-model="matchExpense[bt.id]" class="border rounded-lg px-2 py-1 text-sm">
            <option value="">Match expense…</option>
            <option v-for="e in data.recent_expenses" :key="e.id" :value="e.id">{{ e.date?.slice(0,10) }} — {{ e.category?.name }}</option>
          </select>
          <button class="bg-stone-200 px-3 py-1 rounded-lg text-sm" @click="match(bt.id, bt.amount)">Match</button>
        </div>
      </div>
    </div>
  </div>
</template>
