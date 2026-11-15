import Dexie, { type EntityTable } from 'dexie'

export interface QueuedRequest {
  id?: number
  method: string
  url: string
  data?: string
  headers?: string
  created_at: string
}

export const offlineDb = new Dexie('FoodezaBookkeeping') as Dexie & {
  queue: EntityTable<QueuedRequest, 'id'>
}

offlineDb.version(1).stores({
  queue: '++id, created_at',
})
