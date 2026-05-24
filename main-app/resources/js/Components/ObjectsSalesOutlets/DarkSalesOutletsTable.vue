<script setup>
import DarkSalesOutletsCell from '@/Components/ObjectsSalesOutlets/DarkSalesOutletsCell.vue';
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

const emit = defineEmits(['sort', 'edit']);
const localRows = ref(props.rows.map((row) => ({ ...row })));
const topScrollbar = ref(null);
const tableScrollbar = ref(null);
const openPoptipId = ref(null);

watch(
    () => props.rows,
    (rows) => {
        localRows.value = rows.map((row) => ({ ...row }));
    },
);

const visibleColumnDefinitions = computed(() =>
    props.columns.filter((column) => props.visibleColumns.includes(column.key)),
);
const actionsColumnWidth = 128;
const defaultColumnWidth = 160;
const tableMinWidth = computed(() =>
    visibleColumnDefinitions.value.reduce(
        (width, column) => width + (column.width ?? defaultColumnWidth),
        actionsColumnWidth,
    ),
);
const columnStyle = (column) => ({
    width: `${column.width ?? defaultColumnWidth}px`,
    minWidth: `${column.width ?? defaultColumnWidth}px`,
});
const columnAlignClass = (column) => ({
    'text-left': column.align === 'left',
    'text-right': column.align === 'right',
    'text-center': !column.align || column.align === 'center',
});
const actionsColumnStyle = {
    width: `${actionsColumnWidth}px`,
    minWidth: `${actionsColumnWidth}px`,
    maxWidth: `${actionsColumnWidth}px`,
};

const sortMark = (column) => {
    if (props.sort !== column.key) {
        return '↕';
    }

    return props.direction === 'asc' ? '↑' : '↓';
};

const rowClass = (row) => ({
    'border-rose-500/30 bg-rose-950/70': row.row_tone === 'danger',
    'border-amber-500/30 bg-amber-950/60': row.row_tone === 'warning',
    'border-emerald-500/30 bg-emerald-950/60': row.row_tone === 'success',
});
const actionsCellClass = (row) => ({
    'bg-rose-950': row.row_tone === 'danger',
    'bg-amber-950': row.row_tone === 'warning',
    'bg-emerald-950': row.row_tone === 'success',
    'bg-slate-950': !row.row_tone,
});

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

const saveCell = async (payload) => {
    const updatedRow = await updateHeadOrganization(payload);
    replaceLocalRow(updatedRow, payload.head_organization_type);
};

const makePoptipId = (row, column) => `${row.id}:${column.key}`;

const syncScroll = (source, target) => {
    if (!source || !target || source.scrollLeft === target.scrollLeft) {
        return;
    }

    target.scrollLeft = source.scrollLeft;
};

const syncTableScroll = () => {
    syncScroll(topScrollbar.value, tableScrollbar.value);
};

const syncTopScroll = () => {
    syncScroll(tableScrollbar.value, topScrollbar.value);
};
</script>

<template>
    <div class="overflow-hidden bg-slate-950/40">
        <div
            ref="topScrollbar"
            class="overflow-x-auto border-b border-slate-800 bg-slate-950/70"
            @scroll="syncTableScroll"
        >
            <div
                class="h-3"
                :style="{ width: `${tableMinWidth}px` }"
            ></div>
        </div>

        <div
            ref="tableScrollbar"
            class="overflow-x-auto"
            @scroll="syncTopScroll"
        >
            <table
                class="w-full border-collapse text-sm text-[#ff851b]"
                :style="{ minWidth: `${tableMinWidth}px` }"
            >
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/80 text-xs uppercase tracking-wide text-[#ff851b]">
                        <th
                            v-for="column in visibleColumnDefinitions"
                            :key="column.key"
                            class="border-r border-slate-800 px-4 py-5 font-semibold last:border-r-0"
                            :class="columnAlignClass(column)"
                            :style="columnStyle(column)"
                        >
                            <button
                                v-if="column.sortable"
                                type="button"
                                class="inline-flex items-center gap-1 transition hover:text-cyan-200"
                                @click="emit('sort', column.key)"
                            >
                                <span>{{ column.label }}</span>
                                <span>{{ sortMark(column) }}</span>
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </th>
                        <th
                            class="sticky right-0 z-20 border-l border-slate-800 bg-slate-950 px-4 py-5 text-center font-semibold text-[#ff851b] shadow-[-14px_0_20px_-18px_rgba(0,0,0,0.95)]"
                            :style="actionsColumnStyle"
                        >
                            Действия
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="localRows.length === 0">
                        <td
                            :colspan="visibleColumnDefinitions.length + 1"
                            class="px-6 py-12 text-center text-[#ff851b]"
                        >
                            Объекты продаж не найдены
                        </td>
                    </tr>

                    <tr
                        v-for="row in localRows"
                        :key="row.id"
                        class="border-b"
                        :class="rowClass(row)"
                    >
                        <td
                            v-for="column in visibleColumnDefinitions"
                            :key="column.key"
                            class="h-24 border-r border-white/10 px-4 py-3 align-middle font-medium last:border-r-0"
                            :class="columnAlignClass(column)"
                            :style="columnStyle(column)"
                        >
                            <DarkSalesOutletsCell
                                :column="column"
                                :row="row"
                                :save-action="saveCell"
                                :poptip-id="makePoptipId(row, column)"
                                :open-poptip-id="openPoptipId"
                                @open-poptip="openPoptipId = $event"
                                @close-poptip="openPoptipId = null"
                            />
                        </td>
                        <td
                            class="sticky right-0 z-20 h-24 border-l border-white/10 px-3 py-3 text-center align-middle shadow-[-14px_0_20px_-18px_rgba(0,0,0,0.95)]"
                            :class="actionsCellClass(row)"
                            :style="actionsColumnStyle"
                        >
                            <div class="flex flex-col items-center gap-2">
                                <div class="group relative inline-flex">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-cyan-500 px-2.5 py-1 text-xs font-semibold text-slate-950 shadow-sm transition hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-900"
                                        :aria-label="`Редактировать объект продаж ${row.id}`"
                                        @click="emit('edit', row)"
                                    >
                                        ✎
                                    </button>
                                    <div class="pointer-events-none absolute right-full top-1/2 z-50 mr-3 w-48 -translate-y-1/2 translate-x-1 rounded-lg border border-cyan-400/30 bg-slate-950 px-3 py-2 text-center text-xs font-medium leading-snug text-slate-100 opacity-0 shadow-xl shadow-black/40 transition duration-150 group-hover:translate-x-0 group-hover:opacity-100 group-focus-within:translate-x-0 group-focus-within:opacity-100">
                                        Редактировать объект продаж {{ row.id }}
                                        <span class="absolute left-full top-1/2 h-2 w-2 -translate-x-1 -translate-y-1/2 rotate-45 border-r border-t border-cyan-400/30 bg-slate-950"></span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-lg border border-cyan-400/40 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-cyan-100 transition hover:border-cyan-300 hover:bg-slate-800"
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
