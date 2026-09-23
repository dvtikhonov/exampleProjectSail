import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import type { GridApi, IRowNode } from 'ag-grid-community'
import TreeGrid from '../src/components/TreeGrid.vue'
import TreeToolbar from '../src/components/TreeToolbar.vue'
import { useTreeData } from '../src/composables/useTreeData'
import type { TreeItem } from '../src/tree'
import { tzItems } from './fixtures/items'

/** Дождаться fetch + искусственной задержки 2 с из loadItems. */
async function waitForItemsLoaded(): Promise<void> {
  await flushPromises()
  await vi.advanceTimersByTimeAsync(2000)
  await flushPromises()
  await nextTick()
}

function categoryValueGetter(ag: {
  props: (name: string) => unknown
}): (p: { data?: TreeItem }) => string {
  const autoGroup = ag.props('autoGroupColumnDef') as {
    valueGetter: (p: { data?: TreeItem }) => string
  }
  return autoGroup.valueGetter
}

/** Mock GridApi: № п\п 1-based → data узла по индексу отображаемого списка. */
function mockGridApiForRows(rows: TreeItem[]): GridApi<TreeItem> {
  return {
    getDisplayedRowAtIndex: (index: number): IRowNode<TreeItem> | undefined => {
      const data = rows[index]
      if (!data) {
        return undefined
      }
      return { data } as IRowNode<TreeItem>
    },
  } as GridApi<TreeItem>
}

