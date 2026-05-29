<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DarkColumnFiltersModal from '@/Components/ObjectsSalesOutlets/DarkColumnFiltersModal.vue';
import DarkColumnSettingsModal from '@/Components/ObjectsSalesOutlets/DarkColumnSettingsModal.vue';
import DarkSalesOutletEditModal from '@/Components/ObjectsSalesOutlets/DarkSalesOutletEditModal.vue';
import DarkSalesOutletsPagination from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsPagination.vue';
import DarkSalesOutletsTable from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsTable.vue';
import DarkSalesOutletsToolbar from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsToolbar.vue';
import { usePersistentTableSettings } from '@/Composables/usePersistentTableSettings';
import { SalesOutletValidationError, updateSalesOutlet } from '@/Services/salesOutlets';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

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
const modalIds = Object.freeze({
    columns: 'columns',
    filters: 'filters',
});
const activeModal = ref(null);
const exportJobUuid = ref(null);
const exportStatus = ref('idle');
const exportError = ref('');
const exportPollTimer = ref(null);
const mailJobUuid = ref(null);
const mailStatus = ref('idle');
const mailError = ref('');
const mailPollTimer = ref(null);
const localSalesOutlets = ref(props.salesOutlets.map((row) => ({ ...row })));
const editingSalesOutlet = ref(null);
const isEditModalOpen = ref(false);
const isSavingSalesOutlet = ref(false);
const editModalError = ref('');
const editFieldErrors = ref({});
const isColumnModalOpen = computed(() => activeModal.value === modalIds.columns);
const isFilterModalOpen = computed(() => activeModal.value === modalIds.filters);
const hasActiveColumnFilters = computed(() => Object.keys(columnFilters.value).length > 0);
const isExporting = computed(() => ['pending', 'processing'].includes(exportStatus.value));
const isMailing = computed(() => ['pending', 'processing'].includes(mailStatus.value));
const exportButtonText = computed(() => (isExporting.value ? 'Файл собирается...' : 'Сохранить в файл'));
const mailButtonText = computed(() => (isMailing.value ? 'Данные собираются...' : 'Отправить по почте'));
const exportStatusText = computed(() => {
    if (exportError.value) {
        return exportError.value;
    }

    if (exportStatus.value === 'pending') {
        return 'Экспорт поставлен в очередь.';
    }

    if (exportStatus.value === 'processing') {
        return 'Файл собирается, можно продолжать работу с таблицей.';
    }

    if (exportStatus.value === 'completed') {
        return 'Файл готов и скачивается.';
    }

    return '';
});
const mailStatusText = computed(() => {
    if (mailError.value) {
        return mailError.value;
    }

    if (mailStatus.value === 'pending') {
        return 'Отправка поставлена в очередь.';
    }

    if (mailStatus.value === 'processing') {
        return 'Данные собираются, можно продолжать работу с таблицей.';
    }

    if (mailStatus.value === 'completed') {
        return 'Письмо отправлено получателям.';
    }

    return '';
});
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

const exportPayload = () => ({
    search: props.filters.search,
    status: props.filters.status,
    column_filters: columnFilters.value,
    sort: props.filters.sort,
    direction: props.filters.direction,
    columns: selectedColumns.value,
});

const routeWithUuid = (template, uuid) => template.replace('__uuid__', uuid);

const stopExportPolling = () => {
    if (exportPollTimer.value === null) {
        return;
    }

    window.clearTimeout(exportPollTimer.value);
    exportPollTimer.value = null;
};

const stopMailPolling = () => {
    if (mailPollTimer.value === null) {
        return;
    }

    window.clearTimeout(mailPollTimer.value);
    mailPollTimer.value = null;
};

const downloadExport = (uuid) => {
    window.location.href = routeWithUuid(props.routes.exportDownload, uuid);
};

const pollExportStatus = async (uuid) => {
    const response = await fetch(routeWithUuid(props.routes.exportStatus, uuid), {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('Не удалось получить статус экспорта.');
    }

    const data = await response.json();
    exportStatus.value = data.status;

    if (data.status === 'completed') {
        stopExportPolling();
        downloadExport(uuid);
        return;
    }

    if (data.status === 'failed') {
        stopExportPolling();
        exportError.value = data.error_message || 'Не удалось собрать CSV-файл.';
        return;
    }

    exportPollTimer.value = window.setTimeout(() => pollExportStatus(uuid), 2000);
};

const saveToFile = async () => {
    if (isExporting.value) {
        return;
    }

    stopExportPolling();
    exportError.value = '';
    exportStatus.value = 'pending';

    try {
        const response = await fetch(props.routes.exportCreate, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify(exportPayload()),
        });

        if (!response.ok) {
            throw new Error('Не удалось запустить экспорт.');
        }

        const data = await response.json();
        exportJobUuid.value = data.uuid;
        exportStatus.value = data.status;
        await pollExportStatus(data.uuid);
    } catch (error) {
        stopExportPolling();
        exportStatus.value = 'failed';
        exportError.value = error.message || 'Не удалось запустить экспорт.';
    }
};

const pollMailStatus = async (uuid) => {
    try {
        const response = await fetch(routeWithUuid(props.routes.mailStatus, uuid), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Не удалось получить статус отправки.');
        }

        const data = await response.json();
        mailStatus.value = data.status;

        if (data.status === 'completed') {
            stopMailPolling();
            return;
        }

        if (data.status === 'failed') {
            stopMailPolling();
            mailError.value = data.error_message || 'Не удалось отправить отчёт по почте.';
            return;
        }

        mailPollTimer.value = window.setTimeout(() => pollMailStatus(uuid), 2000);
    } catch (error) {
        stopMailPolling();
        mailStatus.value = 'failed';
        mailError.value = error.message || 'Не удалось получить статус отправки.';
    }
};

const sendByMail = async () => {
    if (isMailing.value) {
        return;
    }

    stopMailPolling();
    mailError.value = '';
    mailStatus.value = 'pending';

    try {
        const response = await fetch(props.routes.mailCreate, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify(exportPayload()),
        });

        if (!response.ok) {
            throw new Error('Не удалось запустить отправку по почте.');
        }

        const data = await response.json();
        mailJobUuid.value = data.uuid;
        mailStatus.value = data.status;
        await pollMailStatus(data.uuid);
    } catch (error) {
        stopMailPolling();
        mailStatus.value = 'failed';
        mailError.value = error.message || 'Не удалось запустить отправку по почте.';
    }
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
        const updatedRow = await updateSalesOutlet(editingSalesOutlet.value.id, payload);
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

onBeforeUnmount(() => {
    stopExportPolling();
    stopMailPolling();
});
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
                        :is-exporting="isExporting"
                        :export-button-text="exportButtonText"
                        :export-status-text="exportStatusText"
                        :is-mailing="isMailing"
                        :mail-button-text="mailButtonText"
                        :mail-status-text="mailStatusText"
                        @open-columns="activeModal = modalIds.columns"
                        @open-filters="activeModal = modalIds.filters"
                        @save-file="saveToFile"
                        @send-mail="sendByMail"
                    />

                    <DarkSalesOutletsTable
                        :columns="columns"
                        :rows="localSalesOutlets"
                        :visible-columns="selectedColumns"
                        :sort="filters.sort"
                        :direction="filters.direction"
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
