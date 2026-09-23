<script setup lang="ts">
import { ref } from 'vue'
import type { GridApi } from 'ag-grid-community'
import { useTreeData } from '../composables/useTreeData'
import type { ItemId, TreeItem } from '../tree'

const props = defineProps<{
  gridApi: GridApi<TreeItem> | null
}>()

const { addItem, nextNumericId, removeByRowNumber } = useTreeData()

const parentInput = ref('')
const labelInput = ref('')
const rowNumInput = ref('')

/** Числовая строка без лишних символов → number, иначе string. */
function parseId(raw: string): ItemId {
  const trimmed = raw.trim()
  if (/^-?\d+$/.test(trimmed)) {
    return Number(trimmed)
  }
  return trimmed
}

function onAdd(): void {
  const parentRaw = parentInput.value.trim()
  const parent: ItemId | null = parentRaw === '' ? null : parseId(parentRaw)
  const label = labelInput.value.trim()
  const id = nextNumericId()

  addItem({
    id,
    parent,
    ...(label !== '' ? { label } : {}),
  })

  parentInput.value = ''
  labelInput.value = ''
}

function onRemove(): void {
  const api = props.gridApi
  if (!api) {
    return
  }
  const n = Number(rowNumInput.value)
  if (!Number.isInteger(n) || n < 1) {
    return
  }
  removeByRowNumber(n, api)
  rowNumInput.value = ''
}
</script>

<template>
  <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
    <form
      class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end"
      @submit.prevent="onAdd"
    >
      <label class="flex flex-col gap-1 text-sm text-slate-700">
        <span>parent</span>
        <input
          v-model="parentInput"
          type="text"
          name="parent"
          placeholder="пусто = корень"
          class="rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-900 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          autocomplete="off"
        />
      </label>
      <label class="flex flex-col gap-1 text-sm text-slate-700">
        <span>label</span>
        <input
          v-model="labelInput"
          type="text"
          name="label"
          class="rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-900 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          autocomplete="off"
        />
      </label>
      <button
        type="submit"
        class="rounded bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-1"
      >
        Добавить
      </button>
    </form>

    <form
      class="flex flex-col gap-2 sm:flex-row sm:items-end"
      @submit.prevent="onRemove"
    >
      <label class="flex flex-col gap-1 text-sm text-slate-700">
        <span>№ п\п</span>
        <input
          v-model="rowNumInput"
          type="number"
          name="rowNum"
          min="1"
          step="1"
          class="w-24 rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-900 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
        />
      </label>
      <button
        type="submit"
        class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-1"
      >
        Удалить
      </button>
    </form>
  </div>
</template>
