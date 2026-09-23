import type { TreeItem } from '../../src/tree'

/** Эталонная фикстура из ТЗ MStroy_Frontend v3.2 (immutable source). */
const TZ_ITEMS_SOURCE: TreeItem[] = [
  { id: 1, parent: null, label: 'Айтем 1' },
  { id: '91064cef', parent: 1, label: 'Айтем 2' },
  { id: 3, parent: 1, label: 'Айтем 3' },
  { id: 4, parent: '91064cef', label: 'Айтем 4' },
  { id: 5, parent: '91064cef', label: 'Айтем 5' },
  { id: 6, parent: '91064cef', label: 'Айтем 6' },
  { id: 7, parent: 4, label: 'Айтем 7' },
  { id: 8, parent: 4, label: 'Айтем 8' },
]

/** Свежая копия — TreeStore мутирует объекты при update/replace. */
export function tzItems(): TreeItem[] {
  return TZ_ITEMS_SOURCE.map((item) => ({ ...item }))
}

/**
 * Генерирует дерево из `count` узлов, глубина ≈ `maxDepth`.
 * Корни с parent=null; дети распределяются по слоям.
 */
export function generateTree(count: number, maxDepth = 10): TreeItem[] {
  if (count <= 0) {
    return []
  }

  const items: TreeItem[] = [{ id: 0, parent: null, label: 'root-0' }]
  const layerStarts: number[] = [0]
  let nextId = 1
  let depth = 0

  while (nextId < count) {
    const parentLayerStart = layerStarts[depth] ?? 0
    const parentLayerEnd = depth + 1 < layerStarts.length ? layerStarts[depth + 1] : nextId
    const parentsInLayer = Math.max(1, parentLayerEnd - parentLayerStart)
    const remaining = count - nextId
    const layerSize = Math.min(
      remaining,
      Math.max(1, Math.ceil(remaining / Math.max(1, maxDepth - depth))),
    )

    layerStarts.push(nextId)

    for (let i = 0; i < layerSize && nextId < count; i++) {
      const parentId = parentLayerStart + (i % parentsInLayer)
      items.push({
        id: nextId,
        parent: parentId,
        label: `item-${nextId}`,
      })
      nextId++
    }

    depth = Math.min(depth + 1, maxDepth - 1)
  }

  return items
}
