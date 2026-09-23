import { describe, expect, it } from 'vitest'
import { TreeStore } from '../src/tree'
import { generateTree } from './fixtures/items'

function measureMs(fn: () => void): number {
  const start = performance.now()
  fn()
  return performance.now() - start
}

/**
 * Пороги — ориентир для README (локальная машина / CI).
 * getItem на 50k должен оставаться субмиллисекундным (индекс Map).
 */
const THRESHOLDS = {
  getItem50kMs: 1,
  getChildren50kMs: 5,
  getAllParents50kMs: 5,
  setItems50kMs: 500,
  removeSubtree50kMs: 100,
  getAllChildrenWide10kMs: 50,
} as const

describe('TreeStore performance', () => {
  it('N=10_000: getItem / getChildren / getAllChildren / getAllParents / setItems / removeItem', () => {
    const items = generateTree(10_000, 10)
    const store = new TreeStore(items)

    // широкий узел — корень (много потомков)
    const getAllChildrenMs = measureMs(() => {
      store.getAllChildren(0)
    })
    expect(getAllChildrenMs).toBeLessThan(THRESHOLDS.getAllChildrenWide10kMs)

    const leafId = 9_999
    const getAllParentsMs = measureMs(() => {
      store.getAllParents(leafId)
    })
    expect(getAllParentsMs).toBeLessThan(5)

    const getItemMs = measureMs(() => {
      for (let i = 0; i < 1000; i++) {
        store.getItem(i)
      }
    })
    expect(getItemMs / 1000).toBeLessThan(1)

    const getChildrenMs = measureMs(() => {
      store.getChildren(0)
    })
    expect(getChildrenMs).toBeLessThan(5)

    const setItemsMs = measureMs(() => {
      store.setItems(items)
    })
    expect(setItemsMs).toBeLessThan(100)

    // поддерево: первый ребёнок корня
    const subtreeRoot = store.getChildren(0)[0]?.id
    expect(subtreeRoot).toBeDefined()
    const removeMs = measureMs(() => {
      store.removeItem(subtreeRoot!)
    })
    expect(removeMs).toBeLessThan(50)
  })

  it('N=50_000: getItem < 1ms; индексированные операции в порогах', () => {
    const items = generateTree(50_000, 10)
    let store = new TreeStore(items)

    const getItemMs = measureMs(() => {
      store.getItem(25_000)
    })
    expect(getItemMs).toBeLessThan(THRESHOLDS.getItem50kMs)

    const getChildrenMs = measureMs(() => {
      store.getChildren(0)
    })
    expect(getChildrenMs).toBeLessThan(THRESHOLDS.getChildren50kMs)

    const leafId = 49_999
    const getAllParentsMs = measureMs(() => {
      store.getAllParents(leafId)
    })
    expect(getAllParentsMs).toBeLessThan(THRESHOLDS.getAllParents50kMs)

    const setItemsMs = measureMs(() => {
      store.setItems(items)
    })
    expect(setItemsMs).toBeLessThan(THRESHOLDS.setItems50kMs)

    store = new TreeStore(items)
    const wideChild = store.getChildren(0)[0]?.id
    expect(wideChild).toBeDefined()

    const getAllChildrenMs = measureMs(() => {
      store.getAllChildren(wideChild!)
    })
    expect(getAllChildrenMs).toBeLessThan(100)

    const removeMs = measureMs(() => {
      store.removeItem(wideChild!)
    })
    expect(removeMs).toBeLessThan(THRESHOLDS.removeSubtree50kMs)
  })
})
