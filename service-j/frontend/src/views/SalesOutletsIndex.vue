<script setup>
import ColumnFiltersModal from '@/components/ColumnFiltersModal.vue';
import ColumnSettingsModal from '@/components/ColumnSettingsModal.vue';
import SalesOutletEditModal from '@/components/SalesOutletEditModal.vue';
import SalesOutletsPagination from '@/components/SalesOutletsPagination.vue';
import SalesOutletsSearchBar from '@/components/SalesOutletsSearchBar.vue';
import SalesOutletsTable from '@/components/SalesOutletsTable.vue';
import SalesOutletsToolbar from '@/components/SalesOutletsToolbar.vue';
import { usePersistentTableSettings } from '@/composables/usePersistentTableSettings';
import { clearApiToken, readStoredApiToken, setApiToken } from '@/services/apiToken';
import { SalesOutletValidationError, createSalesOutletsClient } from '@/services/salesOutlets';
import { computed, onMounted, ref, watch } from 'vue';

const API_PREFIX = '/api/j';
const STORAGE_KEY = 'objects-sales-outlets:service-j';
const client = createSalesOutletsClient(API_PREFIX);

const columns = ref([]);
const rows = ref([]);
const statusOptions = ref([{ value: '', label: 'Все статусы' }]);
const filters = ref({
    search: '',
    status: '',
    column_filters: {},
    sort: 'id',
    direction: 'asc',
    page: 1,
    per_page: 15,
    columns: [],
});
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
});

const selectedColumns = ref([]);
const columnFilters = ref({});
const tableSettings = ref(null);

const isLoading = ref(false);
const loadError = ref('');
const authHint = ref(false);
const manualToken = ref('');
const showTokenForm = ref(false);

const modalIds = Object.freeze({
    columns: 'columns',
    filters: 'filters',
});
const activeModal = ref(null);
const editingSalesOutlet = ref(null);
const isEditModalOpen = ref(false);
const isSavingSalesOutlet = ref(false);
const editModalError = ref('');
const editFieldErrors = ref({});
const isDeletingId = ref(null);
const deleteError = ref('');

const isColumnModalOpen = computed(() => activeModal.value === modalIds.columns);
const isFilterModalOpen = computed(() => activeModal.value === modalIds.filters);
const hasActiveColumnFilters = computed(() => Object.keys(columnFilters.value).length > 0);
const editStatusOptions = computed(() => statusOptions.value.filter(
    (option) => String(option?.value ?? '').trim() !== '',
));

const ensureTableSettings = () => {
    if (!columns.value.length) {
        return;
    }

    tableSettings.value = usePersistentTableSettings(STORAGE_KEY, columns.value);

    if (tableSettings.value.savedColumns) {
        selectedColumns.value = [...tableSettings.value.savedColumns];
    } else if (!selectedColumns.value.length) {
        selectedColumns.value = [...(filters.value.columns ?? columns.value.map((c) => c.key))];
    }

    if (tableSettings.value.savedFilters && Object.keys(columnFilters.value).length === 0) {
        columnFilters.value = { ...tableSettings.value.savedFilters };
    }
};

const applyIndexPayload = (payload) => {
    const meta = payload.meta ?? {};
    columns.value = Array.isArray(meta.columns) ? meta.columns : [];
    rows.value = Array.isArray(payload.data) ? payload.data.map((row) => ({ ...row })) : [];
    statusOptions.value = Array.isArray(meta.status_options) && meta.status_options.length
        ? meta.status_options
        : [{ value: '', label: 'Все статусы' }];

    const nextFilters = meta.filters ?? {};
    filters.value = {
        search: nextFilters.search ?? '',
        status: nextFilters.status ?? '',
        column_filters: nextFilters.column_filters ?? {},
        sort: nextFilters.sort ?? 'id',
        direction: nextFilters.direction ?? 'asc',
        page: nextFilters.page ?? 1,
        per_page: nextFilters.per_page ?? 15,
        columns: nextFilters.columns ?? columns.value.map((c) => c.key),
    };
    pagination.value = {
        current_page: meta.pagination?.current_page ?? 1,
        last_page: meta.pagination?.last_page ?? 1,
        per_page: meta.pagination?.per_page ?? filters.value.per_page,
        total: meta.pagination?.total ?? 0,
        from: meta.pagination?.from ?? 0,
        to: meta.pagination?.to ?? 0,
    };

    ensureTableSettings();

    if (!selectedColumns.value.length) {
        selectedColumns.value = [...filters.value.columns];
    }
};

const buildQuery = (overrides = {}) => ({
    search: filters.value.search,
    status: filters.value.status,
    sort: filters.value.sort,
    direction: filters.value.direction,
    page: 1,
    per_page: filters.value.per_page,
    columns: selectedColumns.value,
    column_filters: columnFilters.value,
    ...overrides,
});

