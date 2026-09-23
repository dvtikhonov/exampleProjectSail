<script setup lang="ts">
import { onMounted, shallowRef, watch } from 'vue'
import { AgGridVue } from 'ag-grid-vue3'
import {
  AllCommunityModule,
  ModuleRegistry,
  themeAlpine,
  type ColDef,
  type GetDataPath,
  type GetRowIdParams,
  type GridApi,
  type GridReadyEvent,
  type ValueGetterParams,
} from 'ag-grid-community'
import { TreeDataModule } from 'ag-grid-enterprise'
import { useTreeData } from '../composables/useTreeData'
import type { TreeItem } from '../tree'

ModuleRegistry.registerModules([AllCommunityModule, TreeDataModule])

const { store, loading, rowData, loadItems } = useTreeData()

const gridApi = shallowRef<GridApi<TreeItem> | null>(null)

const gridTheme = themeAlpine.withParams({
  borderColor: '#d0d7de',
  rowBorder: true,
  headerBackgroundColor: '#f6f8fa',
  fontFamily: 'Segoe UI, system-ui, sans-serif',
  fontSize: 14,
  headerFontSize: 14,
  spacing: 6,
})

const columnDefs: ColDef<TreeItem>[] = [
  {
    colId: 'rowNum',
    headerName: '№ п\\п',
    width: 80,
    maxWidth: 100,
    suppressHeaderMenuButton: true,
    valueGetter: (params: ValueGetterParams<TreeItem>) => {
      if (params.node == null || params.node.rowIndex == null) {
        return ''
      }
      return params.node.rowIndex + 1
    },
    cellClass: 'tree-grid__cell-row-num',
  },
  {
    colId: 'label',
    headerName: 'Наименование',
    field: 'label',
    flex: 1,
    minWidth: 200,
    suppressHeaderMenuButton: true,
  },
]

const autoGroupColumnDef: ColDef<TreeItem> = {
  headerName: 'Категория',
  minWidth: 180,
  flex: 1,
  suppressHeaderMenuButton: true,
  valueGetter: (params: ValueGetterParams<TreeItem>) => {
    const item = params.data
    if (!item) {
      return ''
    }
    return store.getChildren(item.id).length > 0 ? 'Группа' : 'Элемент'
  },
  cellRendererParams: {
    suppressCount: true,
  },
}

const defaultColDef: ColDef<TreeItem> = {
  sortable: false,
  resizable: true,
  suppressMovable: true,
}

const getDataPath: GetDataPath<TreeItem> = (data) =>
  store
    .getAllParents(data.id)
    .map((item) => `${typeof item.id}:${item.id}`)
    .reverse()

const getRowId = (params: GetRowIdParams<TreeItem>): string =>
  `${typeof params.data.id}:${params.data.id}`

function moveRowNumFirst(api: GridApi<TreeItem>): void {
  const rowNum = api.getColumn('rowNum')
  if (rowNum) {
    api.moveColumns([rowNum.getColId()], 0)
  }
}

function onGridReady(event: GridReadyEvent<TreeItem>): void {
  gridApi.value = event.api
  moveRowNumFirst(event.api)
}

watch(rowData, (rows) => {
  const api = gridApi.value
  if (api) {
    api.setGridOption('rowData', rows)
    moveRowNumFirst(api)
  }
})

onMounted(() => {
  void loadItems()
})
</script>

<template>
  <div class="tree-grid relative h-[70vh] w-full overflow-hidden rounded border border-slate-300 bg-white">
    <AgGridVue
      class="tree-grid__ag h-full w-full"
      :theme="gridTheme"
      :column-defs="columnDefs"
      :default-col-def="defaultColDef"
      :auto-group-column-def="autoGroupColumnDef"
      :row-data="rowData"
      :tree-data="true"
      :group-default-expanded="-1"
      :animate-rows="true"
      :loading="loading"
      :get-data-path="getDataPath"
      :get-row-id="getRowId"
      @grid-ready="onGridReady"
    />
  </div>
</template>

<style>
.tree-grid__cell-row-num {
  text-align: center;
}

.tree-grid .ag-header-cell-label {
  font-weight: 600;
}
</style>
