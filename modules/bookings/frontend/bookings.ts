export const statusLabels: Record<string, string> = {
  inquiry: 'Inquiry',
  confirmed: 'Confirmed',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

export const statusClass: Record<string, string> = {
  inquiry: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-emerald-100 text-emerald-800',
  completed: 'bg-slate-100 text-slate-700',
  cancelled: 'bg-red-100 text-red-700',
}

export function formatTime(value?: string | null) {
  if (!value) return ''
  return String(value).slice(0, 5)
}

export function formatDate(value: unknown) {
  if (!value) return '—'
  return String(value).slice(0, 10)
}

export type BookingRow = {
  id: number
  reference: string
  title: string
  service_type: string
  event_date: string
  start_time?: string | null
  end_time?: string | null
  location?: string | null
  guest_count?: number | null
  status: string
  quoted_amount?: string | number | null
  deposit_amount?: string | number | null
  notes?: string | null
  customer?: { id: number; name: string; email?: string | null; phone?: string | null } | null
}
