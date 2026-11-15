<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, formatMoney } from '@framework/api/client'
import { useAppStore } from '@framework/stores/app'
import { useListPage } from '@framework/composables/useListPage'
import { type MenuItemRow } from '@framework/utils/catalog'

type OptionBlock = {
  group: string | null
  headerPrice: number | null
  headerItemId: number | null
  items: MenuItemRow[]
}

type CategoryBlock = {
  category: string
  blocks: OptionBlock[]
}

const app = useAppStore()
const { rows, loading, error, load } = useListPage<MenuItemRow>('/menu')
const meta = ref<{ categories?: string[]; services?: string[] }>({})
const category = ref('')
const service = ref('')
const availableOnly = ref(false)
const expandedCategories = ref<Set<string>>(new Set())
const expandedGroups = ref<Set<string>>(new Set())

function categoryKey(cat: string) {
  return cat
}

function groupKey(cat: string, group: string | null) {
  return `${cat}::${group ?? '__none'}`
}

function isCategoryOpen(cat: string) {
  return expandedCategories.value.has(categoryKey(cat))
}

function toggleCategory(cat: string) {
  const key = categoryKey(cat)
  const next = new Set(expandedCategories.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  expandedCategories.value = next
}

function isGroupOpen(cat: string, group: string | null) {
  // Ungrouped items show whenever the category is open
  if (!group) return true
  return expandedGroups.value.has(groupKey(cat, group))
}

function toggleGroup(cat: string, group: string) {
  const key = groupKey(cat, group)
  const next = new Set(expandedGroups.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  expandedGroups.value = next
}

function buildBlocks(items: MenuItemRow[]): OptionBlock[] {
  const order: string[] = []
  const map = new Map<string, MenuItemRow[]>()
  for (const row of items) {
    const key = row.menu_group?.trim() || ''
    if (!map.has(key)) {
      map.set(key, [])
      order.push(key)
    }
    map.get(key)!.push(row)
  }

  return order.map((key) => {
    const list = map.get(key) ?? []
    if (!key) {
      return { group: null, headerPrice: null, headerItemId: null, items: list }
    }
    const priced = list.filter((i) => Number(i.price) > 0)
    const choices = list.filter((i) => Number(i.price) === 0)
    // Fixed-price group (e.g. platter): price on header, options underneath
    if (priced.length === 1 && choices.length > 0) {
      const main = priced[0]
      return {
        group: key,
        headerPrice: Number(main.price),
        headerItemId: main.id,
        items: choices,
      }
    }
    return { group: key, headerPrice: null, headerItemId: null, items: list }
  })
}

const grouped = computed((): CategoryBlock[] => {
  const byCat = new Map<string, MenuItemRow[]>()
  for (const row of rows.value) {
    const list = byCat.get(row.category) ?? []
    list.push(row)
    byCat.set(row.category, list)
  }
  const result: CategoryBlock[] = []
  for (const cat of meta.value.categories ?? []) {
    const items = byCat.get(cat)
    if (items?.length) result.push({ category: cat, blocks: buildBlocks(items) })
  }
  for (const [cat, items] of byCat) {
    if (!(meta.value.categories ?? []).includes(cat)) {
      result.push({ category: cat, blocks: buildBlocks(items) })
    }
  }
  return result
})

async function loadFiltered() {
  const params: Record<string, string | number | boolean> = {}
  if (category.value) params.category = category.value
  if (service.value) params.service_type = service.value
  if (availableOnly.value) params.available = 1
  await load(params)
}

onMounted(async () => {
  const { data } = await api.get('/menu/meta')
  meta.value = data
  await loadFiltered()
})

async function remove(id: number) {
  if (!confirm('Delete this menu item?')) return
  await api.delete(`/menu/${id}`)
  rows.value = rows.value.filter((r) => r.id !== id)
}

function priceLabel(row: MenuItemRow, block: OptionBlock) {
  const price = Number(row.price)
  if (block.headerPrice != null && price === 0) return '—'
  if (block.headerPrice != null && row.name === block.group) return formatMoney(price, app.currencySymbol)
  return formatMoney(price, app.currencySymbol)
}
</script>

<template>
  <div>
    <div class="flex justify-between items-start mb-6 gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Menu management</h1>
        <p class="text-muted text-sm">Dishes, packages, prices, and recipes</p>
      </div>
      <RouterLink to="/menu/new" class="bg-brand text-white px-4 py-2 rounded-lg">+ New dish</RouterLink>
    </div>

    <div class="flex items-center gap-3 mb-4 flex-wrap">
      <select v-model="service" class="border rounded-lg px-3 py-2" @change="loadFiltered">
        <option value="">All services</option>
        <option v-for="s in meta.services" :key="s" :value="s">{{ s }}</option>
      </select>
      <select v-model="category" class="border rounded-lg px-3 py-2" @change="loadFiltered">
        <option value="">All categories</option>
        <option v-for="c in meta.categories" :key="c" :value="c">{{ c }}</option>
      </select>
      <label class="text-sm flex items-center gap-2">
        <input v-model="availableOnly" type="checkbox" @change="loadFiltered" />
        Available only
      </label>
    </div>

    <div v-if="error" class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm flex justify-between items-center">
      <span>{{ error }}</span>
      <button class="underline font-medium" @click="loadFiltered">Retry</button>
    </div>

    <div v-if="loading" class="text-muted text-sm">Loading…</div>

    <div v-else-if="rows.length === 0" class="bg-white rounded-xl p-8 text-center text-muted shadow-sm">
      No menu items yet.
    </div>

    <div v-else class="space-y-3">
      <section v-for="cat in grouped" :key="cat.category" class="bg-white rounded-xl shadow-sm overflow-hidden">
        <button
          type="button"
          class="w-full px-4 py-3 bg-stone-50 font-semibold text-sm uppercase tracking-wide text-muted flex items-center justify-between gap-3 hover:bg-stone-100 text-left"
          @click="toggleCategory(cat.category)"
        >
          <span>{{ cat.category }}</span>
          <span class="text-stone-400 normal-case tracking-normal font-normal text-xs">
            {{ isCategoryOpen(cat.category) ? '▾' : '▸' }}
          </span>
        </button>

        <div v-if="isCategoryOpen(cat.category)">
          <div v-for="block in cat.blocks" :key="block.group ?? '__none'">
            <button
              v-if="block.group"
              type="button"
              class="w-full px-4 py-2 border-t bg-stone-50/80 flex justify-between items-center gap-3 hover:bg-stone-100 text-left"
              @click="toggleGroup(cat.category, block.group)"
            >
              <h3 class="font-semibold text-sm text-stone-800">{{ block.group }}</h3>
              <div class="flex items-center gap-3">
                <span v-if="block.headerPrice != null" class="font-semibold text-sm">
                  {{ formatMoney(block.headerPrice, app.currencySymbol) }}
                </span>
                <RouterLink
                  v-if="block.headerItemId"
                  :to="`/menu/${block.headerItemId}/edit`"
                  class="text-brand text-xs"
                  @click.stop
                >Edit</RouterLink>
                <span class="text-stone-400 text-xs w-3">{{ isGroupOpen(cat.category, block.group) ? '▾' : '▸' }}</span>
              </div>
            </button>

            <table v-if="isGroupOpen(cat.category, block.group)" class="w-full text-sm">
              <tbody>
                <tr
                  v-for="r in block.items"
                  :key="r.id"
                  class="border-t"
                  :class="block.group ? 'bg-white' : ''"
                >
                  <td class="p-3" :class="block.group ? 'pl-8' : ''">
                    <div class="font-medium">{{ r.name }}</div>
                    <div v-if="r.allergens" class="text-xs text-muted">Allergens: {{ r.allergens }}</div>
                  </td>
                  <td class="p-3 text-muted">{{ (r.service_types || []).join(', ') }}</td>
                  <td class="p-3 text-right text-muted">{{ r.cost ? formatMoney(+r.cost, app.currencySymbol) : '—' }}</td>
                  <td class="p-3 text-right font-medium">{{ priceLabel(r, block) }}</td>
                  <td class="p-3">
                    <span
                      class="px-2 py-0.5 rounded-full text-xs"
                      :class="r.available ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                    >
                      {{ r.available ? 'Available' : 'Hidden' }}
                    </span>
                  </td>
                  <td class="p-3 whitespace-nowrap">
                    <RouterLink :to="`/menu/${r.id}/edit`" class="text-brand text-xs mr-3">Edit</RouterLink>
                    <button class="text-red-600 text-xs" @click="remove(r.id)">Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
