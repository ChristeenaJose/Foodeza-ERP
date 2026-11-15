export const DEFAULT_MIN_DATE = '2025-01-01'

export function minMonthFromDate(minDate = DEFAULT_MIN_DATE) {
  return minDate.slice(0, 7)
}

export function startOfMonth(ym: string) {
  return `${ym}-01`
}

export function endOfMonth(ym: string) {
  const [y, m] = ym.split('-').map((v) => Number(v))
  const lastDay = new Date(y, m, 0).getDate()
  return `${ym}-${String(lastDay).padStart(2, '0')}`
}

export function startOfYear(y: string) {
  return `${y}-01-01`
}

export function endOfYear(y: string) {
  return `${y}-12-31`
}

export function yearsFrom(minDate = DEFAULT_MIN_DATE) {
  const start = Number(minDate.slice(0, 4))
  const end = new Date().getFullYear()
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
}
