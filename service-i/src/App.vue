<script setup lang="ts">
import { shallowRef } from 'vue'
import type { GridApi } from 'ag-grid-community'
import TreeGrid from './components/TreeGrid.vue'
import TreeToolbar from './components/TreeToolbar.vue'
import type { TreeItem } from './tree'

const gridApi = shallowRef<GridApi<TreeItem> | null>(null)

function onGridReady(api: GridApi<TreeItem>): void {
  gridApi.value = api
}
</script>

<template>
  <div class="min-h-full bg-slate-100 px-4 py-6 sm:px-8">
    <header class="mb-4">
      <h1 class="text-xl font-semibold text-slate-900">MStroy Frontend</h1>
      <p class="mt-1 text-sm text-slate-600">TreeStore + AgGrid Enterprise (tree data)</p>
    </header>
    <TreeGrid @grid-ready="onGridReady" />
    <TreeToolbar :grid-api="gridApi" />
  </div>
</template>
