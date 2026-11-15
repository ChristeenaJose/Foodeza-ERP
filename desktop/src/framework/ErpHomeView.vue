<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAppStore } from './stores/app'

const app = useAppStore()

const modules = [
  {
    to: '/bookkeeping',
    title: 'Bookkeeping',
    description: 'Income, expenses, invoices, payments, bank, and reports',
    letter: 'Bk',
  },
  {
    to: '/bookings',
    title: 'Booking management',
    description: 'Catering, food truck, lunch, and meal box bookings',
    letter: 'Bo',
  },
  {
    to: '/inventory',
    title: 'Inventory',
    description: 'Ingredients, packaging, and stock levels',
    letter: 'In',
  },
  {
    to: '/menu',
    title: 'Menu management',
    description: 'Product master, menus, packages, pricing & availability',
    letter: 'Mn',
  },
  {
    to: '/orders',
    title: 'Google Form orders',
    description: 'Orders submitted through the Google Form',
    letter: 'Gf',
  },
]

onMounted(() => {
  app.ping()
})
</script>

<template>
  <div class="min-h-screen bg-stone-100">
    <header class="bg-sidebar text-white px-8 py-6">
      <div class="max-w-5xl mx-auto flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-brand font-black flex items-center justify-center text-3xl">F</div>
        <div>
          <div class="text-xl font-semibold">Foodeza ERP</div>
          <div class="text-sm text-white/70">Catering · Food Truck · Corporate Lunch · Meal Box Delivery</div>
        </div>
        <div class="ml-auto text-xs flex items-center gap-2 text-white/70">
          <span class="w-2 h-2 rounded-full" :class="app.online ? 'bg-emerald-400' : 'bg-amber-400'" />
          {{ app.online ? 'Local API online' : 'Offline — queue active' }}
        </div>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-8 py-10">
      <h1 class="text-2xl font-bold mb-2">Modules</h1>
      <p class="text-muted mb-8">Open a module to continue.</p>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <RouterLink
          v-for="mod in modules"
          :key="mod.title"
          :to="mod.to"
          class="bg-white rounded-2xl p-6 shadow-sm border border-stone-200 hover:border-brand hover:shadow-md transition text-left"
        >
          <div class="w-10 h-10 rounded-lg bg-brand text-white font-bold flex items-center justify-center mb-4 text-sm">{{ mod.letter }}</div>
          <h2 class="text-lg font-semibold">{{ mod.title }}</h2>
          <p class="text-sm text-muted mt-1">{{ mod.description }}</p>
          <div class="mt-4 text-sm font-medium text-brand">Open module →</div>
        </RouterLink>
      </div>
    </main>
  </div>
</template>
