<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    columns: {
        type: Array,
        required: true,
    },
    selectedColumns: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['close', 'apply']);
const localColumns = ref([...props.selectedColumns]);

watch(
    () => props.selectedColumns,
    (columns) => {
        localColumns.value = [...columns];
    },
);

watch(
    () => props.show,
    (show) => {
        if (show) {
            localColumns.value = [...props.selectedColumns];
        }
    },
);

const toggleColumn = (columnKey) => {
    if (localColumns.value.includes(columnKey)) {
        localColumns.value = localColumns.value.filter((key) => key !== columnKey);
        return;
    }

    localColumns.value = [...localColumns.value, columnKey];
};

const selectAll = () => {
    localColumns.value = props.columns.map((column) => column.key);
};

const apply = () => {
    const columns = localColumns.value.length
        ? localColumns.value
        : props.columns.map((column) => column.key);

    emit('apply', columns);
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 px-4 py-6 backdrop-blur-sm sm:px-0"
            @click.self="emit('close')"
        >
            <div class="mx-auto w-full max-w-lg overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl shadow-black/50">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                Настройка таблицы
                            </h3>
                            <p class="mt-1 text-sm text-slate-400">
                                Выберите колонки, которые должны быть видны в списке объектов продаж.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-2xl leading-none text-slate-500 transition hover:text-white"
                            @click="emit('close')"
                        >
                            ×
                        </button>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="column in columns"
                            :key="column.key"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-200 transition hover:border-cyan-400/70 hover:bg-slate-800"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-slate-600 bg-slate-900 text-cyan-500 shadow-sm focus:ring-cyan-500"
                                :checked="localColumns.includes(column.key)"
                                @change="toggleColumn(column.key)"
                            />
                            <span>{{ column.label }}</span>
                        </label>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-100 transition hover:border-cyan-400"
                            @click="selectAll"
                        >
                            Выбрать все
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-300 transition hover:text-white"
                            @click="emit('close')"
                        >
                            Отмена
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-950 transition hover:bg-cyan-400"
                            @click="apply"
                        >
                            Применить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
