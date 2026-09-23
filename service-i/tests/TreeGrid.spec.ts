import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import TreeGrid from '../src/components/TreeGrid.vue'
import { useTreeData } from '../src/composables/useTreeData'
import type { TreeItem } from '../src/tree'
import { tzItems } from './fixtures/items'

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

    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()
    await nextTick()

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
    const autoGroup = ag.props('autoGroupColumnDef') as { headerName?: string }
    const headers = [...columnDefs.map((c) => c.headerName), autoGroup.headerName]

    expect(headers).toEqual(
      expect.arrayContaining(['№ п\\п', 'Категория', 'Наименование']),
    )
    expect(ag.props('treeData')).toBe(true)
    expect(ag.props('groupDefaultExpanded')).toBe(-1)

    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()
    await nextTick()

    const getDataPath = ag.props('getDataPath') as (d: { id: string | number }) => string[]
    expect(getDataPath({ id: 7 })).toEqual([
      'number:1',
      'string:91064cef',
      'number:4',
      'number:7',
    ])

    wrapper.unmount()
  })
})
