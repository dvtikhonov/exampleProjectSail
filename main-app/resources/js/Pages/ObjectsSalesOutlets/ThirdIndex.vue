<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DarkColumnFiltersModal from '@/Components/ObjectsSalesOutlets/DarkColumnFiltersModal.vue';
import DarkColumnSettingsModal from '@/Components/ObjectsSalesOutlets/DarkColumnSettingsModal.vue';
import DarkSalesOutletEditModal from '@/Components/ObjectsSalesOutlets/DarkSalesOutletEditModal.vue';
import DarkSalesOutletsPagination from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsPagination.vue';
import DarkSalesOutletsTable from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsTable.vue';
import DarkSalesOutletsToolbar from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsToolbar.vue';
import { usePersistentTableSettings } from '@/Composables/usePersistentTableSettings';
import { resolveSalesOutletsRoute } from '@/Composables/useSalesOutletsRoutes';
import { SalesOutletValidationError, createSalesOutletsClient } from '@/Services/salesOutlets';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const apiPrefix = '/api/e';
const salesOutletsClient = createSalesOutletsClient(apiPrefix);

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    salesOutlets: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    pagination: {
        type: Object,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
    routes: {
        type: Object,
        required: true,
    },
});

const tableSettings = usePersistentTableSettings('objects-sales-outlets:third-index', props.columns);
const selectedColumns = ref(tableSettings.savedColumns ?? [...props.filters.columns]);
const columnFilters = ref(tableSettings.savedFilters ?? { ...props.filters.column_filters });
const modalIds = Object.freeze({
    columns: 'columns',
    filters: 'filters',
});
const activeModal = ref(null);
const localSalesOutlets = ref(props.salesOutlets.map((row) => ({ ...row })));
const editingSalesOutlet = ref(null);
const isEditModalOpen = ref(false);
const isSavingSalesOutlet = ref(false);
const editModalError = ref('');
const editFieldErrors = ref({});
const isColumnModalOpen = computed(() => activeModal.value === modalIds.columns);
const isFilterModalOpen = computed(() => activeModal.value === modalIds.filters);
const hasActiveColumnFilters = computed(() => Object.keys(columnFilters.value).length > 0);
const isSameArray = (first, second) =>
    first.length === second.length && first.every((value, index) => value === second[index]);
const isSameObject = (first, second) => {
    const firstEntries = Object.entries(first);
    const secondEntries = Object.entries(second);

    return firstEntries.length === secondEntries.length
        && firstEntries.every(([key, value]) => second[key] === value);
};
const hasSavedTableSettings = tableSettings.savedColumns || tableSettings.savedFilters;
const currentSettingsMatchSaved =
    isSameArray(selectedColumns.value, props.filters.columns)
    && isSameObject(columnFilters.value, props.filters.column_filters);

if (hasSavedTableSettings && !currentSettingsMatchSaved) {
    try {
        router.get(
            resolveSalesOutletsRoute(props.routes, 'index'),
            {
                sort: props.filters.sort,
                direction: props.filters.direction,
                page: props.filters.page,
                per_page: props.filters.per_page,
                columns: selectedColumns.value,
                column_filters: columnFilters.value,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    } catch (error) {
        console.error(error?.message ?? error);
    }
}

watch(
    () => props.salesOutlets,
    (salesOutlets) => {
        localSalesOutlets.value = salesOutlets.map((row) => ({ ...row }));
    },
);

watch(
    () => props.filters.columns,
    (columns) => {
        selectedColumns.value = [...columns];
    },
);

watch(
    () => props.filters.column_filters,
    (filters) => {
        columnFilters.value = { ...filters };
    },
);

const visitSalesOutlets = (overrides = {}) => {
    router.get(
        resolveSalesOutletsRoute(props.routes, 'index'),
        {
            sort: props.filters.sort,
            direction: props.filters.direction,
            page: 1,
            per_page: props.filters.per_page,
            columns: selectedColumns.value,
            column_filters: columnFilters.value,
            ...overrides,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const sortBy = (columnKey) => {
    const direction =
        props.filters.sort === columnKey && props.filters.direction === 'asc'
            ? 'desc'
            : 'asc';

    visitSalesOutlets({
        sort: columnKey,
        direction,
        page: props.pagination.current_page,
    });
};

const changePage = (page) => {
    if (page < 1 || page > props.pagination.last_page) {
        return;
    }

    visitSalesOutlets({ page });
};

const applyColumnFilters = (filters) => {
    columnFilters.value = { ...filters };
    activeModal.value = null;
    tableSettings.saveFilters(columnFilters.value);
    visitSalesOutlets({ column_filters: columnFilters.value });
};

const clearColumnFilters = () => {
    columnFilters.value = {};
    activeModal.value = null;
    tableSettings.saveFilters({});
    visitSalesOutlets({ column_filters: {} });
};

const applyColumns = (columns) => {
    selectedColumns.value = columns;
    activeModal.value = null;
    tableSettings.saveColumns(columns);
    visitSalesOutlets({ columns });
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
    localSalesOutlets.value = localSalesOutlets.value.map((row) => (
        row.id === updatedRow.id
            ? { ...row, ...updatedRow }
            : row
    ));
};

const saveSalesOutlet = async (payload) => {
    if (! editingSalesOutlet.value) {
        return;
    }

    isSavingSalesOutlet.value = true;
    editModalError.value = '';
    editFieldErrors.value = {};

    try {
        const updatedRow = await salesOutletsClient.updateSalesOutlet(editingSalesOutlet.value.id, payload);
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
</script>

<template>
    <Head title="Объекты продаж 3" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-300">
                        Темная версия
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold leading-tight text-white">
                        Объекты продаж 3
                    </h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Таблица объектов продаж через service-e без отчётов и экспорта.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-end">
                    <Link
                        :href="route('objectsSalesOutlets.darkIndex')"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-200 shadow-lg shadow-black/20 transition hover:border-cyan-400 hover:text-white"
                    >
                        Объекты продаж 2
                    </Link>
                </div>
            </div>
        </template>

        <div class="bg-slate-950 py-8 text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl shadow-black/30">
                    <DarkSalesOutletsToolbar
                        :has-active-filters="hasActiveColumnFilters"
                        :show-reports="false"
                        @open-columns="activeModal = modalIds.columns"
                        @open-filters="activeModal = modalIds.filters"
                    />

                    <DarkSalesOutletsTable
                        :columns="columns"
                        :rows="localSalesOutlets"
                        :visible-columns="selectedColumns"
                        :sort="filters.sort"
                        :direction="filters.direction"
                        :api-prefix="apiPrefix"
                        @sort="sortBy"
                        @edit="openEditModal"
                    />

                    <DarkSalesOutletsPagination
                        :pagination="pagination"
                        @change-page="changePage"
                    />
                </div>
            </div>
        </div>

        <DarkColumnSettingsModal
            :show="isColumnModalOpen"
            :columns="columns"
            :selected-columns="selectedColumns"
            @close="activeModal = null"
            @apply="applyColumns"
        />

        <DarkColumnFiltersModal
            :show="isFilterModalOpen"
            :columns="columns"
            :filters="columnFilters"
            @close="activeModal = null"
            @clear="clearColumnFilters"
            @apply="applyColumnFilters"
        />

        <DarkSalesOutletEditModal
            :show="isEditModalOpen"
            :row="editingSalesOutlet"
            :is-saving="isSavingSalesOutlet"
            :server-error="editModalError"
            :field-errors="editFieldErrors"
            @close="closeEditModal"
            @save="saveSalesOutlet"
        />
    </AuthenticatedLayout>
</template>
