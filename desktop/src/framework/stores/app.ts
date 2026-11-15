import { defineStore } from 'pinia'
import { ref } from 'vue'
import { checkHealth, isOnline } from '../api/client'

export const useAppStore = defineStore('app', () => {
  const online = ref(true)
  const company = ref('Foodeza')
  const currencySymbol = ref('€')

  async function ping() {
    online.value = await checkHealth()
    return online.value
  }

  return { online, company, currencySymbol, ping, isOnline }
})
