<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ColumnFiltersModal from '@/Components/ObjectsSalesOutlets/ColumnFiltersModal.vue';
import ColumnSettingsModal from '@/Components/ObjectsSalesOutlets/ColumnSettingsModal.vue';
import SalesOutletsPagination from '@/Components/ObjectsSalesOutlets/SalesOutletsPagination.vue';
import SalesOutletsTable from '@/Components/ObjectsSalesOutlets/SalesOutletsTable.vue';
import SalesOutletsToolbar from '@/Components/ObjectsSalesOutlets/SalesOutletsToolbar.vue';
import { usePersistentTableSettings } from '@/Composables/usePersistentTableSettings';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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

const tableSettings = usePersistentTableSettings('objects-sales-outlets:index', props.columns);
const selectedColumns = ref(tableSettings.savedColumns ?? [...props.filters.columns]);
const columnFilters = ref(tableSettings.savedFilters ?? { ...props.filters.column_filters });
const isColumnModalOpen = ref(false);
const isFilterModalOpen = ref(false);
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
    router.get(
        props.routes.index,
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
}

watch(
    () => props.filters.column_filters,
    (filters) => {
        columnFilters.value = { ...filters };
    },
);

const visitSalesOutlets = (overrides = {}) => {
    router.get(
        props.routes.index,
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
    isFilterModalOpen.value = false;
    tableSettings.saveFilters(columnFilters.value);
    visitSalesOutlets({ column_filters: columnFilters.value });
};

const clearColumnFilters = () => {
    columnFilters.value = {};
    isFilterModalOpen.value = false;
    tableSettings.saveFilters({});
    visitSalesOutlets({ column_filters: {} });
};

const applyColumns = (columns) => {
    selectedColumns.value = columns;
    isColumnModalOpen.value = false;
    tableSettings.saveColumns(columns);
    visitSalesOutlets({ columns });
};
</script>

<template>
    <Head title="Объекты продаж" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Таблица объектов продаж
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Поиск, фильтрация и массовая работа с объектами продаж.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                    @click="history.back()"
                >
                    Назад
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <SalesOutletsToolbar
                        :has-active-filters="hasActiveColumnFilters"
                        @open-columns="isColumnModalOpen = true"
                        @open-filters="isFilterModalOpen = true"
                    />

                    <SalesOutletsTable
                        :columns="columns"
                        :rows="salesOutlets"
                        :visible-columns="selectedColumns"
                        :sort="filters.sort"
                        :direction="filters.direction"
                        @sort="sortBy"
                    />

                    <SalesOutletsPagination
                        :pagination="pagination"
                        @change-page="changePage"
                    />
                </div>
            </div>
        </div>

        <ColumnSettingsModal
            :show="isColumnModalOpen"
            :columns="columns"
            :selected-columns="selectedColumns"
            @close="isColumnModalOpen = false"
            @apply="applyColumns"
        />

        <ColumnFiltersModal
            :show="isFilterModalOpen"
            :columns="columns"
            :filters="columnFilters"
            @close="isFilterModalOpen = false"
            @clear="clearColumnFilters"
            @apply="applyColumnFilters"
        />
    </AuthenticatedLayout>
</template>
