export type ListPeriod = 'month' | 'year'

export type ExpenseListQuery = {
  period: ListPeriod
  month: string
  year: string
  category: string
  sort: string
  dir: 'asc' | 'desc'
}

export type IncomeListQuery = {
  period: ListPeriod
  month: string
  year: string
  sort: string
  dir: 'asc' | 'desc'
}

function queryValue(query: Record<string, unknown>, key: string): string {
  const value = query[key]
  return typeof value === 'string' ? value : ''
}

export function parseExpenseListQuery(query: Record<string, unknown>): ExpenseListQuery {
  return {
    period: queryValue(query, 'period') === 'year' ? 'year' : 'month',
    month: queryValue(query, 'month') || new Date().toISOString().slice(0, 7),
    year: queryValue(query, 'year') || String(new Date().getFullYear()),
    category: queryValue(query, 'category'),
    sort: queryValue(query, 'sort') || 'date',
    dir: queryValue(query, 'dir') === 'asc' ? 'asc' : 'desc',
  }
}

export function buildExpenseListQuery(state: ExpenseListQuery): Record<string, string> {
  const query: Record<string, string> = {
    period: state.period,
    sort: state.sort,
    dir: state.dir,
  }

  if (state.period === 'year') {
    query.year = state.year
  } else {
    query.month = state.month
  }

  if (state.category) query.category = state.category
  return query
}

export function parseIncomeListQuery(query: Record<string, unknown>): IncomeListQuery {
  return {
    period: queryValue(query, 'period') === 'year' ? 'year' : 'month',
    month: queryValue(query, 'month') || new Date().toISOString().slice(0, 7),
    year: queryValue(query, 'year') || String(new Date().getFullYear()),
    sort: queryValue(query, 'sort') || 'date',
    dir: queryValue(query, 'dir') === 'asc' ? 'asc' : 'desc',
  }
}

export function buildIncomeListQuery(state: IncomeListQuery): Record<string, string> {
  const query: Record<string, string> = {
    period: state.period,
    sort: state.sort,
    dir: state.dir,
  }
  if (state.period === 'year') {
    query.year = state.year
  } else {
    query.month = state.month
  }
  return query
}

export function pickListQuery(query: Record<string, unknown>, keys: string[]): Record<string, string> {
  const picked: Record<string, string> = {}
  for (const key of keys) {
    const value = queryValue(query, key)
    if (value) picked[key] = value
  }
  return picked
}