const loadSalesOutlets = async (overrides = {}) => {
    isLoading.value = true;
    loadError.value = '';
    deleteError.value = '';
    authHint.value = false;

    try {
        const query = buildQuery(overrides);
        const payload = await client.index(query);
        applyIndexPayload(payload);

        if (overrides.columns) {
            selectedColumns.value = [...overrides.columns];
        }
        if (overrides.column_filters !== undefined) {
            columnFilters.value = { ...overrides.column_filters };
        }
    } catch (error) {
        loadError.value = error?.message ?? 'Не удалось загрузить данные';
        authHint.value = String(loadError.value).toLowerCase().includes('токен')
            || String(loadError.value).toLowerCase().includes('сессия');
        showTokenForm.value = authHint.value;
    } finally {
        isLoading.value = false;
    }
};

const sortBy = (columnKey) => {
    const direction =
        filters.value.sort === columnKey && filters.value.direction === 'asc'
            ? 'desc'
            : 'asc';

    loadSalesOutlets({
        sort: columnKey,
        direction,
        page: pagination.value.current_page,
    });
};

const changePage = (page) => {
    if (page < 1 || page > pagination.value.last_page) {
        return;
    }

    loadSalesOutlets({ page });
};

const applySearchStatus = (payload) => {
    loadSalesOutlets({
        search: payload.search,
        status: payload.status,
        per_page: payload.per_page,
        page: 1,
    });
};

const applyColumnFilters = (nextFilters) => {
    columnFilters.value = { ...nextFilters };
    activeModal.value = null;
    tableSettings.value?.saveFilters(columnFilters.value);
    loadSalesOutlets({ column_filters: columnFilters.value, page: 1 });
};

const clearColumnFilters = () => {
    columnFilters.value = {};
    activeModal.value = null;
    tableSettings.value?.saveFilters({});
    loadSalesOutlets({ column_filters: {}, page: 1 });
};

const applyColumns = (nextColumns) => {
    selectedColumns.value = nextColumns;
    activeModal.value = null;
    tableSettings.value?.saveColumns(nextColumns);
    loadSalesOutlets({ columns: nextColumns, page: 1 });
};

const openEditModal = (row) => {
    editingSalesOutlet.value = { ...row };
    editModalError.value = '';
    editFieldErrors.value = {};
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    if (isSavingSalesOutlet.value) {
        return;
    }

    isEditModalOpen.value = false;
    editingSalesOutlet.value = null;
    editModalError.value = '';
    editFieldErrors.value = {};
};

const finishEditModal = () => {
    isEditModalOpen.value = false;
    editingSalesOutlet.value = null;
    editModalError.value = '';
    editFieldErrors.value = {};
};

const replaceSalesOutlet = (updatedRow) => {
    rows.value = rows.value.map((row) => (
        row.id === updatedRow.id
            ? { ...row, ...updatedRow }
            : row
    ));
};

const saveSalesOutlet = async (payload) => {
    if (!editingSalesOutlet.value) {
        return;
    }

    isSavingSalesOutlet.value = true;
    editModalError.value = '';
    editFieldErrors.value = {};

    try {
        const updatedRow = await client.updateSalesOutlet(editingSalesOutlet.value.id, payload);
        replaceSalesOutlet(updatedRow);
        finishEditModal();
    } catch (error) {
        if (error instanceof SalesOutletValidationError) {
            editFieldErrors.value = error.errors;
            editModalError.value = error.message;
            return;
        }

        editModalError.value = error?.message ?? 'Не удалось сохранить объект продаж.';
    } finally {
        isSavingSalesOutlet.value = false;
    }
};

const deleteSalesOutlet = async (row) => {
    if (!window.confirm(`Удалить объект продаж #${row.id}?`)) {
        return;
    }

    isDeletingId.value = row.id;
    deleteError.value = '';

    try {
        await client.deleteSalesOutlet(row.id);
        await loadSalesOutlets({ page: pagination.value.current_page });
    } catch (error) {
        deleteError.value = error?.message ?? 'Не удалось удалить объект продаж.';
    } finally {
        isDeletingId.value = null;
    }
};

const saveManualToken = () => {
    const token = manualToken.value.trim();
    if (!token) {
        return;
    }

    setApiToken(token);
    showTokenForm.value = false;
    loadSalesOutlets();
};

const clearTokenAndRetry = () => {
    clearApiToken();
    manualToken.value = '';
    showTokenForm.value = true;
};

watch(
    () => columns.value,
    () => {
        ensureTableSettings();
    },
);

