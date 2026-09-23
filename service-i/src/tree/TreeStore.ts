import type { ItemId, TreeItem } from './types'

/**
 * In-memory дерево с индексами Map:
 * - byId — O(1) getItem
 * - childrenByParent — прямые дети без полного скана
 *
 * Ключи id хранятся как есть (number и string — разные ключи).
 */
export class TreeStore {
  private items: TreeItem[] = []
  private byId = new Map<ItemId, TreeItem>()
  private childrenByParent = new Map<ItemId | null, ItemId[]>()

  constructor(items: TreeItem[] = []) {
    this.setItems(items)
  }

  /** Shallow copy внутреннего массива — наружная мутация не ломает индексы. */
  getAll(): TreeItem[] {
    return [...this.items]
  }

  getItem(id: ItemId): TreeItem | undefined {
    return this.byId.get(id)
  }

  getChildren(id: ItemId): TreeItem[] {
    if (!this.byId.has(id)) {
      return []
    }
    const childIds = this.childrenByParent.get(id)
    if (!childIds || childIds.length === 0) {
      return []
    }
    const result: TreeItem[] = []
    for (const childId of childIds) {
      const child = this.byId.get(childId)
      if (child) {
        result.push(child)
      }
    }
    return result
  }

  /**
   * Все потомки узла. Порядок не гарантируется.
   */
  getAllChildren(id: ItemId): TreeItem[] {
    if (!this.byId.has(id)) {
      return []
    }
    const result: TreeItem[] = []
    const stack = [...(this.childrenByParent.get(id) ?? [])]
    while (stack.length > 0) {
      const childId = stack.pop()!
      const item = this.byId.get(childId)
      if (!item) {
        continue
      }
      result.push(item)
      const grandchildren = this.childrenByParent.get(childId)
      if (grandchildren) {
        for (const grandchildId of grandchildren) {
          stack.push(grandchildId)
        }
      }
    }
    return result
  }

  /**
   * Цепочка от элемента к корню: [item, parent, …, root].
   * Для корня — [root]. Несуществующий id → [].
   */
  getAllParents(id: ItemId): TreeItem[] {
    const item = this.byId.get(id)
    if (!item) {
      return []
    }
    const result: TreeItem[] = [item]
    let current: TreeItem = item
    while (current.parent !== null) {
      const parent = this.byId.get(current.parent)
      if (!parent) {
        break
      }
      result.push(parent)
      current = parent
    }
    return result
  }

  setItems(items: TreeItem[]): void {
    this.items = [...items]
    this.rebuildIndexes(this.items)
  }

  /**
   * Новый id — append. Уже есть — replace (поля + индекс родителя при смене parent).
   */
  addItem(item: TreeItem): void {
    if (this.byId.has(item.id)) {
      this.replaceItem(item)
      return
    }
    this.items.push(item)
    this.byId.set(item.id, item)
    this.addToParentIndex(item.parent, item.id)
  }

  /**
   * Удаляет узел и всех потомков. Несуществующий id — no-op.
   */
  removeItem(id: ItemId): void {
    const root = this.byId.get(id)
    if (!root) {
      return
    }

    const toRemove = new Set<ItemId>()
    const stack: ItemId[] = [id]
    while (stack.length > 0) {
      const currentId = stack.pop()!
      if (toRemove.has(currentId)) {
        continue
      }
      toRemove.add(currentId)
      const children = this.childrenByParent.get(currentId)
      if (children) {
        for (const childId of children) {
          stack.push(childId)
        }
      }
    }

    this.removeFromParentIndex(root.parent, id)

    for (const removeId of toRemove) {
      this.byId.delete(removeId)
      this.childrenByParent.delete(removeId)
    }

    this.items = this.items.filter((item) => !toRemove.has(item.id))
  }

  /**
   * Актуализация по item.id. Нет id — no-op (не upsert).
   * Поле id не меняется; остальным полям — значения из payload.
   */
  updateItem(item: TreeItem): void {
    const existing = this.byId.get(item.id)
    if (!existing) {
      return
    }

    const oldParent = existing.parent

    for (const [key, value] of Object.entries(item)) {
      if (key === 'id') {
        continue
      }
      ;(existing as Record<string, unknown>)[key] = value
    }

    if (oldParent !== existing.parent) {
      this.removeFromParentIndex(oldParent, existing.id)
      this.addToParentIndex(existing.parent, existing.id)
    }
  }

  private replaceItem(item: TreeItem): void {
    const existing = this.byId.get(item.id)
    if (!existing) {
      return
    }

    const oldParent = existing.parent
    const id = existing.id

    for (const key of Object.keys(existing)) {
      if (key !== 'id') {
        delete (existing as Record<string, unknown>)[key]
      }
    }
    Object.assign(existing, item, { id })

    if (oldParent !== existing.parent) {
      this.removeFromParentIndex(oldParent, id)
      this.addToParentIndex(existing.parent, id)
    }
  }

  private rebuildIndexes(items: TreeItem[]): void {
    this.byId.clear()
    this.childrenByParent.clear()
    for (const item of items) {
      this.byId.set(item.id, item)
      this.addToParentIndex(item.parent, item.id)
    }
  }

  private addToParentIndex(parent: ItemId | null, id: ItemId): void {
    const siblings = this.childrenByParent.get(parent) ?? []
    siblings.push(id)
    this.childrenByParent.set(parent, siblings)
  }

  private removeFromParentIndex(parent: ItemId | null, id: ItemId): void {
    const siblings = this.childrenByParent.get(parent)
    if (!siblings) {
      return
    }
    const index = siblings.indexOf(id)
    if (index !== -1) {
      siblings.splice(index, 1)
    }
    if (siblings.length === 0) {
      this.childrenByParent.delete(parent)
    }
  }
}
