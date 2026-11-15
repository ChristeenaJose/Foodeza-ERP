<script setup lang="ts">
import { computed } from 'vue'
import { formatMoney } from '../api/client'

export type BarItem = {
  label: string
  value: number
}

const props = withDefaults(defineProps<{
  title: string
  items: BarItem[]
  color?: string
  currencySymbol?: string
  emptyText?: string
  compact?: boolean
}>(), {
  color: '#dc2626',
  currencySymbol: '€',
  emptyText: 'No data',
  compact: false,
})

const max = computed(() => Math.max(...props.items.map((i) => i.value), 0))

function widthPct(value: number) {
  if (max.value <= 0) return 0
  return Math.max(4, Math.round((value / max.value) * 100))
}
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm" :class="compact ? 'p-3' : 'p-5'">
    <h2 class="font-semibold mb-2" :class="compact ? 'text-xs text-muted uppercase tracking-wide' : ''">{{ title }}</h2>
    <div v-if="!items.length" class="text-xs text-muted py-3 text-center">{{ emptyText }}</div>
    <div v-else class="space-y-1.5">
      <div
        v-for="item in items"
        :key="item.label"
        class="grid grid-cols-[5.5rem_1fr_auto] gap-2 items-center"
        :class="compact ? 'text-xs' : 'text-sm'"
      >
        <div class="truncate" :title="item.label">{{ item.label }}</div>
        <div class="rounded-full bg-stone-100 overflow-hidden" :class="compact ? 'h-1.5' : 'h-3'">
          <div
            class="h-full rounded-full"
            :style="{ width: `${widthPct(item.value)}%`, backgroundColor: color }"
          />
        </div>
        <div class="text-right font-medium tabular-nums min-w-[4.25rem]">
          {{ formatMoney(item.value, currencySymbol) }}
        </div>
      </div>
    </div>
  </div>
</template>
