import { computed, ref } from 'vue'
import { TreeStore } from '../tree'
import type { TreeItem } from '../tree'
import { delay } from '../utils/delay'

const store = new TreeStore([])
const loading = ref(true)
const revision = ref(0)

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
  revision.value++
  loading.value = false
}

export function useTreeData() {
  return {
    store,
    loading,
    revision,
    rowData,
    loadItems,
  }
}
