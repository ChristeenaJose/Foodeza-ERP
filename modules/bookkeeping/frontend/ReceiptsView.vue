<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@framework/api/client'
import { API_BASE } from '@framework/config'

const receipts = ref<any[]>([])

onMounted(async () => {
  const { data } = await api.get('/reports/attachments')
  receipts.value = data.receipts
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Receipts</h1>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-stone-50 text-muted uppercase text-xs">
          <tr><th class="text-left p-3">File</th><th class="text-left p-3">Supplier</th><th class="text-left p-3">Date</th></tr>
        </thead>
        <tbody>
          <tr v-for="r in receipts" :key="r.id" class="border-t">
            <td class="p-3"><a :href="`${API_BASE}/attachments/${r.id}/download`" target="_blank" class="text-brand">{{ r.original_name }}</a></td>
            <td class="p-3">{{ r.expense?.supplier?.name ?? '—' }}</td>
            <td class="p-3">{{ r.expense?.date?.slice(0, 10) ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