describe('TreeGrid', () => {
  beforeEach(() => {
    const { store, loading, revision } = useTreeData()
    store.setItems([])
    loading.value = true
    revision.value = 0

    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        json: async () => tzItems(),
      })),
    )
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('shows loading overlay while items are not yet loaded', async () => {
    const wrapper = mount(TreeGrid, { attachTo: document.body })
    await flushPromises()

    const ag = wrapper.findComponent({ name: 'AgGridVue' })
    expect(ag.exists()).toBe(true)
    expect(ag.props('loading')).toBe(true)
    expect(ag.props('rowData')).toEqual([])

    // дождаться loadItems, чтобы не оставить pending на singleton composable
    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()
    wrapper.unmount()
  })

  it('hides loading and exposes fixture rows after 2s delay', async () => {
    const wrapper = mount(TreeGrid, { attachTo: document.body })
    await flushPromises()

    const ag = wrapper.findComponent({ name: 'AgGridVue' })
    expect(ag.props('loading')).toBe(true)

    await waitForItemsLoaded()

    expect(ag.props('loading')).toBe(false)

    const rows = ag.props('rowData') as TreeItem[]
    expect(rows).toHaveLength(8)
    expect(rows.map((r) => r.label)).toEqual(
      expect.arrayContaining(['Айтем 1']),
    )
    expect(rows.find((r) => r.id === 1)?.label).toBe('Айтем 1')

    expect(fetch).toHaveBeenCalledWith('/items.json')

    wrapper.unmount()
  })

  it('exposes PDF column headers (№ п\\п, Категория) and tree options', async () => {
    const wrapper = mount(TreeGrid, { attachTo: document.body })
    await flushPromises()

    const ag = wrapper.findComponent({ name: 'AgGridVue' })
    const columnDefs = ag.props('columnDefs') as Array<{ headerName?: string }>
    const autoGroup = ag.props('autoGroupColumnDef') as {
      headerName?: string
      colId?: string
      valueGetter?: (p: { data?: TreeItem }) => string
    }
    const headers = [...columnDefs.map((c) => c.headerName), autoGroup.headerName]

    expect(headers).toEqual(
      expect.arrayContaining(['№ п\\п', 'Категория', 'Наименование']),
    )
    expect(autoGroup.headerName).toBe('Категория')
    expect(autoGroup.colId).toBe('category')
    expect(ag.props('treeData')).toBe(true)
    expect(ag.props('groupDefaultExpanded')).toBe(-1)
    expect(ag.props('animateRows')).toBe(true)

    await waitForItemsLoaded()

    const getDataPath = ag.props('getDataPath') as (d: { id: string | number }) => string[]
    expect(getDataPath({ id: 7 })).toEqual([
      'number:1',
      'string:91064cef',
      'number:4',
      'number:7',
    ])
    // number и string не схлопываются
    expect(getDataPath({ id: 1 })).toEqual(['number:1'])
    expect(getDataPath({ id: '91064cef' })).toEqual([
      'number:1',
      'string:91064cef',
    ])

    wrapper.unmount()
  })

  it('marks id 4 as Группа and id 5 as Элемент (by children)', async () => {
    const wrapper = mount(TreeGrid, { attachTo: document.body })
    await waitForItemsLoaded()

    const ag = wrapper.findComponent({ name: 'AgGridVue' })
    const valueGetter = categoryValueGetter(ag)
    const rows = ag.props('rowData') as TreeItem[]
    const item4 = rows.find((r) => r.id === 4)
    const item5 = rows.find((r) => r.id === 5)
    expect(item4).toBeTruthy()
    expect(item5).toBeTruthy()
    expect(valueGetter({ data: item4 })).toBe('Группа')
    expect(valueGetter({ data: item5 })).toBe('Элемент')

    wrapper.unmount()
  })

  it('removeByRowNumber cascades descendants (91064cef subtree)', async () => {
    const wrapper = mount(TreeGrid, { attachTo: document.body })
    await waitForItemsLoaded()

    const ag = wrapper.findComponent({ name: 'AgGridVue' })
    const { store, removeByRowNumber } = useTreeData()
    const before = ag.props('rowData') as TreeItem[]
    expect(before).toHaveLength(8)

    // DFS при полном раскрытии: 1, 91064cef, 4, 7, 8, 5, 6, 3 → № п\п 2 = 91064cef
    const displayed = [
      store.getItem(1)!,
      store.getItem('91064cef')!,
      store.getItem(4)!,
      store.getItem(7)!,
      store.getItem(8)!,
      store.getItem(5)!,
      store.getItem(6)!,
      store.getItem(3)!,
    ]
    removeByRowNumber(2, mockGridApiForRows(displayed))
    await nextTick()

    const after = ag.props('rowData') as TreeItem[]
    expect(after.map((r) => r.id)).toEqual([1, 3])
    expect(store.getItem('91064cef')).toBeUndefined()
    expect(store.getItem(4)).toBeUndefined()
    expect(store.getItem(7)).toBeUndefined()

    // неверный № п\п — no-op
    removeByRowNumber(99, mockGridApiForRows(after))
    await nextTick()
    expect((ag.props('rowData') as TreeItem[]).map((r) => r.id)).toEqual([1, 3])

    wrapper.unmount()
  })

  it('addItem adds a row and turns leaf parent into Группа', async () => {
    const wrapper = mount(TreeGrid, { attachTo: document.body })
    await waitForItemsLoaded()

    const ag = wrapper.findComponent({ name: 'AgGridVue' })
    const valueGetter = categoryValueGetter(ag)
    const { addItem, nextNumericId, store } = useTreeData()

    const item5 = store.getItem(5)
    expect(valueGetter({ data: item5 })).toBe('Элемент')

    // после фикстуры ТЗ: max(1,3,4,5,6,7,8)=8 → next = 9
    expect(nextNumericId()).toBe(9)

    addItem({ id: nextNumericId(), parent: 5, label: 'Новый ребёнок' })
    await nextTick()

    const rows = ag.props('rowData') as TreeItem[]
    expect(rows).toHaveLength(9)
    expect(rows.find((r) => r.id === 9)).toMatchObject({
      id: 9,
      parent: 5,
      label: 'Новый ребёнок',
    })
    expect(valueGetter({ data: store.getItem(5) })).toBe('Группа')
    // id 4 по-прежнему группа
    expect(valueGetter({ data: store.getItem(4) })).toBe('Группа')

    wrapper.unmount()
  })

  it('TreeToolbar form: add without id field then remove by № п\\п', async () => {
    const gridWrapper = mount(TreeGrid, { attachTo: document.body })
    await waitForItemsLoaded()

    const { store, nextNumericId } = useTreeData()
    const ag = gridWrapper.findComponent({ name: 'AgGridVue' })
    const valueGetter = categoryValueGetter(ag)

    const toolbar = mount(TreeToolbar, {
      props: { gridApi: null },
      attachTo: document.body,
    })

    // поле id в форме отсутствует — id = max(number)+1
    expect(toolbar.find('input[name="id"]').exists()).toBe(false)
    expect(nextNumericId()).toBe(9)

    // лист id=3 → Элемент; добавим ребёнка через форму
    expect(valueGetter({ data: store.getItem(3) })).toBe('Элемент')

    await toolbar.find('input[name="parent"]').setValue('3')
    await toolbar.find('input[name="label"]').setValue('Через toolbar')
    await toolbar.findAll('form')[0]!.trigger('submit')
    await nextTick()

    expect(store.getItem(9)).toMatchObject({
      id: 9,
      parent: 3,
      label: 'Через toolbar',
    })
    expect(valueGetter({ data: store.getItem(3) })).toBe('Группа')
    expect((ag.props('rowData') as TreeItem[]).some((r) => r.id === 9)).toBe(
      true,
    )

    // удаление по № п\п: mock отображает id 9 на строке 2
    const api = mockGridApiForRows([store.getItem(1)!, store.getItem(9)!])
    await toolbar.setProps({ gridApi: api })
    await toolbar.find('input[name="rowNum"]').setValue('2')
    await toolbar.findAll('form')[1]!.trigger('submit')
    await nextTick()

    expect(store.getItem(9)).toBeUndefined()
    expect(valueGetter({ data: store.getItem(3) })).toBe('Элемент')

    toolbar.unmount()
    gridWrapper.unmount()
  })
})