onMounted(async () => {
    if (readStoredApiToken()) {
        manualToken.value = readStoredApiToken();
    }

    // До первого ответа API: подтянуть сохранённые колонки/фильтры из localStorage
    // (после meta.columns перезапишем валидными ключами в ensureTableSettings).
    try {
        const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');
        if (Array.isArray(raw.columns) && raw.columns.length) {
            selectedColumns.value = [...raw.columns];
        }
        if (raw.column_filters && typeof raw.column_filters === 'object') {
            columnFilters.value = { ...raw.column_filters };
        }
    } catch {
        // ignore
    }

    await loadSalesOutlets();
});
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100">
    <header class="border-b border-slate-800 bg-slate-900/90">
      <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-300">
            service-j · Vue 3
          </p>
          <h1 class="mt-2 text-2xl font-semibold leading-tight text-white">
            Объекты продаж 4
          </h1>
          <p class="mt-1 text-sm text-slate-400">
            Таблица торговых точек через `/api/j` (same-origin gateway).
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-cyan-400 hover:text-white"
            @click="showTokenForm = !showTokenForm"
          >
            API-токен
          </button>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-cyan-400 hover:text-white disabled:opacity-50"
            :disabled="isLoading"
            @click="loadSalesOutlets({ page: pagination.current_page })"
          >
            Обновить
          </button>
        </div>
      </div>
    </header>

    <main class="py-8">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div
          v-if="showTokenForm"
          class="mb-6 rounded-2xl border border-slate-700 bg-slate-900/80 p-4 shadow-xl shadow-black/20"
        >
          <p class="text-sm text-slate-300">
            Токен берётся из сессии main-app (`GET /get-api-token` на gateway) или вставьте Passport Bearer вручную.
          </p>
          <div class="mt-3 flex flex-col gap-3 sm:flex-row">
            <input
              v-model="manualToken"
              type="password"
              autocomplete="off"
              placeholder="Bearer token"
              class="block w-full rounded-md border-slate-700 bg-slate-950 text-sm text-slate-100 shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400"
            >
            <button
              type="button"
              class="inline-flex shrink-0 items-center justify-center rounded-lg border border-transparent bg-cyan-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 transition hover:bg-cyan-400"
              @click="saveManualToken"
            >
              Сохранить токен
            </button>
            <button
              type="button"
              class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-200 transition hover:border-cyan-400"
              @click="clearTokenAndRetry"
            >
              Сбросить
            </button>
          </div>
        </div>

        <p
          v-if="loadError"
          class="mb-4 rounded-xl border border-rose-400/30 bg-rose-950/40 px-4 py-3 text-sm text-rose-200"
          role="alert"
        >
          {{ loadError }}
          <span
            v-if="authHint"
            class="mt-1 block text-rose-300/80"
          >
            Войдите в main-app на том же gateway (localhost:8080) или укажите токен выше.
          </span>
        </p>

        <p
          v-if="deleteError"
          class="mb-4 rounded-xl border border-rose-400/30 bg-rose-950/40 px-4 py-3 text-sm text-rose-200"
          role="alert"
        >
          {{ deleteError }}
        </p>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl shadow-black/30">
          <SalesOutletsSearchBar
            :search="filters.search"
            :status="filters.status"
            :status-options="statusOptions"
            :per-page="filters.per_page"
            :is-loading="isLoading"
            @apply="applySearchStatus"
          />

          <SalesOutletsToolbar
            :has-active-filters="hasActiveColumnFilters"
            @open-columns="activeModal = modalIds.columns"
            @open-filters="activeModal = modalIds.filters"
          />

          <div
            v-if="isLoading && rows.length === 0"
            class="px-6 py-16 text-center text-sm text-slate-400"
          >
            Загрузка…
          </div>

          <SalesOutletsTable
            v-else
            :columns="columns"
            :rows="rows"
            :visible-columns="selectedColumns.length ? selectedColumns : columns.map((c) => c.key)"
            :sort="filters.sort"
            :direction="filters.direction"
            :api-prefix="API_PREFIX"
            :is-deleting-id="isDeletingId"
            @sort="sortBy"
            @edit="openEditModal"
            @delete="deleteSalesOutlet"
            @row-updated="replaceSalesOutlet"
          />

          <SalesOutletsPagination
            :pagination="pagination"
            @change-page="changePage"
          />
        </div>
      </div>
    </main>

    <ColumnSettingsModal
      :show="isColumnModalOpen"
      :columns="columns"
      :selected-columns="selectedColumns.length ? selectedColumns : columns.map((c) => c.key)"
      @close="activeModal = null"
      @apply="applyColumns"
    />

    <ColumnFiltersModal
      :show="isFilterModalOpen"
      :columns="columns"
      :filters="columnFilters"
      @close="activeModal = null"
      @clear="clearColumnFilters"
      @apply="applyColumnFilters"
    />

    <SalesOutletEditModal
      :show="isEditModalOpen"
      :row="editingSalesOutlet"
      :is-saving="isSavingSalesOutlet"
      :server-error="editModalError"
      :field-errors="editFieldErrors"
      :status-options="editStatusOptions"
      @close="closeEditModal"
      @save="saveSalesOutlet"
    />
  </div>
</template>
