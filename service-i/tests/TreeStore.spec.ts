import { describe, expect, it } from 'vitest'
import { TreeStore } from '../src/tree'
import type { TreeItem } from '../src/tree'
import { tzItems } from './fixtures/items'

function idsOf(items: TreeItem[]): Array<string | number> {
  return items.map((item) => item.id)
}

describe('TreeStore', () => {
  describe('getAll', () => {
    it('возвращает все 8 элементов фикстуры ТЗ', () => {
      const store = new TreeStore(tzItems())
      expect(store.getAll()).toHaveLength(8)
    })

    it('возвращает shallow copy — мутация результата не меняет store', () => {
      const store = new TreeStore(tzItems())
      const copy = store.getAll()
      copy.pop()
      copy[0] = { id: 999, parent: null, label: 'hacked' }
      expect(store.getAll()).toHaveLength(8)
      expect(store.getItem(1)?.label).toBe('Айтем 1')
    })
  })

  describe('getItem', () => {
    it('находит number и string id', () => {
      const store = new TreeStore(tzItems())
      expect(store.getItem(1)?.label).toBe('Айтем 1')
      expect(store.getItem('91064cef')?.label).toBe('Айтем 2')
    })

    it('не схлопывает number и string id', () => {
      const store = new TreeStore([
        { id: 1, parent: null, label: 'num' },
        { id: '1', parent: null, label: 'str' },
      ])
      expect(store.getItem(1)?.label).toBe('num')
      expect(store.getItem('1')?.label).toBe('str')
    })

    it('неизвестный id → undefined', () => {
      const store = new TreeStore(tzItems())
      expect(store.getItem(999)).toBeUndefined()
      expect(store.getItem('missing')).toBeUndefined()
    })
  })

  describe('getChildren', () => {
    it('возвращает прямых детей корня 1', () => {
      const store = new TreeStore(tzItems())
      expect(idsOf(store.getChildren(1))).toEqual(['91064cef', 3])
    })

    it('лист → []', () => {
      const store = new TreeStore(tzItems())
      expect(store.getChildren(8)).toEqual([])
    })

    it('несуществующий id → []', () => {
      const store = new TreeStore(tzItems())
      expect(store.getChildren(999)).toEqual([])
    })
  })

  describe('getAllChildren', () => {
    it('состав потомков 1 без жёсткого порядка', () => {
      const store = new TreeStore(tzItems())
      const ids = idsOf(store.getAllChildren(1))
      expect(ids).toHaveLength(7)
      expect(ids).toEqual(expect.arrayContaining(['91064cef', 3, 4, 5, 6, 7, 8]))
    })

    it('состав потомков 4 — {7, 8}', () => {
      const store = new TreeStore(tzItems())
      const ids = idsOf(store.getAllChildren(4))
      expect(ids).toHaveLength(2)
      expect(ids).toEqual(expect.arrayContaining([7, 8]))
    })

    it('лист / несуществующий → []', () => {
      const store = new TreeStore(tzItems())
      expect(store.getAllChildren(8)).toEqual([])
      expect(store.getAllChildren(999)).toEqual([])
    })
  })

  describe('getAllParents', () => {
    it('цепочка от 7 к корню — порядок контракт', () => {
      const store = new TreeStore(tzItems())
      expect(idsOf(store.getAllParents(7))).toEqual([7, 4, '91064cef', 1])
    })

    it('корень → [root]', () => {
      const store = new TreeStore(tzItems())
      expect(idsOf(store.getAllParents(1))).toEqual([1])
    })

    it('несуществующий → []', () => {
      const store = new TreeStore(tzItems())
      expect(store.getAllParents(999)).toEqual([])
    })
  })

  describe('setItems', () => {
    it('полностью заменяет данные', () => {
      const store = new TreeStore(tzItems())
      store.setItems([{ id: 100, parent: null, label: 'only' }])
      expect(store.getAll()).toHaveLength(1)
      expect(store.getItem(1)).toBeUndefined()
      expect(store.getItem(100)?.label).toBe('only')
    })
  })

  describe('addItem', () => {
    it('добавляет новый элемент', () => {
      const store = new TreeStore(tzItems())
      store.addItem({ id: 9, parent: 3, label: 'Айтем 9' })
      expect(store.getItem(9)?.label).toBe('Айтем 9')
      expect(idsOf(store.getChildren(3))).toEqual([9])
      expect(store.getAll()).toHaveLength(9)
    })

    it('существующий id → replace (включая смену parent)', () => {
      const store = new TreeStore(tzItems())
      store.addItem({ id: 5, parent: 3, label: 'replaced-5', extra: true })
      expect(store.getItem(5)).toMatchObject({
        id: 5,
        parent: 3,
        label: 'replaced-5',
        extra: true,
      })
      expect(idsOf(store.getChildren('91064cef'))).toEqual([4, 6])
      expect(idsOf(store.getChildren(3))).toEqual([5])
      expect(store.getAll()).toHaveLength(8)
    })
  })

  describe('removeItem', () => {
    it('каскадно удаляет поддерево 91064cef', () => {
      const store = new TreeStore(tzItems())
      store.removeItem('91064cef')
      expect(idsOf(store.getAll()).sort((a, b) => String(a).localeCompare(String(b)))).toEqual([
        1,
        3,
      ])
      expect(store.getItem('91064cef')).toBeUndefined()
      expect(store.getItem(4)).toBeUndefined()
      expect(store.getItem(7)).toBeUndefined()
      expect(idsOf(store.getChildren(1))).toEqual([3])
    })

    it('несуществующий id → no-op без исключения', () => {
      const store = new TreeStore(tzItems())
      expect(() => store.removeItem(999)).not.toThrow()
      expect(store.getAll()).toHaveLength(8)
    })
  })

  describe('updateItem', () => {
    it('меняет label и parent', () => {
      const store = new TreeStore(tzItems())
      store.updateItem({ id: 5, parent: 3, label: 'updated-5' })
      expect(store.getItem(5)).toMatchObject({ parent: 3, label: 'updated-5' })
      expect(idsOf(store.getChildren('91064cef'))).toEqual([4, 6])
      expect(idsOf(store.getChildren(3))).toEqual([5])
    })

    it('несуществующий id → no-op (не upsert)', () => {
      const store = new TreeStore(tzItems())
      store.updateItem({ id: 999, parent: null, label: 'ghost' })
      expect(store.getItem(999)).toBeUndefined()
      expect(store.getAll()).toHaveLength(8)
    })

    it('игнорирует поле id при обновлении — ключ в store не меняется', () => {
      const store = new TreeStore(tzItems())
      const before = store.getItem(5)
      store.updateItem({ id: 5, parent: '91064cef', label: 'keep-5' })
      expect(store.getItem(5)?.id).toBe(5)
      expect(store.getItem(5)?.label).toBe('keep-5')
      expect(store.getItem(5)).toBe(before)
      expect(store.getItem(50)).toBeUndefined()
    })
  })
})
