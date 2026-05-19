<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DarkColumnFiltersModal from '@/Components/ObjectsSalesOutlets/DarkColumnFiltersModal.vue';
import DarkColumnSettingsModal from '@/Components/ObjectsSalesOutlets/DarkColumnSettingsModal.vue';
import DarkSalesOutletsPagination from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsPagination.vue';
import DarkSalesOutletsTable from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsTable.vue';
import DarkSalesOutletsToolbar from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsToolbar.vue';
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

const tableSettings = usePersistentTableSettings('objects-sales-outlets:dark-index', props.columns);
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
    <Head title="Объекты продаж 2" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-300">
                        Темная версия
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold leading-tight text-white">
                        Объекты продаж 2
                    </h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Та же таблица объектов продаж в темном Tailwind-дизайне.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-200 shadow-lg shadow-black/20 transition hover:border-cyan-400 hover:text-white"
                    @click="history.back()"
                >
                    Назад
                </button>
            </div>
        </template>

        <div class="bg-slate-950 py-8 text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-2xl shadow-black/30">
                    <DarkSalesOutletsToolbar
                        :has-active-filters="hasActiveColumnFilters"
                        @open-columns="isColumnModalOpen = true"
                        @open-filters="isFilterModalOpen = true"
                    />

                    <DarkSalesOutletsTable
                        :columns="columns"
                        :rows="salesOutlets"
                        :visible-columns="selectedColumns"
                        :sort="filters.sort"
                        :direction="filters.direction"
                        @sort="sortBy"
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
            @close="isColumnModalOpen = false"
            @apply="applyColumns"
        />

        <DarkColumnFiltersModal
            :show="isFilterModalOpen"
            :columns="columns"
            :filters="columnFilters"
            @close="isFilterModalOpen = false"
            @clear="clearColumnFilters"
            @apply="applyColumnFilters"
        />
    </AuthenticatedLayout>
</template>
