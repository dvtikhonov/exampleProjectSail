import { computed, ref } from 'vue'
import type { GridApi } from 'ag-grid-community'
import { TreeStore } from '../tree'
import type { TreeItem } from '../tree'
import { delay } from '../utils/delay'

const store = new TreeStore([])
const loading = ref(true)
const revision = ref(0)

function bump(): void {
  revision.value++
}

const rowData = computed(() => {
  revision.value
  return store.getAll()
})

async function loadItems(): Promise<void> {
  loading.value = true
  const res = await fetch('/items.json')
  const data = (await res.json()) as TreeItem[]
  await delay(2000)
  store.setItems(data)
  bump()
  loading.value = false
}

function addItem(item: TreeItem): void {
  store.addItem(item)
  bump()
}

/**
 * Следующий числовой id: max среди number-id в store + 1;
 * string-id (напр. '91064cef') в max не участвуют; если чисел нет → 1.
 */
function nextNumericId(): number {
  let max = 0
  for (const item of store.getAll()) {
    if (typeof item.id === 'number' && item.id > max) {
      max = item.id
    }
  }
  return max + 1
}

/** Удаление по № п\п видимой строки (1-based). Неверный номер — no-op. */
function removeByRowNumber(rowNum: number, gridApi: GridApi<TreeItem>): void {
  const node = gridApi.getDisplayedRowAtIndex(rowNum - 1)
  if (!node?.data) {
    return
  }
  store.removeItem(node.data.id)
  bump()
}

export function useTreeData() {
  return {
    store,
    loading,
    revision,
    rowData,
    loadItems,
    bump,
    addItem,
    nextNumericId,
    removeByRowNumber,
  }
}
