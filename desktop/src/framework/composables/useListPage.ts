import { ref } from 'vue'
import { api } from '../api/client'

export function useListPage<T>(url: string, pick?: (data: unknown) => T[]) {
  const rows = ref<T[]>([])
  const loading = ref(true)
  const error = ref('')

  async function load(params?: Record<string, any>) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get(url, params ? { params } : undefined)
      rows.value = pick ? pick(data) : (Array.isArray(data) ? data : [])
    } catch (e: any) {
      rows.value = []
      error.value = e?.response?.data?.message ?? 'Could not load data. Is the API running?'
    } finally {
      loading.value = false
    }
  }

  return { rows, loading, error, load }
}
