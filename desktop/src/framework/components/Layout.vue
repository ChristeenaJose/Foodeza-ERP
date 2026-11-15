<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useAppStore } from '../stores/app'

const route = useRoute()
const app = useAppStore()

type NavItem = { to: string; label: string; exact?: boolean }

const moduleLabel = computed(() => (route.meta.module as string) || 'Module')
const nav = computed(() => (route.meta.nav as NavItem[]) || [])

function isActive(item: NavItem) {
  if (item.exact) {
    return route.path === item.to || route.path === `${item.to}/`
  }
  return route.path === item.to || route.path.startsWith(`${item.to}/`)
}

onMounted(() => {
  app.ping()
  setInterval(() => app.ping(), 10000)
})
</script>

<template>
  <div class="flex min-h-screen">
    <aside class="w-60 shrink-0 bg-sidebar text-slate-300 p-4 flex flex-col">
      <RouterLink to="/" class="flex items-center gap-3 pb-5 mb-4 border-b border-white/10 px-2 hover:opacity-90">
        <div class="w-11 h-11 rounded-xl bg-brand text-white font-black flex items-center justify-center text-3xl leading-none select-none tracking-tight">F</div>
        <div>
          <div class="text-white font-semibold">Foodeza ERP</div>
          <div class="text-xs opacity-70">{{ moduleLabel }}</div>
        </div>
      </RouterLink>

      <RouterLink to="/" class="px-3 py-2 mb-3 rounded-lg text-xs text-white/70 hover:bg-white/5 hover:text-white">
        ← All modules
      </RouterLink>

      <nav class="flex flex-col gap-1 flex-1">
        <RouterLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="px-3 py-2 rounded-lg text-sm transition"
          :class="isActive(item)
            ? 'bg-red-600/25 text-white font-medium'
            : 'hover:bg-white/5 hover:text-white'"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="mt-4 px-2 text-xs flex items-center gap-2">
        <span class="w-2 h-2 rounded-full" :class="app.online ? 'bg-emerald-400' : 'bg-amber-400'" />
        {{ app.online ? 'Local API online' : 'Offline — queue active' }}
      </div>
    </aside>

    <main class="flex-1 p-8 overflow-auto">
      <RouterView />
    </main>
  </div>
</template>
