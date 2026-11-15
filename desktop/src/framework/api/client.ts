import axios, { type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'
import { API_BASE } from '../config'
import { offlineDb } from '../offline/db'

export const api: AxiosInstance = axios.create({
  baseURL: API_BASE,
  timeout: 15000,
  headers: { Accept: 'application/json' },
})

let online = true

export function isOnline() {
  return online
}

export async function checkHealth(): Promise<boolean> {
  try {
    await api.get('/health', { timeout: 2000 })
    online = true
    await flushQueue()
    return true
  } catch {
    online = false
    return false
  }
}

async function flushQueue() {
  const pending = await offlineDb.queue.orderBy('created_at').toArray()
  for (const item of pending) {
    try {
      await api.request({
        method: item.method,
        url: item.url,
        data: item.data ? JSON.parse(item.data) : undefined,
        headers: item.headers ? JSON.parse(item.headers) : undefined,
      })
      if (item.id) await offlineDb.queue.delete(item.id)
    } catch {
      break
    }
  }
}

api.interceptors.response.use(
  (r) => {
    online = true
    return r
  },
  async (error) => {
    const config = error.config as InternalAxiosRequestConfig & { _queued?: boolean }
    if (!config || config._queued || config.method === 'get' || error.response) {
      if (!error.response) online = false
      return Promise.reject(error)
    }

    await offlineDb.queue.add({
      method: config.method ?? 'post',
      url: config.url ?? '',
      data: config.data ? JSON.stringify(config.data) : undefined,
      headers: config.headers ? JSON.stringify(config.headers) : undefined,
      created_at: new Date().toISOString(),
    })

    return Promise.resolve({
      data: { queued: true, offline: true },
      status: 202,
      statusText: 'Queued Offline',
      headers: {},
      config,
    })
  }
)

export function formatMoney(amount: number, symbol = '€') {
  return `${symbol}${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

export function expenseGross(amount: number, vatAmount?: number | null, vatRate = 0.21) {
  const net = Number(amount) || 0
  const vat = vatAmount != null ? Number(vatAmount) : 0
  if (vat > 0) return Math.round((net + vat) * 100) / 100
  return Math.round(net * (1 + vatRate) * 100) / 100
}

export function grossToNet(gross: number, vatRate = 0.21) {
  return Math.round((gross / (1 + vatRate)) * 100) / 100
}

export async function downloadInvoicePdf(invoiceId: number | string, filename: string, download = true) {
  const { data } = await api.get(`/invoices/${invoiceId}/pdf`, {
    params: download ? { download: 1 } : undefined,
    responseType: 'blob',
  })
  const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
  if (download) {
    const a = document.createElement('a')
    a.href = url
    a.download = filename.endsWith('.pdf') ? filename : `${filename}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } else {
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  }
}

export async function downloadTaxReportPdf(year: number | string, download = true) {
  const filename = `Foodeza-Steuerberater-Paket-${year}.pdf`
  const { data } = await api.get('/reports/tax/pdf', {
    params: { year, download: download ? 1 : undefined },
    responseType: 'blob',
  })
  const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
  if (download) {
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } else {
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  }
}

export function downloadJson(data: unknown, filename: string) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename.endsWith('.json') ? filename : `${filename}.json`
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

export const proofTypeLabels: Record<string, string> = {
  uploaded: 'Uploaded',
  paper_pdf: 'Paper/PDF',
  bank_statement: 'Bank stmt',
  credit_card: 'Card stmt',
  business_account: 'Business acct',
  none: 'No record',
}

export function proofTypeLabel(type?: string | null) {
  if (!type) return proofTypeLabels.none
  return proofTypeLabels[type] ?? type
}

export const paymentLabels: Record<string, string> = {
  cash: 'Cash',
  bank_transfer: 'Bank Transfer',
  card: 'Card',
  sumup: 'SumUp',
  stripe: 'Stripe',
  paypal: 'PayPal',
}
