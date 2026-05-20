<script setup>
import HeadOrganizationPoptip from '@/Components/ObjectsSalesOutlets/HeadOrganizationPoptip.vue';
import { updateHeadOrganization } from '@/Services/salesOutlets';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        required: true,
    },
    visibleColumns: {
        type: Array,
        required: true,
    },
    sort: {
        type: String,
        default: 'id',
    },
    direction: {
        type: String,
        default: 'asc',
    },
});

const emit = defineEmits(['sort']);
const localRows = ref(props.rows.map((row) => ({ ...row })));

watch(
    () => props.rows,
    (rows) => {
        localRows.value = rows.map((row) => ({ ...row }));
    },
);

const visibleColumnDefinitions = () =>
    props.columns.filter((column) => props.visibleColumns.includes(column.key));

const rowClass = (row) => ({
    'bg-rose-200 text-white': row.row_tone === 'danger',
    'bg-amber-100 text-amber-950': row.row_tone === 'warning',
    'bg-emerald-50 text-emerald-950': row.row_tone === 'success',
});

const sortMark = (column) => {
    if (props.sort !== column.key) {
        return '↕';
    }

    return props.direction === 'asc' ? '↑' : '↓';
};

const organizationKindLabels = {
    ooo: 'ООО',
    ip: 'ИП',
    ao: 'АО',
    spk: 'СПК',
};
const organizationKinds = Object.values(organizationKindLabels);
const normalizeOrganizationKind = (value) => {
    const normalizedValue = String(value ?? '').trim();

    if (organizationKinds.includes(normalizedValue)) {
        return normalizedValue;
    }

    return organizationKindLabels[normalizedValue.toLowerCase()] ?? '';
};
const formatHeadOrganization = (row, fallbackType = '') => {
    const name = String(row.head_organization ?? '').trim();
    const type = normalizeOrganizationKind(
        row.head_organization_type_label || row.head_organization_type || fallbackType,
    );

    if (name === '' || type === '' || organizationKinds.some((kind) => name.startsWith(`${kind} `))) {
        return name;
    }

    return `${type} ${name}`;
};
const displayRow = (row, fallbackType = '') => ({
    ...row,
    head_organization: formatHeadOrganization(row, fallbackType),
});

const replaceLocalRow = (updatedRow, fallbackType = '') => {
    const rowForDisplay = displayRow(updatedRow, fallbackType);

    localRows.value = localRows.value.map((localRow) => (
        localRow.id === rowForDisplay.id
            ? { ...localRow, ...rowForDisplay }
            : localRow
    ));
};

const saveHeadOrganization = async (payload) => {
    const updatedRow = await updateHeadOrganization(payload);
    replaceLocalRow(updatedRow, payload.head_organization_type);
};
</script>

<template>
    <div class="overflow-hidden bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-[1180px] table-fixed border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <th
                            v-for="column in visibleColumnDefinitions()"
                            :key="column.key"
                            class="w-36 border-r border-gray-200 px-4 py-5 text-center font-semibold last:border-r-0"
                        >
                            <button
                                v-if="column.sortable"
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-gray-800"
                                @click="emit('sort', column.key)"
                            >
                                <span>{{ column.label }}</span>
                                <span class="text-gray-400">{{ sortMark(column) }}</span>
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </th>
                        <th class="sticky right-0 z-10 w-32 bg-gray-50 px-4 py-5 text-center font-semibold">
                            Действия
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="localRows.length === 0">
                        <td
                            :colspan="visibleColumnDefinitions().length + 1"
                            class="px-6 py-12 text-center text-gray-500"
                        >
                            Объекты продаж не найдены
                        </td>
                    </tr>

                    <tr
                        v-for="row in localRows"
                        :key="row.id"
                        class="border-b border-white/40"
                        :class="rowClass(row)"
                    >
                        <td
                            v-for="column in visibleColumnDefinitions()"
                            :key="column.key"
                            class="h-24 border-r border-white/30 px-4 py-3 text-center align-middle font-medium last:border-r-0"
                        >
                            <HeadOrganizationPoptip
                                v-if="column.key === 'head_organization'"
                                :row-id="row.id"
                                :value="row[column.key]"
                                :organization-type="row.head_organization_type_label || row.head_organization_type"
                                :save-action="saveHeadOrganization"
                            />
                            <span v-else>{{ row[column.key] }}</span>
                        </td>
                        <td class="sticky right-0 z-10 h-24 bg-inherit px-3 py-3 text-center align-middle shadow-[-8px_0_12px_-12px_rgba(0,0,0,0.35)]">
                            <div class="flex flex-col items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-md bg-emerald-500 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                                    :title="`Редактировать объект продаж ${row.id}`"
                                >
                                    ✎
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md bg-emerald-500 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                                >
                                    Визиты
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
