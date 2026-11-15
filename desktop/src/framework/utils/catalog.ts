export function formatQty(value: unknown, unit?: string | null) {
  const num = Number(value ?? 0)
  const text = Number.isInteger(num)
    ? String(num)
    : num.toFixed(3).replace(/\.?0+$/, '')
  return unit ? `${text} ${unit}` : text
}

export const movementLabels: Record<string, string> = {
  in: 'Received',
  out: 'Used',
  adjust: 'Adjusted',
}

export const movementClass: Record<string, string> = {
  in: 'text-emerald-700',
  out: 'text-red-700',
  adjust: 'text-slate-700',
}

export type InventoryItemRow = {
  id: number
  name: string
  sku?: string | null
  category: string
  unit: string
  quantity: string | number
  reorder_level: string | number
  unit_cost?: string | number | null
  supplier_name?: string | null
  notes?: string | null
  active: boolean
  low_stock: boolean
  stock_value: number
}

export type InventoryMovementRow = {
  id: number
  date: string
  type: string
  quantity: string | number
  reason?: string | null
  notes?: string | null
  item?: InventoryItemRow | null
  inventory_item?: InventoryItemRow | null
}

export type MenuIngredientRow = {
  id?: number
  inventory_item_id: number
  quantity: string | number
  inventory_item?: InventoryItemRow | null
}

export type MenuItemRow = {
  id: number
  name: string
  description?: string | null
  category: string
  menu_group?: string | null
  service_types: string[]
  price: string | number
  available: boolean
  allergens?: string | null
  notes?: string | null
  cost?: number
  ingredients?: MenuIngredientRow[]
}
