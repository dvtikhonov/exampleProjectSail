<script setup lang="ts">
import { onMounted, shallowRef, watch } from 'vue'
import { AgGridVue } from 'ag-grid-vue3'
import {
  AllCommunityModule,
  ModuleRegistry,
  themeAlpine,
  type CellClassParams,
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

const emit = defineEmits<{
  'grid-ready': [api: GridApi<TreeItem>]
}>()

const { store, loading, rowData, loadItems } = useTreeData()

const gridApi = shallowRef<GridApi<TreeItem> | null>(null)

/** Тема ближе к макету PDF: тонкие линии, светлый хедер. */
const gridTheme = themeAlpine.withParams({
  borderColor: '#d0d7de',
  rowBorder: true,
  columnBorder: true,
  headerBackgroundColor: '#ffffff',
  headerFontWeight: 600,
  fontFamily: 'Segoe UI, system-ui, sans-serif',
  fontSize: 14,
  headerFontSize: 14,
  spacing: 6,
  cellHorizontalPadding: 12,
})

function isGroup(item: TreeItem | undefined | null): boolean {
  if (!item) {
    return false
  }
  return store.getChildren(item.id).length > 0
}

function categoryLabel(item: TreeItem | undefined | null): string {
  if (!item) {
    return ''
  }
  return isGroup(item) ? 'Группа' : 'Элемент'
}

/**
 * Колонки 1:1 по макету PDF (стр. 3):
 * № п\п | Категория (tree: Группа/Элемент) | Наименование
 */
const columnDefs: ColDef<TreeItem>[] = [
  {
    colId: 'rowNum',
    headerName: '№ п\\п',
    width: 90,
    maxWidth: 110,
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
    minWidth: 220,
    suppressHeaderMenuButton: true,
    cellClass: 'tree-grid__cell-label',
  },
]

/** Tree-колонка «Категория»: иерархия + Группа/Элемент по наличию детей (§5.4). */
const autoGroupColumnDef: ColDef<TreeItem> = {
  colId: 'category',
  headerName: 'Категория',
  minWidth: 200,
  flex: 1,
  suppressHeaderMenuButton: true,
  valueGetter: (params: ValueGetterParams<TreeItem>) => categoryLabel(params.data),
  cellClassRules: {
    'tree-grid__category--group': (params: CellClassParams<TreeItem>) =>
      isGroup(params.data),
    'tree-grid__category--element': (params: CellClassParams<TreeItem>) =>
      !!params.data && !isGroup(params.data),
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

/**
 * Смешанные id (number | string) без схлопывания 1 ↔ "1".
 * Путь: корень → … → узел (reverse от getAllParents).
 */
const getDataPath: GetDataPath<TreeItem> = (data) =>
  store
    .getAllParents(data.id)
    .map((item) => `${typeof item.id}:${item.id}`)
    .reverse()

const getRowId = (params: GetRowIdParams<TreeItem>): string =>
  `${typeof params.data.id}:${params.data.id}`

/** Порядок колонок PDF: № п\п → Категория (auto) → Наименование. */
function applyPdfColumnOrder(api: GridApi<TreeItem>): void {
  const rowNum = api.getColumn('rowNum')
  if (rowNum) {
    api.moveColumns([rowNum.getColId()], 0)
  }
}

function onGridReady(event: GridReadyEvent<TreeItem>): void {
  gridApi.value = event.api
  applyPdfColumnOrder(event.api)
  emit('grid-ready', event.api)
}

watch(rowData, (rows) => {
  const api = gridApi.value
  if (api) {
    api.setGridOption('rowData', rows)
    applyPdfColumnOrder(api)
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

.tree-grid__cell-label {
  font-weight: 700;
  color: #1f2328;
}

/* Макет PDF: Группа — жирный чёрный; Элемент — обычный серый */
.tree-grid__category--group {
  font-weight: 700;
  color: #1f2328;
}

.tree-grid__category--element {
  font-weight: 400;
  color: #8b949e;
}

.tree-grid .ag-header-cell-label {
  font-weight: 600;
}
</style>
